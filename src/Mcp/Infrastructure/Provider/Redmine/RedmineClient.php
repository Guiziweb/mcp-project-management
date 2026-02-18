<?php

declare(strict_types=1);

namespace App\Mcp\Infrastructure\Provider\Redmine;

use App\Mcp\Infrastructure\Provider\Redmine\Exception\AccessDeniedException;
use App\Mcp\Infrastructure\Provider\Redmine\Exception\InvalidCredentialsException;
use App\Mcp\Infrastructure\Provider\Redmine\Exception\NotFoundException;
use App\Mcp\Infrastructure\Provider\Redmine\Exception\RedmineApiException;
use Redmine\Client\NativeCurlClient;
use Redmine\Exception\UnexpectedResponseException;
use Redmine\Http\HttpFactory;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

/**
 * Client for Redmine API.
 *
 * Created dynamically by AdapterFactory with user credentials.
 */
#[Autoconfigure(autowire: false)]
class RedmineClient implements RedmineClientInterface
{
    public function __construct(
        private readonly string $redmineUrl,
        private readonly string $redmineApiKey,
    ) {
    }

    private function getClient(): NativeCurlClient
    {
        return new NativeCurlClient(
            $this->redmineUrl,
            $this->redmineApiKey
        );
    }

    /**
     * Convert HTTP error responses to specific exceptions.
     */
    private function handleUnexpectedResponse(UnexpectedResponseException $e): never
    {
        $statusCode = $e->getResponse()?->getStatusCode();

        $this->throwForStatusCode($statusCode, 'Redmine API error: '.$e->getMessage(), $e);
    }

