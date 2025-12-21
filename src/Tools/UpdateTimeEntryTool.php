<?php

declare(strict_types=1);

namespace App\Tools;

use App\Domain\Port\TimeEntryPort;
use Mcp\Capability\Attribute\McpTool;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Autoconfigure(public: true)]
final class UpdateTimeEntryTool
{
    public function __construct(
        private readonly TimeEntryPort $adapter,
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
     * @param int|null    $activity_id   New activity ID (optional, use list_activities to get valid IDs)
     * @param string|null $spent_on      New date in YYYY-MM-DD format (optional)
     *
     * @return array<string, mixed>
     */
    #[McpTool(name: 'update_time_entry')]
    public function updateTimeEntry(
        int $time_entry_id,
        ?float $hours = null,
        ?string $comment = null,
        ?int $activity_id = null,
        ?string $spent_on = null,
    ): array {
        try {
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
