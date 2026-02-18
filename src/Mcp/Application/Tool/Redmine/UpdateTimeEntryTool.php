<?php

declare(strict_types=1);

namespace App\Mcp\Application\Tool\Redmine;

use App\Mcp\Application\Tool\RedmineTool;
use App\Mcp\Infrastructure\Adapter\AdapterHolder;
use App\Mcp\Infrastructure\Provider\Redmine\Exception\RedmineApiException;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Exception\ToolCallException;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Autoconfigure(public: true)]
final class UpdateTimeEntryTool implements RedmineTool
{
    public function __construct(
        private readonly AdapterHolder $adapterHolder,
    ) {
    }

    /**
     * Update an existing time entry.
     *
     * @return array<string, mixed>
     */
    #[McpTool(name: 'update_time_entry')]
    public function updateTimeEntry(
        #[Schema(description: 'The time entry ID to update')]
        mixed $time_entry_id,
        #[Schema(description: 'New hours (optional, must be > 0)')]
        mixed $hours = null,
        #[Schema(description: 'New comment (optional)')]
        ?string $comment = null,
        #[Schema(description: 'New activity ID (optional, read provider://projects/{project_id}/activities to get valid IDs)')]
        mixed $activity_id = null,
        #[Schema(pattern: '^\d{4}-\d{2}-\d{2}$', description: 'New date in YYYY-MM-DD format (optional)')]
        ?string $spent_on = null,
    ): array {
        try {
            // Cast to proper types for API compatibility (Cursor sends strings)
            $time_entry_id = (int) $time_entry_id;
            $hours = null !== $hours && '' !== $hours ? (float) $hours : null;
            $activity_id = null !== $activity_id && '' !== $activity_id ? (int) $activity_id : null;

            $adapter = $this->adapterHolder->getRedmine();

            // Validate activity_id format
            if (null !== $activity_id && $activity_id <= 0) {
                throw new ToolCallException('activity_id must be a positive integer.');
            }

            // Validate at least one field is provided
            if (null === $hours && null === $comment && null === $activity_id && null === $spent_on) {
                throw new ToolCallException('At least one field (hours, comment, activity_id, or spent_on) must be provided to update.');
            }

            $adapter->updateTimeEntry(
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
        } catch (RedmineApiException $e) {
            throw new ToolCallException($e->getMessage());
        }
    }
}
