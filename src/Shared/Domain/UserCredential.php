<?php

declare(strict_types=1);

namespace App\Shared\Domain;

/**
 * Represents a user's Redmine credentials.
 *
 * Stateless architecture: credentials are embedded in Bearer tokens,
 * not stored in a database.
 */
final readonly class UserCredential
{
    public const PROVIDER_REDMINE = 'redmine';

    /**
     * @param array<string, mixed> $orgConfig       Organization-level config (url)
     * @param array<string, mixed> $userCredentials User-level credentials (api_key)
     */
    public function __construct(
        public string $userId,
        public string $provider = self::PROVIDER_REDMINE,
        public array $orgConfig = [],
        public array $userCredentials = [],
        public string $role = 'user',
    ) {
    }

    /**
     * Get the Redmine URL (from org config).
     */
    public function getUrl(): ?string
    {
        return $this->orgConfig['url'] ?? null;
    }

    /**
     * Get the API key (from user credentials).
     */
    public function getApiKey(): ?string
    {
        return $this->userCredentials['api_key'] ?? null;
    }

    public function isAdmin(): bool
    {
        return 'admin' === $this->role;
    }
}
