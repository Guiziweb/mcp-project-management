<?php

declare(strict_types=1);

namespace App\Domain\User;

/**
 * Represents a user in the time tracking system.
 */
readonly class User
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
    ) {
    }
}
