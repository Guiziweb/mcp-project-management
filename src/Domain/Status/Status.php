<?php

declare(strict_types=1);

namespace App\Domain\Status;

/**
 * Represents an issue status.
 */
readonly class Status
{
    public function __construct(
        public int $id,
        public string $name,
        public bool $isClosed = false,
    ) {
    }
}