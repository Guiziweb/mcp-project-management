<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * Validates JWT tokens and extracts user information.
 * Used by MCP server to authenticate requests from Claude Desktop.
 *
 * Tokens contain encrypted provider credentials (stateless architecture).
 * Supports multiple providers: Redmine, Jira, etc.
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
     * Extract provider credentials from a JWT token.
     *
     * @return array{provider: string, url: string, key: string, email: string|null}
     *
     * @throws \RuntimeException if token is invalid or missing credentials
     */
    public function extractCredentials(string $token): array
    {
        $payload = $this->decodeToken($token);

        if (!isset($payload->credentials)) {
            throw new \RuntimeException('Token missing credentials claim');
        }

        $decrypted = $this->encryption->decrypt((string) $payload->credentials);
        $credentials = json_decode($decrypted, true);

        if (!is_array($credentials) || !isset($credentials['provider'], $credentials['url'], $credentials['key'])) {
            throw new \RuntimeException('Invalid credentials format in token');
        }

        return [
            'provider' => $credentials['provider'],
            'url' => $credentials['url'],
            'key' => $credentials['key'],
            'email' => $credentials['email'] ?? null,
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
     * Create a JWT token with embedded provider credentials.
     *
     * @param array{provider: string, url: string, key: string, email?: string} $credentials
     * @param array<string, mixed>                                              $extraClaims
     */
    public function createTokenWithCredentials(
        string $userId,
        array $credentials,
        int $expiresIn = 86400,
        array $extraClaims = [],
    ): string {
        // Encrypt credentials as JSON blob
        $encryptedCredentials = $this->encryption->encrypt(
            json_encode($credentials, JSON_THROW_ON_ERROR)
        );

        return $this->createToken($userId, $expiresIn, array_merge($extraClaims, [
            'credentials' => $encryptedCredentials,
        ]));
    }

    /**
     * Create an access token (short-lived, 24h).
     *
     * @param array{provider: string, url: string, key: string, email?: string} $credentials
     */
    public function createAccessToken(
        string $userId,
        array $credentials,
        string $role = 'user',
        bool $isBot = false,
    ): string {
        return $this->createTokenWithCredentials(
            $userId,
            $credentials,
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
     *
     * @param array{provider: string, url: string, key: string, email?: string} $credentials
     */
    public function createRefreshToken(
        string $userId,
        array $credentials,
        string $role = 'user',
        bool $isBot = false,
    ): string {
        return $this->createTokenWithCredentials(
            $userId,
            $credentials,
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
