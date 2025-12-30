<?php

declare(strict_types=1);

namespace App\Mcp\Infrastructure\Http\Controller;

use App\Admin\Infrastructure\Doctrine\Repository\UserRepository;
use App\Admin\Infrastructure\Session\DoctrineSessionStore;
use App\Mcp\Application\Resource\ActivitiesResource;
use App\Mcp\Application\Resource\StatusesResource;
use App\Mcp\Application\Tool\DeleteTimeEntryTool;
use App\Mcp\Application\Tool\GetAttachmentTool;
use App\Mcp\Application\Tool\GetIssueDetailsTool;
use App\Mcp\Application\Tool\ListIssuesTool;
use App\Mcp\Application\Tool\ListProjectsTool;
use App\Mcp\Application\Tool\ListTimeEntriesTool;
use App\Mcp\Application\Tool\LogTimeTool;
use App\Mcp\Application\Tool\UpdateIssueTool;
use App\Mcp\Application\Tool\UpdateTimeEntryTool;
use App\Mcp\Domain\Port\ActivityPort;
use App\Mcp\Domain\Port\IssueWritePort;
use App\Mcp\Domain\Port\StatusPort;
use App\Mcp\Domain\Port\TimeEntryWritePort;
use App\Mcp\Infrastructure\Adapter\AdapterFactory;
use App\Mcp\Infrastructure\Security\McpUser;
use Mcp\Server;
use Mcp\Server\Session\SessionStoreInterface;
use Mcp\Server\Transport\StreamableHttpTransport;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * HTTP MCP endpoint with OAuth authentication.
 *
 * Tools and resources are registered dynamically based on the provider's capabilities:
 * - ActivityPort: activities resource (Redmine only)
 * - StatusPort: statuses resource (Redmine only)
 * - IssueWritePort: update_issue (Redmine only)
 * - TimeEntryWritePort: log_time, update_time_entry, delete_time_entry (not Monday)
 */
final class McpController extends AbstractController
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly ContainerInterface $serviceContainer,
        private readonly AdapterFactory $adapterFactory,
        private readonly UserRepository $userRepository,
        private readonly DoctrineSessionStore $doctrineSessionStore,
    ) {
    }

    #[Route('/mcp', name: 'mcp_endpoint', methods: ['GET', 'POST', 'DELETE'])]
    public function handle(Request $request): Response
    {
        // Get authenticated user (Symfony Security handles token validation)
        $user = $this->getUser();
        if (!$user instanceof McpUser) {
            throw new \LogicException('User should be authenticated by Symfony Security');
        }

        $this->logger->debug('Building MCP server for user', [
            'user_id' => $user->getUserIdentifier(),
            'provider' => $user->getCredential()->provider,
        ]);

        // Create adapter to check capabilities
        $adapter = $this->adapterFactory->createForUser($user->getCredential());
        $supportsActivity = $adapter instanceof ActivityPort;
        $supportsStatus = $adapter instanceof StatusPort;
        $supportsIssueWrite = $adapter instanceof IssueWritePort;
        $supportsTimeEntryWrite = $adapter instanceof TimeEntryWritePort;

        // Convert Symfony Request to PSR-7
        $psr17Factory = new Psr17Factory();
        $creator = new ServerRequestCreator($psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory);
        $psrRequest = $creator->fromGlobals();

        // Determine session store: use Doctrine if user exists in DB, otherwise file-based
        $sessionStore = $this->getSessionStore($user, $request);

        // Build MCP server with tools filtered by capabilities
        $builder = Server::builder()
            ->setServerInfo('mcp-timetracking', '1.0.0')
            ->setContainer($this->serviceContainer)
            ->setLogger($this->logger)
            ->setSession($sessionStore);

        // Core tools (all providers)
        $builder->addTool([ListProjectsTool::class, 'listProjects']);
        $builder->addTool([ListIssuesTool::class, 'listIssues']);
        $builder->addTool([GetIssueDetailsTool::class, 'getIssueDetails']);
        $builder->addTool([ListTimeEntriesTool::class, 'listTimeEntries']);
        $builder->addTool([GetAttachmentTool::class, 'getAttachment']);

        // Build instructions based on capabilities
        $instructions = [];

        // Activity resource (Redmine only) - exposed as resource for LLM to read proactively
        if ($supportsActivity) {
            $builder->addResource(
                [ActivitiesResource::class, 'getActivities'],
                uri: 'provider://activities',
                name: 'activities',
                description: 'List of available time entry activities for logging time',
                mimeType: 'application/json'
            );
            $instructions[] = 'When logging time with log_time, you need an activity_id. '.
                'Read the "provider://activities" resource to get the list of available activities with their IDs.';
        }

        // Status resource (Redmine only) - exposed as resource for LLM to read proactively
        if ($supportsStatus) {
            $builder->addResource(
                [StatusesResource::class, 'getStatuses'],
                uri: 'provider://statuses',
                name: 'statuses',
                description: 'List of available issue statuses with their IDs',
                mimeType: 'application/json'
            );
            $instructions[] = 'Read "provider://statuses" to get status IDs for filtering issues or updating issue status.';
        }

        // Issue write tools (Redmine only)
        if ($supportsIssueWrite) {
            $builder->addTool([UpdateIssueTool::class, 'updateIssue']);
        }

        // Time entry write tools (Redmine, Jira - not Monday)
        if ($supportsTimeEntryWrite) {
            $builder->addTool([LogTimeTool::class, 'logTime']);
            $builder->addTool([UpdateTimeEntryTool::class, 'updateTimeEntry']);
            $builder->addTool([DeleteTimeEntryTool::class, 'deleteTimeEntry']);
        }

        // Set combined instructions
        if (!empty($instructions)) {
            $builder->setInstructions(implode("\n", $instructions));
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

    /**
     * Configure the session store with the current user.
     */
    private function getSessionStore(McpUser $mcpUser, Request $request): SessionStoreInterface
    {
        $dbUser = $this->userRepository->find((int) $mcpUser->getUserIdentifier());

        if (null === $dbUser) {
            throw new \RuntimeException('User not found in database. Please use an invite link to create your account.');
        }

        $this->doctrineSessionStore->setCurrentUser($dbUser);

        // Try to detect client info from User-Agent
        $userAgent = $request->headers->get('User-Agent', '');
        if (str_contains($userAgent, 'Claude') || str_contains($userAgent, 'Anthropic')) {
            $this->doctrineSessionStore->setClientInfo('Claude Desktop');
        } elseif (str_contains($userAgent, 'Cursor')) {
            $this->doctrineSessionStore->setClientInfo('Cursor');
        } elseif ('' !== $userAgent) {
            $this->doctrineSessionStore->setClientInfo(substr($userAgent, 0, 100));
        }

        return $this->doctrineSessionStore;
    }
}
