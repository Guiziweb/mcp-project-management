<?php

declare(strict_types=1);

namespace App\Infrastructure\Jira;

use App\Domain\Model\Issue;
use App\Domain\Model\Project;
use App\Domain\Model\TimeEntry;
use App\Domain\Model\User;
use App\Domain\Port\PortCapabilities;
use App\Domain\Port\TimeTrackingPort;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * Jira Cloud adapter for the time tracking port.
 */
class JiraAdapter implements TimeTrackingPort
{
    private ?User $currentUser = null;
    private ?string $currentUserAccountId = null;

    public function __construct(
        private readonly JiraClient $jiraClient,
        private readonly DenormalizerInterface $serializer,
    ) {
    }

    public function getCapabilities(): PortCapabilities
    {
        return new PortCapabilities(
            name: 'Jira Cloud',
            requiresActivity: false, // Jira doesn't have activities like Redmine
            supportsProjectHierarchy: false,
            supportsTags: true, // Jira has labels
            maxDailyHours: 24,
        );
    }

    public function getCurrentUser(): User
    {
        if (null === $this->currentUser) {
            $data = $this->jiraClient->getMyself();
            $this->currentUserAccountId = $data['accountId'];
            /** @var User $user */
            $user = $this->serializer->denormalize(
                $data,
                User::class,
                null,
                ['provider' => 'jira']
            );
            $this->currentUser = $user;
        }

        return $this->currentUser;
    }

    public function getProjects(): array
    {
        $projectsData = $this->jiraClient->getProjects();

        return array_map(
            fn (array $project) => $this->serializer->denormalize(
                $project,
                Project::class,
                null,
                ['provider' => 'jira']
            ),
            $projectsData
        );
    }

    public function getIssues(?int $projectId = null, int $limit = 50, ?int $userId = null): array
    {
        // Build JQL query
        $jqlParts = [];

        if (null !== $projectId) {
            // We need to get project key from ID - for now, assume projectId is used directly
            $jqlParts[] = sprintf('project = %d', $projectId);
        }

        // Note: Jira uses accountId for user filtering, not integer IDs
        // For now, filter by current user's assigned issues
        $jqlParts[] = 'assignee = currentUser()';
        $jqlParts[] = 'status != Done';

        $jql = implode(' AND ', $jqlParts).' ORDER BY updated DESC';

        $issuesData = $this->jiraClient->searchIssues($jql, $limit);

        return array_map(
            fn (array $issue) => $this->serializer->denormalize(
                $issue,
                Issue::class,
                null,
                ['provider' => 'jira']
            ),
            $issuesData
        );
    }

    public function getIssue(int $issueId): Issue
    {
        // Jira typically uses issue keys like "KAN-1", but we have an int ID
        // The API accepts both key and numeric ID
        $data = $this->jiraClient->getIssue((string) $issueId);

        return $this->serializer->denormalize(
            $data,
            Issue::class,
            null,
            ['provider' => 'jira']
        );
    }

    public function getActivities(): array
    {
        // Jira doesn't have the concept of "activities" like Redmine
        // Return empty array as per PortCapabilities::requiresActivity = false
        return [];
    }

    public function logTime(
        int $issueId,
        int $seconds,
        string $comment,
        \DateTimeInterface $spentAt,
        array $metadata = [],
    ): TimeEntry {
        $result = $this->jiraClient->logTime(
            (string) $issueId,
            $seconds,
            $comment,
            $spentAt,
        );

        $issueData = $this->jiraClient->getIssue((string) $issueId);

        return $this->serializer->denormalize(
            array_merge($result, ['issue' => $issueData]),
            TimeEntry::class,
            null,
            ['provider' => 'jira']
        );
    }

    public function getTimeEntries(
        \DateTimeInterface $from,
        \DateTimeInterface $to,
        ?int $userId = null,
    ): array {
        // Jira doesn't have a direct API to get all worklogs for a user
        // We need to search for issues with worklogs in the date range, then get worklogs
        $fromDate = $from->format('Y-m-d');
        $toDate = $to->format('Y-m-d');

        $jql = sprintf(
            'worklogDate >= "%s" AND worklogDate <= "%s" AND worklogAuthor = currentUser() ORDER BY updated DESC',
            $fromDate,
            $toDate
        );

        $issuesData = $this->jiraClient->searchIssues($jql, 100);

        $timeEntries = [];

        foreach ($issuesData as $issueData) {
            $worklogs = $this->jiraClient->getWorklogs($issueData['key']);

            foreach ($worklogs as $worklog) {
                // Filter by current user and date range
                if (!$this->isWorklogInRange($worklog, $from, $to)) {
                    continue;
                }

                // Filter by author if it's the current user
                if ($worklog['author']['accountId'] !== $this->currentUserAccountId) {
                    continue;
                }

                // Add issue data and key for the normalizer
                $worklog['issue'] = $issueData;
                $worklog['issueKey'] = $issueData['key'];

                $timeEntries[] = $this->serializer->denormalize(
                    $worklog,
                    TimeEntry::class,
                    null,
                    ['provider' => 'jira']
                );
            }
        }

        return $timeEntries;
    }

    public function getAttachment(int $attachmentId): array
    {
        $data = $this->jiraClient->getAttachment($attachmentId);

        // Transform to interface-expected format
        return [
            'id' => $data['id'],
            'filename' => $data['filename'],
            'filesize' => $data['size'],
            'content_type' => $data['mimeType'],
            'description' => null,
            'author' => is_array($data['author']) ? ($data['author']['displayName'] ?? null) : $data['author'],
        ];
    }

    public function downloadAttachment(int $attachmentId): string
    {
        return $this->jiraClient->downloadAttachment($attachmentId);
    }

    public function updateTimeEntry(
        int $timeEntryId,
        ?float $hours = null,
        ?string $comment = null,
        ?int $activityId = null,
        ?string $spentOn = null,
    ): void {
        // For Jira, we need the issue key to update a worklog
        // This is a limitation - we'd need to store/lookup the issue key
        // For now, throw an exception explaining the limitation
        throw new \RuntimeException('updateTimeEntry requires issue key for Jira. Use issue-specific worklog update.');
    }

    public function deleteTimeEntry(int $timeEntryId): void
    {
        // Same limitation as updateTimeEntry
        throw new \RuntimeException('deleteTimeEntry requires issue key for Jira. Use issue-specific worklog delete.');
    }

    /**
     * Check if a worklog falls within the specified date range.
     *
     * @param array<string, mixed> $worklog
     */
    private function isWorklogInRange(array $worklog, \DateTimeInterface $from, \DateTimeInterface $to): bool
    {
        if (!isset($worklog['started'])) {
            return false;
        }

        $started = new \DateTime($worklog['started']);

        return $started >= $from && $started <= $to;
    }
}
