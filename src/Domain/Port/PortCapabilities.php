<?php

declare(strict_types=1);

namespace App\Domain\Port;

/**
 * Describes the capabilities and requirements of a time tracking adapter.
 */
readonly class PortCapabilities
{
    public function __construct(
        public string $name,
        public bool $requiresActivity = false,
        public bool $supportsProjectHierarchy = false,
        public bool $supportsTags = false,
        public int $maxDailyHours = 24,
    ) {
    }
}
