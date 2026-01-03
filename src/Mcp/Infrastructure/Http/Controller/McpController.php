<?php

declare(strict_types=1);

namespace App\Mcp\Infrastructure\Http\Controller;

use App\Admin\Infrastructure\Doctrine\Entity\User;
use App\Admin\Infrastructure\Doctrine\Repository\UserRepository;
use App\Admin\Infrastructure\Service\ToolRegistry;
use App\Admin\Infrastructure\Session\DoctrineSessionStore;
use App\Mcp\Application\Resource\ProjectActivitiesResource;
use App\Mcp\Application\Resource\ProjectMembersResource;
use App\Mcp\Application\Resource\StatusesResource;
use App\Mcp\Infrastructure\Adapter\AdapterFactory;
use App\Mcp\Infrastructure\Adapter\AdapterHolder;
use App\Mcp\Infrastructure\Security\McpUser;
use Mcp\Server;
use Mcp\Server\Transport\StreamableHttpTransport;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * HTTP MCP endpoint with OAuth authentication.
 *
 * Tools are registered dynamically via ToolRegistry based on:
 * - Provider type (Redmine, Jira, Monday)
 * - User permissions (enabledTools)
 */
final class McpController extends AbstractController
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly ContainerInterface $serviceContainer,
        private readonly UserRepository $userRepository,
        private readonly DoctrineSessionStore $doctrineSessionStore,
        private readonly AdapterFactory $adapterFactory,
        private readonly AdapterHolder $adapterHolder,
        private readonly ToolRegistry $toolRegistry,
    ) {
    }

    #[Route('/mcp', name: 'mcp_endpoint', methods: ['GET', 'POST', 'DELETE'])]
    public function handle(Request $request): Response
    {
        $mcpUser = $this->getUser();
        if (!$mcpUser instanceof McpUser) {
            throw new \LogicException('User should be authenticated by Symfony Security');
        }

        $provider = $mcpUser->getCredential()->provider;

        // Load database user for permissions and session
        $dbUser = $this->userRepository->find((int) $mcpUser->getUserIdentifier());
        if (null === $dbUser) {
            throw new \RuntimeException('User not found in database. Please use an invite link to create your account.');
        }

        $this->logger->debug('Building MCP server for user', [
            'user_id' => $mcpUser->getUserIdentifier(),
            'provider' => $provider,
        ]);

        // Convert Symfony Request to PSR-7
        $psr17Factory = new Psr17Factory();
        $psrHttpFactory = new PsrHttpFactory($psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory);
        $psrRequest = $psrHttpFactory->createRequest($request);

        // Configure session store
        $this->doctrineSessionStore->setCurrentUser($dbUser);

        // Create and set the adapter for the current user
        $adapter = $this->adapterFactory->createForUser($mcpUser->getCredential());
        $this->adapterHolder->set($adapter);

        // Build MCP server
        $builder = Server::builder()
            ->setServerInfo('mcp-timetracking', '1.0.0')
            ->setContainer($this->serviceContainer)
            ->setLogger($this->logger)
            ->setSession($this->doctrineSessionStore);

        // Register tools based on provider and user permissions
        $this->toolRegistry->registerTools($builder, $dbUser, $provider);

        // Register resources based on tool dependencies (Redmine only)
        if ('redmine' === $provider) {
            $this->registerRedmineResources($builder, $dbUser);
        }

        $server = $builder->build();

        // Create HTTP transport
        $transport = new StreamableHttpTransport($psrRequest, $psr17Factory, $psr17Factory, [], $this->logger);

        // Run server and get response
        $psrResponse = $server->run($transport);

        return new Response(
            (string) $psrResponse->getBody(),
            $psrResponse->getStatusCode(),
            $psrResponse->getHeaders()
        );
    }

    private function registerRedmineResources(Server\Builder $builder, User $user): void
    {
        $instructions = [];

        // Activities resource - needed for log_time
        if ($user->hasToolEnabled('log_time')) {
            $builder->addResourceTemplate(
                [ProjectActivitiesResource::class, 'getProjectActivities'],
                uriTemplate: 'provider://projects/{project_id}/activities',
                name: 'project_activities',
                description: 'List of available time entry activities for a project. Use to get valid activity IDs for logging time.',
                mimeType: 'application/json'
            );
            $instructions[] = 'When logging time with log_time, you need an activity_id. Read "provider://projects/{project_id}/activities" to get the list of available activities for that project.';
        }

        // Statuses resource - needed for update_issue and list_issues (filtering)
        if ($user->hasToolEnabled('update_issue') || $user->hasToolEnabled('list_issues')) {
            $builder->addResource(
                [StatusesResource::class, 'getStatuses'],
                uri: 'provider://statuses',
                name: 'statuses',
                description: 'List of available issue statuses with their IDs',
                mimeType: 'application/json'
            );
            $instructions[] = 'Read "provider://statuses" to get status IDs for filtering issues or updating issue status.';
        }

        // Members resource - needed for update_issue (assign)
        if ($user->hasToolEnabled('update_issue')) {
            $builder->addResourceTemplate(
                [ProjectMembersResource::class, 'getProjectMembers'],
                uriTemplate: 'provider://projects/{project_id}/members',
                name: 'project_members',
                description: 'List of project members with their IDs. Use to get valid user IDs for assigning issues.',
                mimeType: 'application/json'
            );
            $instructions[] = 'To assign an issue to someone, read "provider://projects/{project_id}/members" to get user IDs.';
        }

        if (!empty($instructions)) {
            $builder->setInstructions(implode("\n", $instructions));
        }
    }
}
