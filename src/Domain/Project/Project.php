<?php

declare(strict_types=1);

namespace App\Domain\Project;

/**
 * Represents a project in the time tracking system.
 */
readonly class Project
{
    public function __construct(
        public int $id,
        public string $name,
        public ?Project $parent = null,
    ) {
    }
}
