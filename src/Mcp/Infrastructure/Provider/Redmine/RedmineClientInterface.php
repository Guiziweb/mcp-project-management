<?php

declare(strict_types=1);

namespace App\Mcp\Infrastructure\Provider\Redmine;

interface RedmineClientInterface
{
    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function getIssues(array $params = []): array;

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function getIssue(int $issueId, array $params = []): array;

    /**
     * @return array<string, mixed>
     */
    public function getMyAccount(): array;

    /**
     * @return array<string, mixed>
     */
    public function getMyProjects(): array;

    /**
     * @return array<string, mixed>
     */
    public function getIssueStatuses(): array;

    /**
     * @return array<string, mixed>
     */
    public function logTime(int $issueId, float $hours, string $comment, int $activityId, ?string $spentOn = null): array;

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function getTimeEntries(array $params = []): array;

    /**
     * @return array<string, mixed>
     */
    public function getAttachment(int $attachmentId): array;

    public function downloadAttachment(int $attachmentId): string;

    public function updateTimeEntry(
        int $timeEntryId,
        ?float $hours = null,
        ?string $comment = null,
        ?int $activityId = null,
        ?string $spentOn = null,
    ): void;

    public function deleteTimeEntry(int $timeEntryId): string;

    public function addIssueNote(int $issueId, string $notes, bool $private = false): void;

    public function updateJournal(int $journalId, string $notes): void;

    public function deleteJournal(int $journalId): void;

    public function updateIssue(int $issueId, ?int $statusId = null, ?int $doneRatio = null, ?int $assignedToId = null): void;

    /**
     * @return array<string, mixed>
     */
    public function getProjectMembers(int $projectId): array;

    /**
     * @return array<string, mixed>
     */
    public function getProjectActivities(int $projectId): array;

    /**
     * Get all wiki pages for a project.
     *
     * @return array<string, mixed>
     */
    public function getWikiPages(int $projectId): array;

    /**
     * Get a specific wiki page by title.
     *
     * @return array<string, mixed>
     */
    public function getWikiPage(int $projectId, string $pageTitle): array;
}
