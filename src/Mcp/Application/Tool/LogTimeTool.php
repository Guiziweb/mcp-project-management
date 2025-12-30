<?php

declare(strict_types=1);

namespace App\Mcp\Application\Tool;

use App\Mcp\Domain\Port\TimeEntryWritePort;
use App\Mcp\Domain\Service\TimeEntryService;
use Mcp\Capability\Attribute\McpTool;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Autoconfigure(public: true)]
final class LogTimeTool
{
    public function __construct(
        private readonly TimeEntryService $timeEntryService,
        private readonly TimeEntryWritePort $adapter,
    ) {
    }

    /**
     * Log time spent on an issue.
     *
     * Can specify the date to log time on (defaults to today).
     * Some providers require activity_id (use list_activities tool first to get valid IDs).
     *
     * @param int         $issue_id    The issue ID to log time against
     * @param float       $hours       Number of hours to log
     * @param string      $comment     Comments for the time entry
     * @param mixed       $activity_id Activity ID (required for some providers like Redmine, use list_activities tool to get valid IDs)
     * @param string|null $spent_on    Date in YYYY-MM-DD format (e.g., "2025-10-07"). Defaults to today if not specified.
     *
     * @return array<string, mixed>
     */
    #[McpTool(name: 'log_time')]
    public function logTime(
        int $issue_id,
        float $hours,
        string $comment,
        mixed $activity_id = null,
        ?string $spent_on = null,
    ): array {
        try {
            // Normalize activity_id (some clients send strings)
            $activity_id = null !== $activity_id ? (int) $activity_id : null;

            // Validate activity_id format
            if (null !== $activity_id && $activity_id <= 0) {
                return [
                    'success' => false,
                    'error' => 'activity_id must be a positive integer.',
                ];
            }

            // Validate activity requirement
            if ($this->adapter->requiresActivity() && null === $activity_id) {
                return [
                    'success' => false,
                    'error' => 'This provider requires an activity_id. Please use list_activities tool to get valid IDs.',
                ];
            }

            // Parse date
            $spentAt = $spent_on ? new \DateTime($spent_on) : new \DateTime('today');

            // Prepare metadata
            $metadata = [];
            if (null !== $activity_id) {
                $metadata['activity_id'] = $activity_id;
            }

            // Log time through service
            $timeEntry = $this->timeEntryService->logTime(
                issueId: $issue_id,
                hours: $hours,
                comment: $comment,
                spentAt: $spentAt,
                metadata: $metadata
            );

            // Format response
            return [
                'success' => true,
                'time_entry' => [
                    'id' => $timeEntry->id,
                    'issue_id' => $timeEntry->issue->id,
                    'hours' => $timeEntry->getHours(),
                    'comment' => $timeEntry->comment,
                    'spent_on' => $timeEntry->spentAt->format('Y-m-d'),
                    'activity' => $timeEntry->activity ? $timeEntry->activity->name : null,
                ],
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
