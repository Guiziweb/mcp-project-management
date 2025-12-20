<?php

declare(strict_types=1);

namespace App\Infrastructure\Jira;

use App\Domain\Model\Attachment;
use App\Domain\Model\Issue;
use App\Domain\Model\Project;
use App\Domain\Model\TimeEntry;
use App\Domain\Model\User;
use App\Domain\Provider\ProviderCapabilities;
use App\Domain\Provider\TimeTrackingProviderInterface;

/**
 * Jira Cloud implementation of the time tracking provider.
 */
class JiraProvider implements TimeTrackingProviderInterface
{
    private ?User $currentUser = null;
    private ?string $currentUserAccountId = null;

    public function __construct(
        private readonly JiraService $jiraService,
    ) {
    }

    public function getCapabilities(): ProviderCapabilities
    {
        return new ProviderCapabilities(
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
            $data = $this->jiraService->getMyself();
            $this->currentUserAccountId = $data['accountId'];
            $this->currentUser = new User(
                id: $this->accountIdToInt($data['accountId']),
                name: $data['displayName'],
                email: $data['emailAddress'] ?? '',
            );
        }

        return $this->currentUser;
    }

    public function getProjects(): array
    {
        $projectsData = $this->jiraService->getProjects();

        return array_map(
            fn (array $project) => new Project(
                id: $project['id'],
                name: sprintf('%s (%s)', $project['name'], $project['key']),
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

        $issuesData = $this->jiraService->searchIssues($jql, $limit);

        return array_map(
            fn (array $issue) => $this->mapIssue($issue),
            $issuesData
        );
    }

    public function getIssue(int $issueId): Issue
    {
        // Jira typically uses issue keys like "KAN-1", but we have an int ID
        // The API accepts both key and numeric ID
        $data = $this->jiraService->getIssue((string) $issueId);

        return $this->mapIssue($data);
    }

    public function getActivities(): array
    {
        // Jira doesn't have the concept of "activities" like Redmine
        // Return empty array as per ProviderCapabilities::requiresActivity = false
        return [];
    }

    public function logTime(
        int $issueId,
        int $seconds,
        string $comment,
        \DateTimeInterface $spentAt,
        array $metadata = [],
    ): TimeEntry {
        $result = $this->jiraService->logTime(
            (string) $issueId,
            $seconds,
            $comment,
            $spentAt,
        );

        $issue = $this->getIssue($issueId);
        $user = $this->getCurrentUser();

        return new TimeEntry(
            id: $result['id'],
            issue: $issue,
            user: $user,
            seconds: $seconds,
            comment: $comment,
            spentAt: $spentAt,
            activity: null,
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

        $issuesData = $this->jiraService->searchIssues($jql, 100);

        $timeEntries = [];
        $user = $this->getCurrentUser();

        foreach ($issuesData as $issueData) {
            $issue = $this->mapIssue($issueData);
            $worklogs = $this->jiraService->getWorklogs($issueData['key']);

            foreach ($worklogs as $worklog) {
                // Filter by current user and date range
                if (!$this->isWorklogInRange($worklog, $from, $to)) {
                    continue;
                }

                // Filter by author if it's the current user
                if ($worklog['author']['accountId'] !== $this->currentUserAccountId) {
                    continue;
                }

                $spentAt = new \DateTime($worklog['started']);

                $timeEntries[] = new TimeEntry(
                    id: $worklog['id'],
                    issue: $issue,
                    user: $user,
                    seconds: $worklog['timeSpentSeconds'],
                    comment: $this->extractCommentText($worklog['comment']),
                    spentAt: $spentAt,
                    activity: null,
                );
            }
        }

        return $timeEntries;
    }

    public function getAttachment(int $attachmentId): array
    {
        return $this->jiraService->getAttachment($attachmentId);
    }

    public function downloadAttachment(int $attachmentId): string
    {
        return $this->jiraService->downloadAttachment($attachmentId);
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
     * Convert Jira accountId string to integer for compatibility with our models.
     */
    private function accountIdToInt(string $accountId): int
    {
        // Use crc32 to get a stable integer from the accountId string
        // This is a workaround since our User model expects int id
        return abs(crc32($accountId));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function mapIssue(array $data): Issue
    {
        $projectName = $data['project']['name'] ?? '';
        $projectKey = $data['project']['key'] ?? '';

        $attachments = [];
        if (isset($data['attachments']) && is_array($data['attachments'])) {
            foreach ($data['attachments'] as $attachmentData) {
                $attachments[] = $this->mapAttachment($attachmentData);
            }
        }

        return new Issue(
            id: $data['id'],
            title: $data['summary'] ?? '',
            description: $data['description'] ?? '',
            project: new Project(
                id: $data['project']['id'] ?? 0,
                name: $projectKey ? sprintf('%s (%s)', $projectName, $projectKey) : $projectName,
            ),
            status: $data['status'] ?? 'Unknown',
            attachments: $attachments,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function mapAttachment(array $data): Attachment
    {
        $createdOn = null;
        if (isset($data['created_on'])) {
            try {
                $createdOn = new \DateTimeImmutable($data['created_on']);
            } catch (\Exception) {
                // Ignore invalid dates
            }
        }

        return new Attachment(
            id: $data['id'],
            filename: $data['filename'] ?? '',
            filesize: $data['filesize'] ?? 0,
            contentType: $data['content_type'] ?? 'application/octet-stream',
            description: null,
            contentUrl: $data['content_url'] ?? null,
            author: $data['author'] ?? null,
            createdOn: $createdOn,
        );
    }

    /**
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

    /**
     * Extract plain text from Jira's Atlassian Document Format comment.
     */
    private function extractCommentText(mixed $comment): string
    {
        if (is_string($comment)) {
            return $comment;
        }

        if (is_array($comment) || is_object($comment)) {
            // ADF format - extract text from content
            $comment = (array) $comment;
            if (isset($comment['content'])) {
                return $this->extractTextFromAdf($comment['content']);
            }
        }

        return '';
    }

    /**
     * @param array<int, mixed> $content
     */
    private function extractTextFromAdf(array $content): string
    {
        $text = '';

        foreach ($content as $block) {
            $block = (array) $block;
            if (isset($block['content'])) {
                foreach ($block['content'] as $inline) {
                    $inline = (array) $inline;
                    if (isset($inline['text'])) {
                        $text .= $inline['text'];
                    }
                }
                $text .= "\n";
            }
        }

        return trim($text);
    }
}
