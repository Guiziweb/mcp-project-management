<?php

declare(strict_types=1);

namespace App\Infrastructure\Jira;

use App\Domain\Model\Issue;
use App\Domain\Model\Project;
use App\Domain\Model\TimeEntry;
use App\Domain\Model\User;
use App\Domain\Port\AttachmentPort;
use App\Domain\Port\IssuePort;
use App\Domain\Port\ProjectPort;
use App\Domain\Port\TimeEntryPort;
use App\Domain\Port\UserPort;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * Jira Cloud adapter (no ActivityPort - Jira doesn't have activities).
 */
class JiraAdapter implements UserPort, ProjectPort, IssuePort, TimeEntryPort, AttachmentPort
{
    private ?User $currentUser = null;
    private ?string $currentUserAccountId = null;

    public function __construct(
        private readonly JiraClient $jiraClient,
        private readonly DenormalizerInterface $serializer,
    ) {
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
            $jqlParts[] = sprintf('project = %d', $projectId);
        }

        // Note: Jira uses accountId for user filtering, not integer IDs
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
        $data = $this->jiraClient->getIssue((string) $issueId);

        return $this->serializer->denormalize(
            $data,
            Issue::class,
            null,
            ['provider' => 'jira']
        );
    }

    public function requiresActivity(): bool
    {
        return false;
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
                if (!$this->isWorklogInRange($worklog, $from, $to)) {
                    continue;
                }

                if ($worklog['author']['accountId'] !== $this->currentUserAccountId) {
                    continue;
                }

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
        throw new \RuntimeException('updateTimeEntry requires issue key for Jira. Use issue-specific worklog update.');
    }

    public function deleteTimeEntry(int $timeEntryId): void
    {
        throw new \RuntimeException('deleteTimeEntry requires issue key for Jira. Use issue-specific worklog delete.');
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
}