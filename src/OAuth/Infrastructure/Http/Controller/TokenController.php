<?php

declare(strict_types=1);

namespace App\OAuth\Infrastructure\Http\Controller;

use App\OAuth\Application\UseCase\ExchangeCodeForTokensUseCase;
use App\OAuth\Application\UseCase\InvalidAuthorizationCodeException;
use App\OAuth\Application\UseCase\InvalidRefreshTokenException;
use App\OAuth\Application\UseCase\RedirectUriMismatchException;
use App\OAuth\Application\UseCase\RefreshTokensUseCase;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * OAuth 2.0 Token endpoint.
 * Handles authorization code exchange and token refresh.
 */
final class TokenController extends AbstractController
{
    public function __construct(
        private readonly ExchangeCodeForTokensUseCase $exchangeCodeUseCase,
        private readonly RefreshTokensUseCase $refreshTokensUseCase,
    ) {
    }

    /**
     * OAuth token endpoint.
     * Exchanges authorization code for access token + refresh token.
     */
    #[Route('/oauth/token', name: 'oauth_token', methods: ['POST'])]
    public function token(Request $request): JsonResponse
    {
        $grantType = $request->request->get('grant_type');

        return match ($grantType) {
            'authorization_code' => $this->handleAuthorizationCodeGrant($request),
            'refresh_token' => $this->handleRefreshTokenGrant($request),
            default => new JsonResponse(['error' => 'unsupported_grant_type'], 400),
        };
    }

    private function handleAuthorizationCodeGrant(Request $request): JsonResponse
    {
        $code = $request->request->get('code');
        $redirectUri = $request->request->get('redirect_uri');

        if (!is_string($code)) {
            return new JsonResponse([
                'error' => 'invalid_request',
                'error_description' => 'Missing code parameter',
            ], 400);
        }

        try {
            $tokenPair = $this->exchangeCodeUseCase->execute($code, (string) $redirectUri);

            return new JsonResponse($tokenPair->toArray());
        } catch (InvalidAuthorizationCodeException $e) {
            return new JsonResponse([
                'error' => 'invalid_grant',
                'error_description' => $e->getMessage(),
            ], 400);
        } catch (RedirectUriMismatchException $e) {
            return new JsonResponse([
                'error' => 'invalid_grant',
                'error_description' => $e->getMessage(),
            ], 400);
        }
    }

    private function handleRefreshTokenGrant(Request $request): JsonResponse
    {
        $refreshToken = $request->request->get('refresh_token');

        if (!is_string($refreshToken)) {
            return new JsonResponse([
                'error' => 'invalid_request',
                'error_description' => 'Missing refresh_token parameter',
            ], 400);
        }

        try {
            $tokenPair = $this->refreshTokensUseCase->execute($refreshToken);

            return new JsonResponse($tokenPair->toArray());
        } catch (InvalidRefreshTokenException $e) {
            return new JsonResponse([
                'error' => 'invalid_grant',
                'error_description' => $e->getMessage(),
            ], 400);
        } catch (\RuntimeException $e) {
            return new JsonResponse([
                'error' => 'invalid_grant',
                'error_description' => $e->getMessage(),
            ], 400);
        }
    }
}
