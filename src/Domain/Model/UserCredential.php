<?php

declare(strict_types=1);

namespace App\Domain\Model;

/**
 * Represents a user's Redmine credentials.
 *
 * Stateless architecture: credentials are embedded in JWT tokens,
 * not stored in a database.
 */
final readonly class UserCredential
{
    public function __construct(
        public string $userId,
        public string $redmineUrl,
        public string $redmineApiKey,
        public string $role = 'user',
        public bool $isBot = false,
    ) {
    }

    public function isAdmin(): bool
    {
        return 'admin' === $this->role;
    }
}