    /**
     * Throw appropriate exception based on HTTP status code.
     */
    private function throwForStatusCode(?int $statusCode, string $defaultMessage = 'Redmine API error', ?\Throwable $previous = null): never
    {
        match ($statusCode) {
            401 => throw new InvalidCredentialsException(),
            403 => throw new AccessDeniedException(),
            404 => throw new NotFoundException(),
            default => throw new RedmineApiException($defaultMessage, $statusCode ?? 0, $previous),
        };
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function getIssues(array $params = []): array
    {
        try {
            $client = $this->getClient();
            $api = $client->getApi('issue');

            return $api->list($params);
        } catch (UnexpectedResponseException $e) {
            $this->handleUnexpectedResponse($e);
        }
    }

    /**
     * Get a specific issue by ID.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function getIssue(int $issueId, array $params = []): array
    {
        $client = $this->getClient();
        $api = $client->getApi('issue');
        $result = $api->show($issueId, $params);

        $statusCode = $api->getLastResponse()->getStatusCode();
        if ($statusCode >= 400) {
            $this->throwForStatusCode($statusCode);
        }

        return $result;
    }

    /**
     * Get current authenticated user account.
     *
     * @return array<string, mixed>
     */
    public function getMyAccount(): array
    {
        $client = $this->getClient();
        $api = $client->getApi('user');
        $result = $api->getCurrentUser();

        $statusCode = $api->getLastResponse()->getStatusCode();
        if ($statusCode >= 400) {
            $this->throwForStatusCode($statusCode);
        }

        return $result;
    }

    /**
     * Get projects where the current user is a member.
     *
     * @return array<string, mixed>
     */
    public function getMyProjects(): array
    {
        try {
            $client = $this->getClient();
            $api = $client->getApi('project');

            return $api->list(['membership' => true]);
        } catch (UnexpectedResponseException $e) {
            $this->handleUnexpectedResponse($e);
        }
    }

    /**
     * Get issue statuses.
     *
     * @return array<string, mixed>
     */
    public function getIssueStatuses(): array
    {
        try {
            $client = $this->getClient();
            $api = $client->getApi('issue_status');

            return $api->list();
        } catch (UnexpectedResponseException $e) {
            $this->handleUnexpectedResponse($e);
        }
    }

    /**
     * Log time entry for an issue.
     *
     * @param int         $issueId    Issue ID
     * @param float       $hours      Hours to log
     * @param string      $comment    Comment/description
     * @param int         $activityId Activity type ID
     * @param string|null $spentOn    Date in YYYY-MM-DD format (defaults to today)
     *
     * @return array<string, mixed>
     */
    public function logTime(int $issueId, float $hours, string $comment, int $activityId, ?string $spentOn = null): array
    {
        $data = [
            'issue_id' => $issueId,
            'hours' => $hours,
            'comments' => $comment,
            'spent_on' => $spentOn ?? date('Y-m-d'),
            'activity_id' => $activityId,
        ];

        $client = $this->getClient();
        $api = $client->getApi('time_entry');
        $api->create($data);

        $statusCode = $api->getLastResponse()->getStatusCode();
        if ($statusCode >= 400) {
            $this->throwForStatusCode($statusCode);
        }

        return ['success' => true];
    }

    /**
     * Get time entries with optional filters.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function getTimeEntries(array $params = []): array
    {
        try {
            $client = $this->getClient();
            $api = $client->getApi('time_entry');

            return $api->all($params);
        } catch (UnexpectedResponseException $e) {
            $this->handleUnexpectedResponse($e);
        }
    }

    /**
     * Get attachment details.
     *
     * @return array<string, mixed>
     */
    public function getAttachment(int $attachmentId): array
    {
        $client = $this->getClient();
        $api = $client->getApi('attachment');
        $result = $api->show($attachmentId);

        $statusCode = $api->getLastResponse()->getStatusCode();
        if ($statusCode >= 400) {
            $this->throwForStatusCode($statusCode);
        }

        return $result;
    }

    /**
     * Download attachment content.
     *
     * @return string Binary content of the attachment
     */
    public function downloadAttachment(int $attachmentId): string
    {
        $client = $this->getClient();
        $api = $client->getApi('attachment');
        $result = $api->download($attachmentId);

        $statusCode = $api->getLastResponse()->getStatusCode();
        if ($statusCode >= 400) {
            $this->throwForStatusCode($statusCode);
        }

        return $result;
    }

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
    ): void {
        $data = [];

        if (null !== $hours) {
            $data['hours'] = $hours;
        }
        if (null !== $comment) {
            $data['comments'] = $comment;
        }
        if (null !== $activityId) {
            $data['activity_id'] = $activityId;
        }
        if (null !== $spentOn) {
            $data['spent_on'] = $spentOn;
        }

        if (empty($data)) {
            throw new \InvalidArgumentException('At least one field must be provided to update');
        }

        $client = $this->getClient();
        $api = $client->getApi('time_entry');
        $api->update($timeEntryId, $data);

        $statusCode = $api->getLastResponse()->getStatusCode();
        if ($statusCode >= 400) {
            $this->throwForStatusCode($statusCode);
        }
    }

    /**
     * Delete a time entry.
     *
     * @return string Empty string on success
     */
    public function deleteTimeEntry(int $timeEntryId): string
    {
        $client = $this->getClient();
        $api = $client->getApi('time_entry');
        $result = $api->remove($timeEntryId);

        $statusCode = $api->getLastResponse()->getStatusCode();
        if ($statusCode >= 400) {
            $this->throwForStatusCode($statusCode);
        }

        return $result;
    }

    /**
     * Add a note (comment) to an issue.
     *
     * @param int    $issueId Issue ID
     * @param string $notes   The note/comment content
     * @param bool   $private Whether the note is private (visible only to roles with "View private notes" permission)
     */
    public function addIssueNote(int $issueId, string $notes, bool $private = false): void
    {
        $client = $this->getClient();
        $api = $client->getApi('issue');

        $params = [
            'notes' => $notes,
            'private_notes' => $private,
        ];

        $api->update($issueId, $params);

        $statusCode = $api->getLastResponse()->getStatusCode();
        if ($statusCode >= 400) {
            $this->throwForStatusCode($statusCode);
        }
    }

