<?php

declare(strict_types=1);

namespace App\Infrastructure\Redmine;

use App\Api\RedmineService;
use App\Domain\Model\Activity;
use App\Domain\Model\Issue;
use App\Domain\Model\Project;
use App\Domain\Model\TimeEntry;
use App\Domain\Model\User;
use App\Domain\Provider\ProviderCapabilities;
use App\Domain\Provider\TimeTrackingProviderInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * Redmine implementation of the time tracking provider.
 */
class RedmineProvider implements TimeTrackingProviderInterface
{
    private ?User $currentUser = null;

    public function __construct(
        private readonly RedmineService $redmineService,
        private readonly DenormalizerInterface $serializer,
    ) {
    }

    public function getCapabilities(): ProviderCapabilities
    {
        return new ProviderCapabilities(
            name: 'Redmine',
            requiresActivity: true,
            supportsProjectHierarchy: true,
            supportsTags: false,
            maxDailyHours: 24,
        );
    }

    public function getCurrentUser(): User
    {
        if (null === $this->currentUser) {
            $data = $this->redmineService->getMyAccount();
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
        $data = $this->redmineService->getMyProjects();
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

        $data = $this->redmineService->getIssues($params);
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
        $data = $this->redmineService->getIssue($issueId, [
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
        $data = $this->redmineService->getTimeEntryActivities();
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

    public function logTime(
        int $issueId,
        int $seconds,
        string $comment,
        \DateTimeInterface $spentAt,
        array $metadata = [],
    ): TimeEntry {
        $hours = $seconds / 3600;
        $activityId = $metadata['activity_id'] ?? null;

        if (null === $activityId && $this->getCapabilities()->requiresActivity) {
            throw new \InvalidArgumentException('Activity ID is required for Redmine');
        }

        $spentOn = $spentAt->format('Y-m-d');

        $result = $this->redmineService->logTime(
            $issueId,
            $hours,
            $comment,
            $activityId,
            $spentOn
        );

        // Redmine doesn't return the created time entry, so we reconstruct it
        $issue = $this->getIssue($issueId);
        $user = $this->getCurrentUser();

        $activity = null;
        if (null !== $activityId) {
            $activities = $this->getActivities();
            $activity = array_values(array_filter(
                $activities,
                fn (Activity $a) => $a->id === $activityId
            ))[0] ?? null;
        }

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

        $data = $this->redmineService->getTimeEntries($params);
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

    /**
     * Get attachment metadata.
     *
     * @return array{id: int, filename: string, filesize: int, content_type: string, description: ?string, author: ?string}
     */
    public function getAttachment(int $attachmentId): array
    {
        $data = $this->redmineService->getAttachment($attachmentId);
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

    /**
     * Download attachment content.
     */
    public function downloadAttachment(int $attachmentId): string
    {
        return $this->redmineService->downloadAttachment($attachmentId);
    }
}
