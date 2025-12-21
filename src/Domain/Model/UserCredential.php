<?php

declare(strict_types=1);

namespace App\Domain\Model;

/**
 * Represents a user's time tracking provider credentials.
 *
 * Stateless architecture: credentials are embedded in JWT tokens,
 * not stored in a database.
 *
 * Supports multiple providers: Redmine, Jira, etc.
 */
final readonly class UserCredential
{
    public const PROVIDER_REDMINE = 'redmine';
    public const PROVIDER_JIRA = 'jira';
    public const PROVIDER_MONDAY = 'monday';

    public function __construct(
        public string $userId,
        public string $provider,
        public string $url,
        public string $apiKey,
        public ?string $email = null, // Required for Jira
        public string $role = 'user',
        public bool $isBot = false,
    ) {
    }

    public function isAdmin(): bool
    {
        return 'admin' === $this->role;
    }

    public function isRedmine(): bool
    {
        return self::PROVIDER_REDMINE === $this->provider;
    }

    public function isJira(): bool
    {
        return self::PROVIDER_JIRA === $this->provider;
    }

    public function isMonday(): bool
    {
        return self::PROVIDER_MONDAY === $this->provider;
    }
}
