<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * Validates JWT tokens and extracts user information.
 * Used by MCP server to authenticate requests from Claude Desktop.
 *
 * Tokens contain encrypted Redmine credentials (stateless architecture).
 */
final class JwtTokenValidator
{
    public function __construct(
        private readonly string $jwtSecret,
        private readonly EncryptionService $encryption,
    ) {
    }

    /**
     * Validate a JWT token and return the user ID.
     *
     * @throws \RuntimeException if token is invalid or expired
     */
    public function validateAndGetUserId(string $token): string
    {
        $payload = $this->decodeToken($token);

        if (!isset($payload->sub)) {
            throw new \RuntimeException('Token missing "sub" claim (user ID)');
        }

        return (string) $payload->sub;
    }

    /**
     * Decode a JWT token and return the full payload.
     *
     * @throws \RuntimeException if token is invalid or expired
     */
    public function decodeToken(string $token): \stdClass
    {
        try {
            return JWT::decode($token, new Key($this->jwtSecret, 'HS256'));
        } catch (\Exception $e) {
            throw new \RuntimeException('Invalid or expired token: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Extract Redmine credentials from a JWT token.
     *
     * @return array{url: string, key: string}
     *
     * @throws \RuntimeException if token is invalid or missing credentials
     */
    public function extractCredentials(string $token): array
    {
        $payload = $this->decodeToken($token);

        if (!isset($payload->redmine)) {
            throw new \RuntimeException('Token missing "redmine" claim (credentials)');
        }

        $decrypted = $this->encryption->decrypt((string) $payload->redmine);
        $credentials = json_decode($decrypted, true);

        if (!is_array($credentials) || !isset($credentials['url'], $credentials['key'])) {
            throw new \RuntimeException('Invalid credentials format in token');
        }

        return [
            'url' => $credentials['url'],
            'key' => $credentials['key'],
        ];
    }

    /**
     * Create a JWT token for a user (used by Authorization Server).
     *
     * @param array<string, mixed> $extraClaims
     */
    public function createToken(string $userId, int $expiresIn = 3600, array $extraClaims = []): string
    {
        $now = time();

        $payload = array_merge([
            'iss' => 'mcp-redmine-auth-server', // issuer
            'sub' => $userId, // subject (user ID)
            'iat' => $now, // issued at
            'exp' => $now + $expiresIn, // expiration
        ], $extraClaims);

        return JWT::encode($payload, $this->jwtSecret, 'HS256');
    }

    /**
     * Create a JWT token with embedded Redmine credentials.
     *
     * @param array<string, mixed> $extraClaims
     */
    public function createTokenWithCredentials(
        string $userId,
        string $redmineUrl,
        string $redmineApiKey,
        int $expiresIn = 86400,
        array $extraClaims = [],
    ): string {
        // Encrypt credentials as JSON blob
        $credentials = json_encode([
            'url' => $redmineUrl,
            'key' => $redmineApiKey,
        ], JSON_THROW_ON_ERROR);

        $encryptedCredentials = $this->encryption->encrypt($credentials);

        return $this->createToken($userId, $expiresIn, array_merge($extraClaims, [
            'redmine' => $encryptedCredentials,
        ]));
    }

    /**
     * Create an access token (short-lived, 24h).
     */
    public function createAccessToken(
        string $userId,
        string $redmineUrl,
        string $redmineApiKey,
        string $role = 'user',
        bool $isBot = false,
    ): string {
        return $this->createTokenWithCredentials(
            $userId,
            $redmineUrl,
            $redmineApiKey,
            86400, // 24 hours
            [
                'role' => $role,
                'is_bot' => $isBot,
                'type' => 'access',
            ],
        );
    }

    /**
     * Create a refresh token (long-lived, 30 days).
     */
    public function createRefreshToken(
        string $userId,
        string $redmineUrl,
        string $redmineApiKey,
        string $role = 'user',
        bool $isBot = false,
    ): string {
        return $this->createTokenWithCredentials(
            $userId,
            $redmineUrl,
            $redmineApiKey,
            30 * 24 * 3600, // 30 days
            [
                'role' => $role,
                'is_bot' => $isBot,
                'type' => 'refresh',
            ],
        );
    }

    /**
     * Check if a token is a refresh token.
     */
    public function isRefreshToken(string $token): bool
    {
        $payload = $this->decodeToken($token);

        return isset($payload->type) && 'refresh' === $payload->type;
    }
}
