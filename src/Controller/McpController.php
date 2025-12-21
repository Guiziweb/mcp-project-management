<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\Port\ActivityPort;
use App\Domain\Port\TimeEntryWritePort;
use App\Infrastructure\Adapter\AdapterFactory;
use App\Infrastructure\Security\User;
use App\Tools\DeleteTimeEntryTool;
use App\Tools\GetAttachmentTool;
use App\Tools\GetIssueDetailsTool;
use App\Tools\ListActivitiesTool;
use App\Tools\ListIssuesTool;
use App\Tools\ListProjectsTool;
use App\Tools\ListTimeEntriesTool;
use App\Tools\LogTimeTool;
use App\Tools\UpdateTimeEntryTool;
use Mcp\Server;
use Mcp\Server\Session\FileSessionStore;
use Mcp\Server\Transport\StreamableHttpTransport;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * HTTP MCP endpoint with OAuth authentication.
 *
 * Tools are registered dynamically based on the provider's capabilities:
 * - ActivityPort: list_activities (Redmine only)
 * - TimeEntryWritePort: log_time, update_time_entry, delete_time_entry (not Monday)
 */
final class McpController extends AbstractController
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly ContainerInterface $serviceContainer,
        private readonly AdapterFactory $adapterFactory,
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
    ) {
    }

    #[Route('/mcp', name: 'mcp_endpoint', methods: ['GET', 'POST', 'DELETE'])]
    public function handle(Request $request): Response
    {
        // Get authenticated user (Symfony Security handles JWT validation)
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new \LogicException('User should be authenticated by Symfony Security');
        }

        $this->logger->debug('Building MCP server for user', [
            'user_id' => $user->getUserIdentifier(),
            'provider' => $user->getCredential()->provider,
        ]);

        // Create adapter to check capabilities
        $adapter = $this->adapterFactory->createForUser($user->getCredential());
        $supportsActivity = $adapter instanceof ActivityPort;
        $supportsTimeEntryWrite = $adapter instanceof TimeEntryWritePort;

        // Convert Symfony Request to PSR-7
        $psr17Factory = new Psr17Factory();
        $creator = new ServerRequestCreator($psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory);
        $psrRequest = $creator->fromGlobals();

        // Build MCP server with tools filtered by capabilities
        $builder = Server::builder()
            ->setServerInfo('mcp-timetracking', '1.0.0')
            ->setContainer($this->serviceContainer)
            ->setLogger($this->logger)
            ->setSession(new FileSessionStore($this->projectDir.'/var/mcp-sessions'));

        // Core tools (all providers)
        $builder->addTool([ListProjectsTool::class, 'listProjects']);
        $builder->addTool([ListIssuesTool::class, 'listIssues']);
        $builder->addTool([GetIssueDetailsTool::class, 'getIssueDetails']);
        $builder->addTool([ListTimeEntriesTool::class, 'listTimeEntries']);
        $builder->addTool([GetAttachmentTool::class, 'getAttachment']);

        // Activity tools (Redmine only)
        if ($supportsActivity) {
            $builder->addTool([ListActivitiesTool::class, 'listActivities']);
        }

        // Time entry write tools (Redmine, Jira - not Monday)
        if ($supportsTimeEntryWrite) {
            $builder->addTool([LogTimeTool::class, 'logTime']);
            $builder->addTool([UpdateTimeEntryTool::class, 'updateTimeEntry']);
            $builder->addTool([DeleteTimeEntryTool::class, 'deleteTimeEntry']);
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
}
