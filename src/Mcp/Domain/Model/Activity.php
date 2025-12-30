<?php

declare(strict_types=1);

namespace App\Mcp\Domain\Model;

/**
 * Represents a time entry activity/category.
 * Only used by providers that require activity classification (e.g., Redmine).
 */
readonly class Activity
{
    public function __construct(
        public int $id,
        public string $name,
        public bool $isDefault = false,
        public bool $active = true,
    ) {
    }
}
