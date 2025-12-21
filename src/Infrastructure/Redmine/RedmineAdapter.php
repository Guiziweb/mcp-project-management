<?php

declare(strict_types=1);

namespace App\Infrastructure\Redmine;

use App\Domain\Model\Activity;
use App\Domain\Model\Issue;
use App\Domain\Model\Project;
use App\Domain\Model\TimeEntry;
use App\Domain\Model\User;
use App\Domain\Port\ActivityPort;
use App\Domain\Port\AttachmentPort;
use App\Domain\Port\IssuePort;
use App\Domain\Port\ProjectPort;
use App\Domain\Port\TimeEntryPort;
use App\Domain\Port\UserPort;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * Redmine adapter implementing all ports.
 */
class RedmineAdapter implements UserPort, ProjectPort, IssuePort, TimeEntryPort, ActivityPort, AttachmentPort
{
    private ?User $currentUser = null;

    public function __construct(
        private readonly RedmineClient $redmineClient,
        private readonly DenormalizerInterface $serializer,
    ) {
    }

    public function getCurrentUser(): User
    {
        if (null === $this->currentUser) {
            $data = $this->redmineClient->getMyAccount();
            $this->currentUser = $this->serializer->denormalize(
                $data,
                User::class,
                null,
                ['provider' => 'redmine']
            );
        }

        return $this->currentUser;
    }

    public function getProjects(): array
    {
        $data = $this->redmineClient->getMyProjects();
        $projects = $data['projects'] ?? [];

        return array_map(
            fn (array $project) => $this->serializer->denormalize(
                $project,
                Project::class,
                null,
                ['provider' => 'redmine']
            ),
            $projects
        );
    }

    public function getIssues(?int $projectId = null, int $limit = 50, ?int $userId = null): array
    {
        $user = $this->getCurrentUser();

        $params = [
            'assigned_to_id' => $userId ?? $user->id,
            'limit' => $limit,
            'status_id' => 'open',
        ];

        if (null !== $projectId) {
            $params['project_id'] = $projectId;
        }

        $data = $this->redmineClient->getIssues($params);
        $issues = $data['issues'] ?? [];

        return array_map(
            fn (array $issue) => $this->serializer->denormalize(
                $issue,
                Issue::class,
                null,
                ['provider' => 'redmine']
            ),
            $issues
        );
    }

    public function getIssue(int $issueId): Issue
    {
        $data = $this->redmineClient->getIssue($issueId, [
            'include' => 'journals,attachments',
        ]);

        return $this->serializer->denormalize(
            $data,
            Issue::class,
            null,
            ['provider' => 'redmine']
        );
    }

    public function getActivities(): array
    {
        $data = $this->redmineClient->getTimeEntryActivities();
        $activities = $data['time_entry_activities'] ?? [];

        return array_map(
            fn (array $activity) => $this->serializer->denormalize(
                $activity,
                Activity::class,
                null,
                ['provider' => 'redmine']
            ),
            $activities
        );
    }

    public function requiresActivity(): bool
    {
        return true;
    }

    public function logTime(
        int $issueId,
        int $seconds,
        string $comment,
        \DateTimeInterface $spentAt,
        array $metadata = [],
    ): TimeEntry {
        $hours = $seconds / 3600;
        $activityId = $metadata['activity_id'] ?? null;

        if (null === $activityId) {
            throw new \InvalidArgumentException('Activity ID is required for Redmine');
        }

        $spentOn = $spentAt->format('Y-m-d');

        $this->redmineClient->logTime(
            $issueId,
            $hours,
            $comment,
            $activityId,
            $spentOn
        );

        // Redmine doesn't return the created time entry, so we reconstruct it
        $issue = $this->getIssue($issueId);
        $user = $this->getCurrentUser();

        $activities = $this->getActivities();
        $activity = array_values(array_filter(
            $activities,
            fn (Activity $a) => $a->id === $activityId
        ))[0] ?? null;

        return new TimeEntry(
            id: 0, // We don't have the actual ID
            issue: $issue,
            user: $user,
            seconds: $seconds,
            comment: $comment,
            spentAt: $spentAt,
            activity: $activity,
        );
    }

    public function getTimeEntries(
        \DateTimeInterface $from,
        \DateTimeInterface $to,
        ?int $userId = null,
    ): array {
        $user = $this->getCurrentUser();

        $params = [
            'user_id' => $userId ?? $user->id,
            'from' => $from->format('Y-m-d'),
            'to' => $to->format('Y-m-d'),
            'limit' => 1000,
        ];

        $data = $this->redmineClient->getTimeEntries($params);
        $entries = $data['time_entries'] ?? [];

        return array_map(
            fn (array $entry) => $this->serializer->denormalize(
                $entry,
                TimeEntry::class,
                null,
                ['provider' => 'redmine']
            ),
            $entries
        );
    }

    public function getAttachment(int $attachmentId): array
    {
        $data = $this->redmineClient->getAttachment($attachmentId);
        $attachment = $data['attachment'] ?? $data;

        return [
            'id' => (int) ($attachment['id'] ?? 0),
            'filename' => (string) ($attachment['filename'] ?? ''),
            'filesize' => (int) ($attachment['filesize'] ?? 0),
            'content_type' => (string) ($attachment['content_type'] ?? 'application/octet-stream'),
            'description' => isset($attachment['description']) ? (string) $attachment['description'] : null,
            'author' => isset($attachment['author']['name']) ? (string) $attachment['author']['name'] : null,
        ];
    }

    public function downloadAttachment(int $attachmentId): string
    {
        return $this->redmineClient->downloadAttachment($attachmentId);
    }

    public function updateTimeEntry(
        int $timeEntryId,
        ?float $hours = null,
        ?string $comment = null,
        ?int $activityId = null,
        ?string $spentOn = null,
    ): void {
        $this->redmineClient->updateTimeEntry($timeEntryId, $hours, $comment, $activityId, $spentOn);
    }

    public function deleteTimeEntry(int $timeEntryId): void
    {
        $this->redmineClient->deleteTimeEntry($timeEntryId);
    }
}
