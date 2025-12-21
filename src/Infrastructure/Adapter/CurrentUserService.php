<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapter;

use App\Domain\Port\PortCapabilities;
use App\Domain\Port\TimeTrackingPort;
use App\Infrastructure\Security\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Provides the TimeTrackingPort for the current authenticated user.
 *
 * This service acts as a proxy that delegates to the user-specific adapter
 * based on the current authenticated user from Symfony Security.
 *
 * This allows MCP Tools to be registered once with dependency injection,
 * while still serving different users with their own credentials (Redmine, Jira, etc.).
 */
final readonly class CurrentUserService implements TimeTrackingPort
{
    public function __construct(
        private Security $security,
        private AdapterFactory $adapterFactory,
    ) {
    }

    /**
     * Get the adapter instance for the current user.
     */
    private function getCurrentAdapter(): TimeTrackingPort
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            throw new \RuntimeException('No authenticated user found. Ensure Symfony Security is configured properly.');
        }

        return $this->adapterFactory->createForUser($user->getCredential());
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

        // Require ROLE_ADMIN for cross-user queries
        if (!$this->security->isGranted('ROLE_ADMIN')) {
            throw new AccessDeniedException('Access denied: Only administrators can specify a user_id parameter');
        }
    }

    public function getCapabilities(): PortCapabilities
    {
        return $this->getCurrentAdapter()->getCapabilities();
    }

    public function getCurrentUser(): \App\Domain\Model\User
    {
        return $this->getCurrentAdapter()->getCurrentUser();
    }

    public function getProjects(): array
    {
        return $this->getCurrentAdapter()->getProjects();
    }

    public function getIssues(?int $projectId = null, int $limit = 50, ?int $userId = null): array
    {
        $this->assertCanQueryUser($userId);

        return $this->getCurrentAdapter()->getIssues($projectId, $limit, $userId);
    }

    public function getIssue(int $issueId): \App\Domain\Model\Issue
    {
        return $this->getCurrentAdapter()->getIssue($issueId);
    }

    public function getActivities(): array
    {
        return $this->getCurrentAdapter()->getActivities();
    }

    public function logTime(
        int $issueId,
        int $seconds,
        string $comment,
        \DateTimeInterface $spentAt,
        array $metadata = [],
    ): \App\Domain\Model\TimeEntry {
        return $this->getCurrentAdapter()->logTime($issueId, $seconds, $comment, $spentAt, $metadata);
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
        $this->getCurrentAdapter()->updateTimeEntry($timeEntryId, $hours, $comment, $activityId, $spentOn);
    }

    public function deleteTimeEntry(int $timeEntryId): void
    {
        $this->getCurrentAdapter()->deleteTimeEntry($timeEntryId);
    }
}
