<?php

declare(strict_types=1);

namespace App\Mcp\Domain\Port;

use App\Mcp\Domain\Model\TimeEntry;

/**
 * Write operations for time entries.
 */
interface TimeEntryWritePort
{
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
