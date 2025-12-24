<?php

declare(strict_types=1);

namespace App\Infrastructure\Jira;

use JiraCloud\ADF\AtlassianDocumentFormat;
use JiraCloud\Configuration\ArrayConfiguration;
use JiraCloud\Issue\IssueService;
use JiraCloud\Issue\Worklog;
use JiraCloud\Project\ProjectService;
use JiraCloud\User\UserService;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

/**
 * Client for Jira Cloud API.
 *
 * Created dynamically by AdapterFactory with user credentials.
 */
#[Autoconfigure(autowire: false)]
class JiraClient
{
    private ArrayConfiguration $configuration;

    public function __construct(
        private readonly string $jiraHost,
        private readonly string $jiraUser,
        private readonly string $jiraApiToken,
    ) {
        $this->configuration = new ArrayConfiguration([
            'jiraHost' => $this->jiraHost,
            'jiraUser' => $this->jiraUser,
            'personalAccessToken' => $this->jiraApiToken,
            'jiraLogEnabled' => false,
        ]);
    }

    /**
     * Get current authenticated user.
     *
     * @return array<string, mixed>
     */
    public function getMyself(): array
    {
        $userService = new UserService($this->configuration);
        $user = $userService->getMyself();

        return [
            'accountId' => $user->accountId,
            'displayName' => $user->displayName,
            'emailAddress' => $user->emailAddress ?? null,
            'avatarUrls' => $user->avatarUrls ?? null,
            'active' => $user->active ?? true,
            'timeZone' => $user->timeZone ?? null,
        ];
    }

    /**
     * Get all projects.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getProjects(): array
    {
        $projectService = new ProjectService($this->configuration);
        $projects = $projectService->getAllProjects();

        $result = [];
        foreach ($projects as $project) {
            $result[] = [
                'id' => (int) $project->id,
                'key' => $project->key,
                'name' => $project->name,
            ];
        }

        return $result;
    }

    /**
     * Search issues using JQL.
     *
     * @return array<int, array<string, mixed>>
     */
    public function searchIssues(string $jql, int $maxResults = 50): array
    {
        $issueService = new IssueService($this->configuration);

        // Use the search method (second param is nextPageToken as string)
        $searchResult = $issueService->search($jql, '', $maxResults, ['summary', 'status', 'project', 'assignee']);

        $result = [];
        foreach ($searchResult->issues as $issue) {
            $result[] = [
                'id' => (int) $issue->id,
                'key' => $issue->key,
                'summary' => $issue->fields->summary ?? '',
                'status' => $issue->fields->status->name ?? null,
                'project' => [
                    'id' => (int) ($issue->fields->project->id ?? 0),
                    'key' => $issue->fields->project->key ?? '',
                    'name' => $issue->fields->project->name ?? '',
                ],
            ];
        }

        return $result;
    }

    /**
     * Get a specific issue with full details.
     *
     * @return array<string, mixed>
     */
    public function getIssue(string $issueIdOrKey): array
    {
        $issueService = new IssueService($this->configuration);
        $issue = $issueService->get($issueIdOrKey, ['expand' => 'renderedFields']);

        // Map attachments to raw array format for normalizer
        $attachments = [];
        if (isset($issue->fields->attachment)) {
            foreach ($issue->fields->attachment as $attachment) {
                $attachments[] = [
                    'id' => (int) $attachment->id,
                    'filename' => $attachment->filename ?? '',
                    'size' => $attachment->size ?? 0,
                    'mimeType' => $attachment->mimeType ?? 'application/octet-stream',
                    'content' => $attachment->content ?? null,
                    'author' => isset($attachment->author) ? [
                        'displayName' => $attachment->author->displayName ?? null,
                        'accountId' => $attachment->author->accountId ?? null,
                    ] : null,
                    'created' => $attachment->created ?? null,
                ];
            }
        }

        // Map comments to raw array format for normalizer
        $comments = [];
        if (isset($issue->renderedFields['comment']['comments'])) {
            foreach ($issue->renderedFields['comment']['comments'] as $comment) {
                $comments[] = [
                    'id' => (int) ($comment['id'] ?? 0),
                    'renderedBody' => $comment['renderedBody'] ?? '',
                    'author' => $comment['author'] ?? null,
                    'created' => $comment['created'] ?? null,
                ];
            }
        }

        // Use renderedFields for HTML description (server-side ADF conversion)
        $description = $issue->renderedFields['description'] ?? '';

        return [
            'id' => (int) $issue->id,
            'key' => $issue->key,
            'summary' => $issue->fields->summary ?? '',
            'description' => $description,
            'status' => $issue->fields->status->name ?? null,
            'project' => [
                'id' => (int) ($issue->fields->project->id ?? 0),
                'key' => $issue->fields->project->key ?? '',
                'name' => $issue->fields->project->name ?? '',
            ],
            'assignee' => isset($issue->fields->assignee) ? [
                'displayName' => $issue->fields->assignee->displayName ?? null,
                'accountId' => $issue->fields->assignee->accountId ?? null,
            ] : null,
            'issuetype' => isset($issue->fields->issuetype) ? [
                'name' => $issue->fields->issuetype->name ?? null,
            ] : null,
            'priority' => isset($issue->fields->priority) ? [
                'name' => $issue->fields->priority->name,
            ] : null,
            'attachments' => $attachments,
            'comments' => $comments,
        ];
    }