    /**
     * Update a journal (comment) on an issue.
     *
     * @param int    $journalId Journal/comment ID
     * @param string $notes     The new note content
     */
    public function updateJournal(int $journalId, string $notes): void
    {
        try {
            $client = $this->getClient();

            $response = $client->request(HttpFactory::makeJsonRequest(
                'PUT',
                '/journals/'.$journalId.'.json',
                json_encode(['journal' => ['notes' => $notes]]) ?: ''
            ));

            if ($response->getStatusCode() >= 400) {
                $this->throwForStatusCode($response->getStatusCode(), 'Failed to update journal: '.$response->getContent());
            }
        } catch (UnexpectedResponseException $e) {
            $this->handleUnexpectedResponse($e);
        }
    }

    /**
     * Delete a journal comment from an issue.
     *
     * Note: Redmine doesn't have a DELETE endpoint for journals.
     * Setting notes to empty string deletes the comment.
     * If the journal has field changes (journal_details), the journal
     * entry remains but without the comment text.
     *
     * @param int $journalId Journal/comment ID
     */
    public function deleteJournal(int $journalId): void
    {
        $this->updateJournal($journalId, '');
    }

    /**
     * Update an issue.
     *
     * @param int      $issueId      Issue ID
     * @param int|null $statusId     New status ID (optional)
     * @param int|null $doneRatio    Percentage of completion 0-100 (optional)
     * @param int|null $assignedToId User ID to assign the issue to (optional)
     */
    public function updateIssue(int $issueId, ?int $statusId = null, ?int $doneRatio = null, ?int $assignedToId = null): void
    {
        $params = [];

        if (null !== $statusId) {
            $params['status_id'] = $statusId;
        }

        if (null !== $doneRatio) {
            $params['done_ratio'] = $doneRatio;
        }

        if (null !== $assignedToId) {
            $params['assigned_to_id'] = $assignedToId;
        }

        if (empty($params)) {
            throw new \InvalidArgumentException('At least one field must be provided to update');
        }

        $client = $this->getClient();
        $api = $client->getApi('issue');
        $api->update($issueId, $params);

        $statusCode = $api->getLastResponse()->getStatusCode();
        if ($statusCode >= 400) {
            $this->throwForStatusCode($statusCode);
        }
    }

    /**
     * Get project members (memberships).
     *
     * @param int $projectId Project ID
     *
     * @return array<string, mixed>
     */
    public function getProjectMembers(int $projectId): array
    {
        try {
            $client = $this->getClient();
            $api = $client->getApi('membership');

            return $api->listByProject($projectId, ['limit' => 100]);
        } catch (UnexpectedResponseException $e) {
            $this->handleUnexpectedResponse($e);
        }
    }

    /**
     * Get time entry activities for a specific project.
     *
     * @param int $projectId Project ID
     *
     * @return array<string, mixed>
     */
    public function getProjectActivities(int $projectId): array
    {
        $client = $this->getClient();
        $api = $client->getApi('project');
        $result = $api->show($projectId, ['include' => ['time_entry_activities']]);

        $statusCode = $api->getLastResponse()->getStatusCode();
        if ($statusCode >= 400) {
            $this->throwForStatusCode($statusCode);
        }

        return $result;
    }

    /**
     * Get all wiki pages for a project.
     *
     * @param int $projectId Project ID
     *
     * @return array<string, mixed>
     */
    public function getWikiPages(int $projectId): array
    {
        try {
            $client = $this->getClient();
            $api = $client->getApi('wiki');

            return $api->listByProject($projectId);
        } catch (UnexpectedResponseException $e) {
            $this->handleUnexpectedResponse($e);
        }
    }

    /**
     * Get a specific wiki page by title.
     *
     * @param int    $projectId Project ID
     * @param string $pageTitle Wiki page title
     *
     * @return array<string, mixed>
     */
    public function getWikiPage(int $projectId, string $pageTitle): array
    {
        $client = $this->getClient();
        $api = $client->getApi('wiki');
        $result = $api->show($projectId, $pageTitle);

        $statusCode = $api->getLastResponse()->getStatusCode();
        if ($statusCode >= 400) {
            $this->throwForStatusCode($statusCode);
        }

        return $result;
    }
}
