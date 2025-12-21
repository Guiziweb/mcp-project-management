<?php

declare(strict_types=1);

namespace App\Domain\Port;

use App\Domain\Model\Activity;
use App\Domain\Model\Issue;
use App\Domain\Model\Project;
use App\Domain\Model\TimeEntry;
use App\Domain\Model\User;

/**
 * Port for time tracking adapters (Redmine, Jira, GitLab, etc.).
 */
interface TimeTrackingPort
{
    /**
     * Get adapter capabilities.
     */
    public function getCapabilities(): PortCapabilities;

    /**
     * Get current authenticated user.
     */
    public function getCurrentUser(): User;

    /**
     * Get user's projects.
     *
     * @return Project[]
     */
    public function getProjects(): array;

    /**
     * Get user's issues, optionally filtered by project.
     *
     * @param int|null $projectId Project ID to filter by (optional)
     * @param int      $limit     Maximum number of issues to return
     * @param int|null $userId    Redmine user ID to query (admin-only, null = current user)
     *
     * @return Issue[]
     */
    public function getIssues(?int $projectId = null, int $limit = 50, ?int $userId = null): array;

    /**
     * Get a specific issue by ID.
     */
    public function getIssue(int $issueId): Issue;

    /**
     * Get available activities (only for providers that support activities).
     *
     * @return Activity[]
     */
    public function getActivities(): array;

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
     * @param int|null           $userId Redmine user ID to query (admin-only, null = current user)
     *
     * @return TimeEntry[]
     */
    public function getTimeEntries(
        \DateTimeInterface $from,
        \DateTimeInterface $to,
        ?int $userId = null,
    ): array;

    /**
     * Get attachment metadata.
     *
     * @return array{id: int, filename: string, filesize: int, content_type: string, description: ?string, author: ?string}
     */
    public function getAttachment(int $attachmentId): array;

    /**
     * Download attachment content.
     *
     * @return string Binary content of the attachment
     */
    public function downloadAttachment(int $attachmentId): string;

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
