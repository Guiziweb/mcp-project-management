<?php

declare(strict_types=1);

namespace App\Domain\Port;

use App\Domain\Model\TimeEntry;

interface TimeEntryPort
{
    /**
     * Whether this adapter requires an activity ID when logging time.
     */
    public function requiresActivity(): bool;

    /**
     * Log time on an issue.
     *
     * @param int                  $issueId  Issue identifier
     * @param int                  $seconds  Duration in seconds
     * @param string               $comment  Work description
     * @param \DateTimeInterface   $spentAt  When the work was done
     * @param array<string, mixed> $metadata Provider-specific metadata (e.g., activity_id for Redmine)
     */
    public function logTime(
        int $issueId,
        int $seconds,
        string $comment,
        \DateTimeInterface $spentAt,
        array $metadata = [],
    ): TimeEntry;

    /**
     * Get user's time entries within a date range.
     *
     * @param \DateTimeInterface $from   Start date
     * @param \DateTimeInterface $to     End date
     * @param int|null           $userId User ID to query (admin-only, null = current user)
     *
     * @return TimeEntry[]
     */
    public function getTimeEntries(
        \DateTimeInterface $from,
        \DateTimeInterface $to,
        ?int $userId = null,
    ): array;

    /**
     * Update a time entry.
     *
     * @param int         $timeEntryId Time entry ID to update
     * @param float|null  $hours       New hours (optional)
     * @param string|null $comment     New comment (optional)
     * @param int|null    $activityId  New activity ID (optional)
     * @param string|null $spentOn     New date in YYYY-MM-DD format (optional)
     */
    public function updateTimeEntry(
        int $timeEntryId,
        ?float $hours = null,
        ?string $comment = null,
        ?int $activityId = null,
        ?string $spentOn = null,
    ): void;

    /**
     * Delete a time entry.
     */
    public function deleteTimeEntry(int $timeEntryId): void;
}