<?php

declare(strict_types=1);

namespace App\Infrastructure\Redmine;

use Psr\Log\LoggerInterface;
use Redmine\Client\NativeCurlClient;
use Redmine\Http\HttpFactory;

/**
 * Client for Redmine API.
 */
class RedmineClient
{
    public function __construct(
        private readonly string $redmineUrl,
        private readonly string $redmineApiKey,
        private readonly LoggerInterface $logger,
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
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function getIssues(array $params = []): array
    {
        $client = $this->getClient();
        $api = $client->getApi('issue');

        return $api->list($params);
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

        return $api->show($issueId, $params);
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

        if (false === $result || !is_array($result) || !isset($result['user'])) {
            throw new \RuntimeException('Invalid response from getCurrentUser API');
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
        $client = $this->getClient();
        $api = $client->getApi('project');

        return $api->list(['membership' => true]);
    }

    /**
     * Get time entry activities.
     *
     * @return array<string, mixed>
     */
    public function getTimeEntryActivities(): array
    {
        $client = $this->getClient();
        $api = $client->getApi('time_entry_activity');

        return $api->list();
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

        $this->logger->info('LogTime called', ['data' => $data]);

        $client = $this->getClient();
        $api = $client->getApi('time_entry');

        try {
            $this->logger->info('Calling Redmine API create()');
            $result = $api->create($data);
            $this->logger->info('Redmine API returned', [
                'type' => gettype($result),
                'value' => $result instanceof \SimpleXMLElement ? $result->asXML() : $result,
            ]);

            if ($result instanceof \SimpleXMLElement) {
                if (isset($result->error) || isset($result->errors)) {
                    $errors = [];
                    foreach ($result->error ?? $result->errors->error ?? [] as $error) {
                        $errors[] = (string) $error;
                    }
                    throw new \RuntimeException('Redmine API error: '.implode(', ', $errors));
                }

                return ['success' => true];
            }

            if ('' === $result) {
                return ['success' => true];
            }

            throw new \RuntimeException('Unexpected response from Redmine API: '.gettype($result));
        } catch (\Exception $e) {
            $this->logger->error('Exception during logTime', ['exception' => $e->getMessage()]);
            throw new \RuntimeException('Failed to create time entry in Redmine: '.$e->getMessage(), 0, $e);
        }
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
        $client = $this->getClient();
        $api = $client->getApi('time_entry');

        return $api->all($params);
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

        return $api->show($attachmentId);
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

        return $api->download($attachmentId);
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

        $result = $api->update($timeEntryId, $data);

        if (false === $result) {
            throw new \RuntimeException('Failed to update time entry');
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

        return $api->remove($timeEntryId);
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

        $result = $api->update($issueId, $params);

        if (false === $result) {
            throw new \RuntimeException('Failed to add note to issue');
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
        $client = $this->getClient();

        $response = $client->request(HttpFactory::makeJsonRequest(
            'PUT',
            '/journals/'.$journalId.'.json',
            json_encode(['journal' => ['notes' => $notes]]) ?: ''
        ));

        if ($response->getStatusCode() >= 400) {
            throw new \RuntimeException('Failed to update journal: '.$response->getContent());
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
}
