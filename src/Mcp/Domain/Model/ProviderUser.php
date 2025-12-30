<?php

declare(strict_types=1);

namespace App\Mcp\Domain\Model;

/**
 * Represents a user from the external provider (Redmine, Jira, Monday).
 * This is NOT the authenticated Symfony user - see McpUser for that.
 */
readonly class ProviderUser
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
    ) {
    }
}
