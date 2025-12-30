<?php

declare(strict_types=1);

namespace App\Mcp\Infrastructure\Adapter;

use App\Mcp\Domain\Model\Issue;
use App\Mcp\Domain\Model\ProviderUser;
use App\Mcp\Domain\Model\TimeEntry;
use App\Mcp\Domain\Port\ActivityPort;
use App\Mcp\Domain\Port\AttachmentReadPort;
use App\Mcp\Domain\Port\IssueReadPort;
use App\Mcp\Domain\Port\IssueWritePort;
use App\Mcp\Domain\Port\ProjectPort;
use App\Mcp\Domain\Port\StatusPort;
use App\Mcp\Domain\Port\TimeEntryReadPort;
use App\Mcp\Domain\Port\TimeEntryWritePort;
use App\Mcp\Domain\Port\UserPort;
use App\Mcp\Infrastructure\Security\McpUser;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Proxy service providing ports for the current authenticated user.
 *
 * This service acts as a proxy that delegates to the user-specific adapter
 * based on the current authenticated user from Symfony Security.
 *
 * This allows MCP Tools to be registered once with dependency injection,
 * while still serving different users with their own credentials.
 *
 * Note: TimeEntryWritePort and ActivityPort are optional capabilities.
 * Use supportsTimeEntryWrite() and supportsActivity() to check availability.
 */
#[AsAlias(UserPort::class)]
#[AsAlias(ProjectPort::class)]
#[AsAlias(IssueReadPort::class)]
#[AsAlias(IssueWritePort::class)]
#[AsAlias(TimeEntryReadPort::class)]
#[AsAlias(TimeEntryWritePort::class)]
#[AsAlias(ActivityPort::class)]
#[AsAlias(StatusPort::class)]
#[AsAlias(AttachmentReadPort::class)]
final readonly class CurrentUserService implements UserPort, ProjectPort, IssueReadPort, IssueWritePort, TimeEntryReadPort, TimeEntryWritePort, ActivityPort, StatusPort, AttachmentReadPort
{
    public function __construct(
        private Security $security,
        private AdapterFactory $adapterFactory,
    ) {
    }

    /**
     * Get the adapter instance for the current user.
     */
    private function getCurrentAdapter(): UserPort&ProjectPort&IssueReadPort&TimeEntryReadPort&AttachmentReadPort
    {
        $user = $this->security->getUser();

        if (!$user instanceof McpUser) {
            throw new \RuntimeException('No authenticated user found. Ensure Symfony Security is configured properly.');
        }

        return $this->adapterFactory->createForUser($user->getCredential());
    }

    /**
     * Check if current adapter supports writing time entries.
     */
    public function supportsTimeEntryWrite(): bool
    {
        return $this->getCurrentAdapter() instanceof TimeEntryWritePort;
    }

    /**
     * Check if current adapter supports activities.
     */
    public function supportsActivity(): bool
    {
        return $this->getCurrentAdapter() instanceof ActivityPort;
    }

    /**
     * Check if current user is authorized to query another user's data.
     *
     * @throws AccessDeniedException if user is not authorized
     */
    private function assertCanQueryUser(?int $userId): void
    {
        if (null === $userId) {
            return; // Querying own data is always allowed
        }

        if (!$this->security->isGranted('ROLE_ADMIN')) {
            throw new AccessDeniedException('Access denied: Only administrators can specify a user_id parameter');
        }
    }

    public function getCurrentUser(): ProviderUser
    {
        return $this->getCurrentAdapter()->getCurrentUser();
    }

    public function getProjects(): array
    {
        return $this->getCurrentAdapter()->getProjects();
    }

    public function getIssues(?int $projectId = null, int $limit = 50, ?int $userId = null, string|int|null $statusId = null): array
    {
        $this->assertCanQueryUser($userId);

        return $this->getCurrentAdapter()->getIssues($projectId, $limit, $userId, $statusId);
    }

    public function getIssue(int $issueId): Issue
    {
        return $this->getCurrentAdapter()->getIssue($issueId);
    }

    public function getActivities(): array
    {
        $adapter = $this->getCurrentAdapter();

        if ($adapter instanceof ActivityPort) {
            return $adapter->getActivities();
        }

        return [];
    }

    public function requiresActivity(): bool
    {
        $adapter = $this->getCurrentAdapter();

        if ($adapter instanceof TimeEntryWritePort) {
            return $adapter->requiresActivity();
        }

        return false;
    }

    public function logTime(
        int $issueId,
        int $seconds,
        string $comment,
        \DateTimeInterface $spentAt,
        array $metadata = [],
    ): TimeEntry {
        $adapter = $this->getCurrentAdapter();

        if (!$adapter instanceof TimeEntryWritePort) {
            throw new \RuntimeException('This provider does not support logging time entries.');
        }

        return $adapter->logTime($issueId, $seconds, $comment, $spentAt, $metadata);
    }

    public function getTimeEntries(
        \DateTimeInterface $from,
        \DateTimeInterface $to,
        ?int $userId = null,
    ): array {
        $this->assertCanQueryUser($userId);

        return $this->getCurrentAdapter()->getTimeEntries($from, $to, $userId);
    }

    public function getAttachment(int $attachmentId): array
    {
        return $this->getCurrentAdapter()->getAttachment($attachmentId);
    }

    public function downloadAttachment(int $attachmentId): string
    {
        return $this->getCurrentAdapter()->downloadAttachment($attachmentId);
    }

    public function updateTimeEntry(
        int $timeEntryId,
        ?float $hours = null,
        ?string $comment = null,
        ?int $activityId = null,
        ?string $spentOn = null,
    ): void {
        $adapter = $this->getCurrentAdapter();

        if (!$adapter instanceof TimeEntryWritePort) {
            throw new \RuntimeException('This provider does not support updating time entries.');
        }

        $adapter->updateTimeEntry($timeEntryId, $hours, $comment, $activityId, $spentOn);
    }

    public function deleteTimeEntry(int $timeEntryId): void
    {
        $adapter = $this->getCurrentAdapter();

        if (!$adapter instanceof TimeEntryWritePort) {
            throw new \RuntimeException('This provider does not support deleting time entries.');
        }

        $adapter->deleteTimeEntry($timeEntryId);
    }

    public function getStatuses(): array
    {
        $adapter = $this->getCurrentAdapter();

        if ($adapter instanceof StatusPort) {
            return $adapter->getStatuses();
        }

        return [];
    }

    public function addComment(int $issueId, string $comment, bool $private = false): void
    {
        $adapter = $this->getCurrentAdapter();

        if (!$adapter instanceof IssueWritePort) {
            throw new \RuntimeException('This provider does not support writing issues.');
        }

        $adapter->addComment($issueId, $comment, $private);
    }

    public function updateComment(int $commentId, string $comment): void
    {
        $adapter = $this->getCurrentAdapter();

        if (!$adapter instanceof IssueWritePort) {
            throw new \RuntimeException('This provider does not support writing issues.');
        }

        $adapter->updateComment($commentId, $comment);
    }

    public function deleteComment(int $commentId): void
    {
        $adapter = $this->getCurrentAdapter();

        if (!$adapter instanceof IssueWritePort) {
            throw new \RuntimeException('This provider does not support writing issues.');
        }

        $adapter->deleteComment($commentId);
    }

    public function updateIssue(int $issueId, ?int $statusId = null): void
    {
        $adapter = $this->getCurrentAdapter();

        if (!$adapter instanceof IssueWritePort) {
            throw new \RuntimeException('This provider does not support writing issues.');
        }

        $adapter->updateIssue($issueId, $statusId);
    }
}
