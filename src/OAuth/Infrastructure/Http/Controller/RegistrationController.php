<?php

declare(strict_types=1);

namespace App\OAuth\Infrastructure\Http\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * RFC 7591: OAuth 2.0 Dynamic Client Registration.
 */
final class RegistrationController extends AbstractController
{
    /**
     * Allows clients to register themselves automatically.
     */
    #[Route('/oauth/register', name: 'oauth_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $clientId = 'mcp-'.bin2hex(random_bytes(16));
        $clientSecret = bin2hex(random_bytes(32));

        return new JsonResponse([
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'client_id_issued_at' => time(),
            'client_secret_expires_at' => 0,
            'redirect_uris' => $data['redirect_uris'] ?? [],
            'token_endpoint_auth_method' => 'none',
            'grant_types' => ['authorization_code', 'refresh_token'],
            'response_types' => ['code'],
        ], 201);
    }
}
