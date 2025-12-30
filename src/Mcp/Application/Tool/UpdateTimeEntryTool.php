<?php

declare(strict_types=1);

namespace App\Mcp\Application\Tool;

use App\Mcp\Domain\Port\TimeEntryWritePort;
use Mcp\Capability\Attribute\McpTool;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Autoconfigure(public: true)]
final class UpdateTimeEntryTool
{
    public function __construct(
        private readonly TimeEntryWritePort $adapter,
    ) {
    }

    /**
     * Update an existing time entry.
     *
     * Allows modifying hours, comment, activity, or date of a time entry.
     * At least one field must be provided.
     *
     * @param int         $time_entry_id The time entry ID to update
     * @param float|null  $hours         New hours (optional)
     * @param string|null $comment       New comment (optional)
     * @param mixed       $activity_id   New activity ID (optional, use list_activities to get valid IDs)
     * @param string|null $spent_on      New date in YYYY-MM-DD format (optional)
     *
     * @return array<string, mixed>
     */
    #[McpTool(name: 'update_time_entry')]
    public function updateTimeEntry(
        int $time_entry_id,
        ?float $hours = null,
        ?string $comment = null,
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

            // Validate at least one field is provided
            if (null === $hours && null === $comment && null === $activity_id && null === $spent_on) {
                return [
                    'success' => false,
                    'error' => 'At least one field (hours, comment, activity_id, or spent_on) must be provided to update.',
                ];
            }

            $this->adapter->updateTimeEntry(
                timeEntryId: $time_entry_id,
                hours: $hours,
                comment: $comment,
                activityId: $activity_id,
                spentOn: $spent_on,
            );

            return [
                'success' => true,
                'message' => sprintf('Time entry #%d updated successfully.', $time_entry_id),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
