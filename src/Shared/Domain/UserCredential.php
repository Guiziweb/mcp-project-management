<?php

declare(strict_types=1);

namespace App\Shared\Domain;

/**
 * Represents a user's time tracking provider credentials.
 *
 * Stateless architecture: credentials are embedded in Bearer tokens,
 * not stored in a database.
 *
 * Supports multiple providers: Redmine, Jira, Monday, etc.
 * Credentials are split between org-level (url) and user-level (api_key, email).
 */
final readonly class UserCredential
{
    public const PROVIDER_REDMINE = 'redmine';
    public const PROVIDER_JIRA = 'jira';
    public const PROVIDER_MONDAY = 'monday';

    /**
     * @param array<string, mixed> $orgConfig       Organization-level config (url, etc.)
     * @param array<string, mixed> $userCredentials User-level credentials (api_key, email, etc.)
     */
    public function __construct(
        public string $userId,
        public string $provider,
        public array $orgConfig = [],
        public array $userCredentials = [],
        public string $role = 'user',
    ) {
    }

    /**
     * Get the provider URL (from org config).
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

    /**
     * Get the email (from user credentials, for Jira).
     */
    public function getEmail(): ?string
    {
        return $this->userCredentials['email'] ?? null;
    }

    /**
     * Get any org config value.
     */
    public function getOrgConfigValue(string $key): mixed
    {
        return $this->orgConfig[$key] ?? null;
    }

    /**
     * Get any user credential value.
     */
    public function getUserCredentialValue(string $key): mixed
    {
        return $this->userCredentials[$key] ?? null;
    }

    public function isAdmin(): bool
    {
        return 'admin' === $this->role;
    }
}
