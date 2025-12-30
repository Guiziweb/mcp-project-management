<?php

declare(strict_types=1);

namespace App\OAuth\Application\UseCase;

use App\OAuth\Infrastructure\Security\TokenService;

/**
 * Refreshes an expired access token using a valid refresh token.
 */
final class RefreshTokensUseCase
{
    public function __construct(
        private readonly TokenService $tokenService,
    ) {
    }

    /**
     * @throws InvalidRefreshTokenException
     */
    public function execute(string $refreshToken): TokenPair
    {
        $token = $this->tokenService->validateRefreshToken($refreshToken);

        if (null === $token) {
            throw new InvalidRefreshTokenException('Invalid or expired refresh token');
        }

        $tokens = $this->tokenService->refreshTokens($token);

        return new TokenPair($tokens['access_token'], $tokens['refresh_token']);
    }
}
