<?php

declare(strict_types=1);

namespace App\Mcp\Domain\Model;

/**
 * Represents a project member (user assigned to a project).
 */
readonly class ProjectMember
{
    /**
     * @param array<string> $roles
     */
    public function __construct(
        public int $id,
        public string $name,
        public array $roles = [],
    ) {
    }
}