    /**
     * Log time on an issue (create worklog).
     *
     * @return array<string, mixed>
     */
    public function logTime(
        string $issueIdOrKey,
        int $timeSpentSeconds,
        string $comment,
        ?\DateTimeInterface $started = null,
    ): array {
        $issueService = new IssueService($this->configuration);

        $worklog = new Worklog();
        $worklog->setTimeSpentSeconds($timeSpentSeconds);
        $worklog->setComment(new AtlassianDocumentFormat($comment));

        if (null !== $started) {
            $worklog->setStarted($started->format('Y-m-d\TH:i:s.000O'));
        }

        $result = $issueService->addWorklog($issueIdOrKey, $worklog);

        return [
            'id' => (int) $result->id,
            'timeSpentSeconds' => $result->timeSpentSeconds,
            'started' => $result->started ?? null,
        ];
    }

    /**
     * Get worklogs for an issue.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getWorklogs(string $issueIdOrKey): array
    {
        $issueService = new IssueService($this->configuration);
        $paginatedWorklog = $issueService->getWorklog($issueIdOrKey);

        $result = [];
        foreach ($paginatedWorklog->worklogs as $worklog) {
            $result[] = [
                'id' => (int) $worklog->id,
                'author' => [
                    'accountId' => $worklog->author['accountId'] ?? null,
                    'displayName' => $worklog->author['displayName'] ?? null,
                ],
                'timeSpentSeconds' => $worklog->timeSpentSeconds,
                'comment' => $worklog->comment ?? '',
                'started' => $worklog->started ?? null,
            ];
        }

        return $result;
    }

    /**
     * Update a worklog.
     */
    public function updateWorklog(
        string $issueIdOrKey,
        int $worklogId,
        ?int $timeSpentSeconds = null,
        ?string $comment = null,
    ): void {
        $issueService = new IssueService($this->configuration);

        $worklog = new Worklog();

        if (null !== $timeSpentSeconds) {
            $worklog->setTimeSpentSeconds($timeSpentSeconds);
        }
        if (null !== $comment) {
            $worklog->setComment(new AtlassianDocumentFormat($comment));
        }

        $issueService->editWorklog($issueIdOrKey, $worklog, $worklogId);
    }

    /**
     * Delete a worklog.
     */
    public function deleteWorklog(string $issueIdOrKey, int $worklogId): void
    {
        $issueService = new IssueService($this->configuration);
        $issueService->deleteWorklog($issueIdOrKey, $worklogId);
    }

    /**
     * Get attachment metadata.
     *
     * @return array<string, mixed>
     */
    public function getAttachment(int $attachmentId): array
    {
        $attachmentService = new JiraAttachmentClient($this->configuration);
        $attachment = $attachmentService->get($attachmentId);

        return [
            'id' => (int) $attachment->id,
            'filename' => $attachment->filename ?? '',
            'size' => $attachment->size ?? 0,
            'mimeType' => $attachment->mimeType ?? 'application/octet-stream',
            'content' => $attachment->content ?? null,
            'author' => isset($attachment->author) ? [
                'displayName' => $attachment->author->displayName ?? null,
                'accountId' => $attachment->author->accountId ?? null,
            ] : null,
            'created' => $attachment->created ?? null,
        ];
    }

    /**
     * Download attachment content.
     *
     * @return string Binary content
     */
    public function downloadAttachment(int $attachmentId): string
    {
        $attachmentService = new JiraAttachmentClient($this->configuration);

        return $attachmentService->downloadContent($attachmentId);
    }
}
