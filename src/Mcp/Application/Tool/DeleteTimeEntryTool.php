<?php

declare(strict_types=1);

namespace App\Mcp\Application\Tool;

use App\Mcp\Domain\Port\TimeEntryWritePort;
use Mcp\Capability\Attribute\McpTool;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Autoconfigure(public: true)]
final class DeleteTimeEntryTool
{
    public function __construct(
        private readonly TimeEntryWritePort $adapter,
    ) {
    }

    /**
     * Delete a time entry.
     *
     * Permanently removes a time entry. This action cannot be undone.
     *
     * @param int $time_entry_id The time entry ID to delete
     *
     * @return array<string, mixed>
     */
    #[McpTool(name: 'delete_time_entry')]
    public function deleteTimeEntry(
        int $time_entry_id,
    ): array {
        try {
            $this->adapter->deleteTimeEntry($time_entry_id);

            return [
                'success' => true,
                'message' => sprintf('Time entry #%d deleted successfully.', $time_entry_id),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
