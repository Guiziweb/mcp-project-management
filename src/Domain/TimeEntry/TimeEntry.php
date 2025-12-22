<?php

declare(strict_types=1);

namespace App\Domain\TimeEntry;

use App\Domain\Activity\Activity;
use App\Domain\Issue\Issue;
use App\Domain\User\User;

/**
 * Represents a time entry logged against an issue.
 */
readonly class TimeEntry
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public int $id,
        public Issue $issue,
        public User $user,
        public int $seconds,
        public string $comment,
        public \DateTimeInterface $spentAt,
        public ?Activity $activity = null,
        public array $metadata = [],
    ) {
    }

    /**
     * Get duration in hours (decimal).
     */
    public function getHours(): float
    {
        return round($this->seconds / 3600, 2);
    }
}
