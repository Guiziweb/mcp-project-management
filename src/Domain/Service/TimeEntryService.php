<?php

declare(strict_types=1);

namespace App\Domain\Service;

use App\Domain\Model\TimeEntry;
use App\Domain\Port\TimeEntryPort;

/**
 * Domain service for time entry management with business rules.
 */
class TimeEntryService
{
    public function __construct(
        private readonly TimeEntryPort $adapter,
    ) {
    }

    /**
     * Log time on an issue with business validation.
     *
     * @param array<string, mixed> $metadata
     *
     * @throws \InvalidArgumentException If validation fails
     */
    public function logTime(
        int $issueId,
        float $hours,
        string $comment,
        \DateTimeInterface $spentAt,
        array $metadata = [],
    ): TimeEntry {
        // Validate hours
        if ($hours <= 0) {
            throw new \InvalidArgumentException('Hours must be greater than 0');
        }

        // Validate activity requirement
        if ($this->adapter->requiresActivity() && !isset($metadata['activity_id'])) {
            throw new \InvalidArgumentException('This provider requires an activity_id in metadata');
        }

        // Convert hours to seconds
        $seconds = (int) ($hours * 3600);

        return $this->adapter->logTime(
            issueId: $issueId,
            seconds: $seconds,
            comment: $comment,
            spentAt: $spentAt,
            metadata: $metadata
        );
    }

    /**
     * Get time entries within a date range.
     *
     * @param \DateTimeInterface $from   Start date
     * @param \DateTimeInterface $to     End date
     * @param int|null           $userId Redmine user ID to query (admin-only, null = current user)
     *
     * @return TimeEntry[]
     */
    public function getTimeEntries(
        \DateTimeInterface $from,
        \DateTimeInterface $to,
        ?int $userId = null,
    ): array {
        return $this->adapter->getTimeEntries($from, $to, $userId);
    }

    /**
     * Get aggregated time entries by day.
     *
     * @param \DateTimeInterface $from   Start date
     * @param \DateTimeInterface $to     End date
     * @param int|null           $userId Redmine user ID to query (admin-only, null = current user)
     *
     * @return array<string, array{date: string, hours: float, entries: TimeEntry[]}>
     */
    public function getEntriesByDay(
        \DateTimeInterface $from,
        \DateTimeInterface $to,
        ?int $userId = null,
    ): array {
        $entries = $this->adapter->getTimeEntries($from, $to, $userId);

        $byDay = [];
        foreach ($entries as $entry) {
            $dateKey = $entry->spentAt->format('Y-m-d');

            if (!isset($byDay[$dateKey])) {
                $byDay[$dateKey] = [
                    'date' => $dateKey,
                    'hours' => 0.0,
                    'entries' => [],
                ];
            }

            $byDay[$dateKey]['hours'] += $entry->getHours();
            $byDay[$dateKey]['entries'][] = $entry;
        }

        ksort($byDay);

        return $byDay;
    }

    /**
     * Get aggregated time entries by project.
     *
     * @param \DateTimeInterface $from   Start date
     * @param \DateTimeInterface $to     End date
     * @param int|null           $userId Redmine user ID to query (admin-only, null = current user)
     *
     * @return array<int, array{project_id: int, project_name: string, hours: float, entries: TimeEntry[]}>
     */
    public function getEntriesByProject(
        \DateTimeInterface $from,
        \DateTimeInterface $to,
        ?int $userId = null,
    ): array {
        $entries = $this->adapter->getTimeEntries($from, $to, $userId);

        $byProject = [];
        foreach ($entries as $entry) {
            $projectId = $entry->issue->project->id;

            if (!isset($byProject[$projectId])) {
                $byProject[$projectId] = [
                    'project_id' => $projectId,
                    'project_name' => $entry->issue->project->name,
                    'hours' => 0.0,
                    'entries' => [],
                ];
            }

            $byProject[$projectId]['hours'] += $entry->getHours();
            $byProject[$projectId]['entries'][] = $entry;
        }

        return $byProject;
    }
}
