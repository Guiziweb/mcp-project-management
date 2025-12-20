<?php

declare(strict_types=1);

namespace App\Controller;

use App\Infrastructure\Security\User;
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
 * Validates JWT tokens and loads per-user Redmine credentials.
 */
final class McpController extends AbstractController
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly ContainerInterface $serviceContainer,
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
        ]);

        // Convert Symfony Request to PSR-7
        $psr17Factory = new Psr17Factory();
        $creator = new ServerRequestCreator($psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory);
        $psrRequest = $creator->fromGlobals();

        // Build MCP server with automatic tool discovery
        $server = Server::builder()
            ->setServerInfo('redmine-mcp', '1.0.0')
            ->setDiscovery($this->projectDir.'/src', ['.'])
            ->setContainer($this->serviceContainer)
            ->setLogger($this->logger)
            ->setSession(new FileSessionStore($this->projectDir.'/var/mcp-sessions'))
            ->build();

        // Create HTTP transport (corsHeaders as 4th param, logger as 5th)
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
