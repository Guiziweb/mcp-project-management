<?php

declare(strict_types=1);

namespace App\OAuth\Infrastructure\Security;

use App\Admin\Infrastructure\Doctrine\Entity\AccessToken;
use App\Admin\Infrastructure\Doctrine\Entity\User;
use App\Admin\Infrastructure\Doctrine\Repository\AccessTokenRepository;
use App\Shared\Infrastructure\Security\EncryptionService;
use Psr\Clock\ClockInterface;

final class TokenService
{
    public function __construct(
        private readonly AccessTokenRepository $tokenRepository,
        private readonly EncryptionService $encryption,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @param array{provider: string, org_config: array<string, mixed>, user_credentials: array<string, mixed>} $credentials
     */
    public function createAccessToken(User $user, array $credentials): string
    {
        $plainToken = AccessToken::generateToken();
        $encryptedCredentials = $this->encryption->encrypt(
            json_encode($credentials, JSON_THROW_ON_ERROR)
        );

        $token = new AccessToken(
            user: $user,
            tokenHash: AccessToken::hashToken($plainToken),
            credentials: $encryptedCredentials,
            type: 'access',
            parentToken: null,
            createdAt: $this->clock->now(),
        );

        $this->tokenRepository->save($token);

        return $plainToken;
    }

    /**
     * @param array{provider: string, org_config: array<string, mixed>, user_credentials: array<string, mixed>} $credentials
     */
    public function createRefreshToken(User $user, array $credentials, AccessToken $accessToken): string
    {
        $plainToken = AccessToken::generateToken();
        $encryptedCredentials = $this->encryption->encrypt(
            json_encode($credentials, JSON_THROW_ON_ERROR)
        );

        $token = new AccessToken(
            user: $user,
            tokenHash: AccessToken::hashToken($plainToken),
            credentials: $encryptedCredentials,
            type: 'refresh',
            parentToken: $accessToken,
            createdAt: $this->clock->now(),
        );

        $this->tokenRepository->save($token);

        return $plainToken;
    }

    /**
     * @param array{provider: string, org_config: array<string, mixed>, user_credentials: array<string, mixed>} $credentials
     *
     * @return array{access_token: string, refresh_token: string, token_type: string, expires_in: int}
     */
    public function createTokenPair(User $user, array $credentials): array
    {
        $now = $this->clock->now();
        $accessPlain = AccessToken::generateToken();
        $refreshPlain = AccessToken::generateToken();

        $encryptedCredentials = $this->encryption->encrypt(
            json_encode($credentials, JSON_THROW_ON_ERROR)
        );

        $accessToken = new AccessToken(
            user: $user,
            tokenHash: AccessToken::hashToken($accessPlain),
            credentials: $encryptedCredentials,
            type: 'access',
            parentToken: null,
            createdAt: $now,
        );
        $this->tokenRepository->save($accessToken);

        $refreshToken = new AccessToken(
            user: $user,
            tokenHash: AccessToken::hashToken($refreshPlain),
            credentials: $encryptedCredentials,
            type: 'refresh',
            parentToken: $accessToken,
            createdAt: $now,
        );
        $this->tokenRepository->save($refreshToken);

        return [
            'access_token' => $accessPlain,
            'refresh_token' => $refreshPlain,
            'token_type' => 'Bearer',
            'expires_in' => 86400, // 24 hours
        ];
    }

    public function validateAccessToken(string $plainToken): ?AccessToken
    {
        $now = $this->clock->now();
        $hash = AccessToken::hashToken($plainToken);
        $token = $this->tokenRepository->findValidByTokenHash($hash, $now);

        if (null === $token || !$token->isAccessToken()) {
            return null;
        }

        $token->touch($now);
        $this->tokenRepository->save($token);

        return $token;
    }

    public function validateRefreshToken(string $plainToken): ?AccessToken
    {
        $now = $this->clock->now();
        $hash = AccessToken::hashToken($plainToken);
        $token = $this->tokenRepository->findValidByTokenHash($hash, $now);

        if (null === $token || !$token->isRefreshToken()) {
            return null;
        }

        return $token;
    }

    /**
     * @return array{provider: string, org_config: array<string, mixed>, user_credentials: array<string, mixed>}
     */
    public function extractCredentials(AccessToken $token): array
    {
        $decrypted = $this->encryption->decrypt($token->getCredentials());
        $credentials = json_decode($decrypted, true);

        if (!is_array($credentials) || !isset($credentials['provider'])) {
            throw new \RuntimeException('Invalid credentials format in token');
        }

        return [
            'provider' => $credentials['provider'],
            'org_config' => $credentials['org_config'] ?? [],
            'user_credentials' => $credentials['user_credentials'] ?? [],
        ];
    }

    /**
     * @return array{access_token: string, refresh_token: string, token_type: string, expires_in: int}
     */
    public function refreshTokens(AccessToken $refreshToken): array
    {
        $now = $this->clock->now();
        $credentials = $this->extractCredentials($refreshToken);
        $user = $refreshToken->getUser();

        // Revoke old refresh token
        $refreshToken->revoke($now);
        $this->tokenRepository->save($refreshToken);

        // Revoke old access token if exists
        $oldAccessToken = $refreshToken->getParentToken();
        if (null !== $oldAccessToken && !$oldAccessToken->isRevoked()) {
            $oldAccessToken->revoke($now);
            $this->tokenRepository->save($oldAccessToken);
        }

        return $this->createTokenPair($user, $credentials);
    }

    public function revokeToken(string $plainToken): bool
    {
        $hash = AccessToken::hashToken($plainToken);
        $token = $this->tokenRepository->findByTokenHash($hash);

        if (null === $token) {
            return false;
        }

        $token->revoke($this->clock->now());
        $this->tokenRepository->save($token);

        return true;
    }

    public function revokeAllUserTokens(User $user): int
    {
        return $this->tokenRepository->revokeAllForUser($user);
    }
}
