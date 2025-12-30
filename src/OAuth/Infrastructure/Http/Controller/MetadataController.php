<?php

declare(strict_types=1);

namespace App\OAuth\Infrastructure\Http\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * OAuth 2.0 Metadata endpoints (RFC 8414, RFC 9728).
 */
final class MetadataController extends AbstractController
{
    /**
     * RFC 9728: OAuth 2.0 Protected Resource Metadata.
     * Tells clients where the authorization server is.
     */
    #[Route('/.well-known/oauth-protected-resource', name: 'oauth_metadata', methods: ['GET'])]
    public function protectedResource(Request $request): JsonResponse
    {
        $baseUrl = $this->getBaseUrl($request);

        return new JsonResponse([
            'resource' => $baseUrl.'/mcp',
            'authorization_servers' => [$baseUrl],
            'bearer_methods_supported' => ['header'],
            'resource_signing_alg_values_supported' => ['HS256'],
        ]);
    }

    /**
     * RFC 8414: OAuth 2.0 Authorization Server Metadata.
     * Describes the OAuth endpoints and capabilities.
     */
    #[Route('/.well-known/oauth-authorization-server', name: 'oauth_authorization_server_metadata', methods: ['GET'])]
    public function authorizationServer(Request $request): JsonResponse
    {
        $baseUrl = $this->getBaseUrl($request);

        return new JsonResponse([
            'issuer' => $baseUrl,
            'authorization_endpoint' => $baseUrl.'/oauth/authorize',
            'token_endpoint' => $baseUrl.'/oauth/token',
            'registration_endpoint' => $baseUrl.'/oauth/register',
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'token_endpoint_auth_methods_supported' => ['none', 'client_secret_post'],
            'code_challenge_methods_supported' => ['plain', 'S256'],
        ]);
    }

    /**
     * Get base URL from request, respecting proxy headers.
     */
    private function getBaseUrl(Request $request): string
    {
        return $request->headers->get('X-Forwarded-Proto')
            ? $request->headers->get('X-Forwarded-Proto').'://'.$request->getHost()
            : $request->getSchemeAndHttpHost();
    }
}
