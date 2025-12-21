<?php

declare(strict_types=1);

namespace App\Infrastructure\Monday;

use App\Domain\Model\Issue;
use App\Domain\Model\Project;
use App\Domain\Model\TimeEntry;
use App\Domain\Model\User;
use App\Domain\Port\AttachmentPort;
use App\Domain\Port\IssuePort;
use App\Domain\Port\ProjectPort;
use App\Domain\Port\TimeEntryReadPort;
use App\Domain\Port\UserPort;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * Monday.com adapter.
 *
 * Only implements TimeEntryReadPort (not Write) because Monday API
 * doesn't support logging time programmatically.
 */
class MondayAdapter implements UserPort, ProjectPort, IssuePort, TimeEntryReadPort, AttachmentPort
{
    private ?User $currentUser = null;
    private ?string $currentUserId = null;

    public function __construct(
        private readonly MondayClient $client,
        private readonly DenormalizerInterface $serializer,
    ) {
    }

    public function getCurrentUser(): User
    {
        if (null === $this->currentUser) {
            $data = $this->client->getMe();
            /** @var int|string|null $id */
            $id = $data['id'] ?? null;
            $this->currentUserId = null !== $id ? (string) $id : null;
            $user = $this->serializer->denormalize(
                $data,
                User::class,
                null,
                ['provider' => 'monday']
            );
            \assert($user instanceof User);
            $this->currentUser = $user;
        }

        return $this->currentUser;
    }

    /**
     * @return array<Project>
     */
    public function getProjects(): array
    {
        $boards = $this->client->getBoards();

        return array_map(
            function (array $board): Project {
                $project = $this->serializer->denormalize(
                    $board,
                    Project::class,
                    null,
                    ['provider' => 'monday']
                );
                \assert($project instanceof Project);

                return $project;
            },
            $boards
        );
    }

    /**
     * @return array<Issue>
     */
    public function getIssues(?int $projectId = null, int $limit = 50, ?int $userId = null): array
    {
        // Ensure we have the current user ID for filtering
        $this->getCurrentUser();
        $filterUserId = null !== $userId ? (string) $userId : $this->currentUserId;

        if (null !== $projectId) {
            $items = $this->client->getBoardItems((string) $projectId, $limit);
        } else {
            $items = $this->client->getAllItems($limit);
        }

        // Filter by assignee if needed
        $filteredItems = [];
        foreach ($items as $item) {
            if ($this->isAssignedTo($item, $filterUserId)) {
                $filteredItems[] = $item;
            }
        }

        return array_map(
            function (array $item): Issue {
                $issue = $this->serializer->denormalize(
                    $item,
                    Issue::class,
                    null,
                    ['provider' => 'monday']
                );
                \assert($issue instanceof Issue);

                return $issue;
            },
            array_slice($filteredItems, 0, $limit)
        );
    }

    public function getIssue(int $issueId): Issue
    {
        $item = $this->client->getItem((string) $issueId);

        $issue = $this->serializer->denormalize(
            $item,
            Issue::class,
            null,
            ['provider' => 'monday']
        );
        \assert($issue instanceof Issue);

        return $issue;
    }

    /**
     * Check if an item is assigned to a specific user.
     *
     * @param array<string, mixed> $item
     */
    private function isAssignedTo(array $item, ?string $userId): bool
    {
        if (null === $userId) {
            return true;
        }

        $columnValues = $item['column_values'] ?? [];
        if (!\is_array($columnValues)) {
            return false;
        }

        foreach ($columnValues as $column) {
            if (!\is_array($column)) {
                continue;
            }

            $columnId = $column['id'] ?? null;
            if (\is_string($columnId) && \in_array($columnId, ['task_owner', 'people', 'people1'], true)) {
                $rawValue = $column['value'] ?? null;
                if (!\is_string($rawValue)) {
                    continue;
                }

                $value = json_decode($rawValue, true);
                if (!\is_array($value)) {
                    continue;
                }

                $personsAndTeams = $value['personsAndTeams'] ?? [];
                if (!\is_array($personsAndTeams)) {
                    continue;
                }

                foreach ($personsAndTeams as $person) {
                    if (!\is_array($person)) {
                        continue;
                    }
                    /** @var int|string|null $personId */
                    $personId = $person['id'] ?? null;
                    if ((string) $personId === $userId) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * @return array{id: int, filename: string, filesize: int, content_type: string, description: string|null, author: string|null}
     */
    public function getAttachment(int $attachmentId): array
    {
        $asset = $this->client->getAsset((string) $attachmentId);

        $extension = '';
        if (isset($asset['file_extension']) && \is_string($asset['file_extension'])) {
            $extension = $asset['file_extension'];
        }

        $authorName = null;
        $uploadedBy = $asset['uploaded_by'] ?? null;
        if (\is_array($uploadedBy) && isset($uploadedBy['name']) && \is_string($uploadedBy['name'])) {
            $authorName = $uploadedBy['name'];
        }

        $assetId = $asset['id'] ?? null;
        $assetName = $asset['name'] ?? null;
        $fileSize = $asset['file_size'] ?? null;

        return [
            'id' => \is_int($assetId) || \is_string($assetId) ? (int) $assetId : 0,
            'filename' => \is_string($assetName) ? $assetName : '',
            'filesize' => \is_int($fileSize) || \is_string($fileSize) ? (int) $fileSize : 0,
            'content_type' => $this->guessContentType($extension),
            'description' => null,
            'author' => $authorName,
        ];
    }

    public function downloadAttachment(int $attachmentId): string
    {
        $asset = $this->client->getAsset((string) $attachmentId);
        $publicUrl = $asset['public_url'] ?? null;

        if (!\is_string($publicUrl) || '' === $publicUrl) {
            throw new \RuntimeException('Asset has no public URL');
        }

        return $this->client->downloadAsset($publicUrl);
    }

    private function guessContentType(string $extension): string
    {
        return match (strtolower($extension)) {
            'pdf' => 'application/pdf',
            'doc', 'docx' => 'application/msword',
            'xls', 'xlsx' => 'application/vnd.ms-excel',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'txt' => 'text/plain',
            'csv' => 'text/csv',
            'mp4' => 'video/mp4',
            default => 'application/octet-stream',
        };
    }

    /**
     * @return array<TimeEntry>
     */
    public function getTimeEntries(
        \DateTimeInterface $from,
        \DateTimeInterface $to,
        ?int $userId = null,
    ): array {
        $user = $this->getCurrentUser();
        $items = $this->client->getItemsWithTimeTracking();

        return array_map(
            function (array $item) use ($user): TimeEntry {
                $entry = $this->serializer->denormalize(
                    $item,
                    TimeEntry::class,
                    null,
                    ['provider' => 'monday', 'current_user' => $user]
                );
                \assert($entry instanceof TimeEntry);

                return $entry;
            },
            $items
        );
    }
}
