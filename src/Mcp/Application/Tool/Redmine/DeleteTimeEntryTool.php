<?php

declare(strict_types=1);

namespace App\Mcp\Application\Tool\Redmine;

use App\Mcp\Infrastructure\Adapter\AdapterHolder;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Autoconfigure(public: true)]
final class DeleteTimeEntryTool
{
    public function __construct(
        private readonly AdapterHolder $adapterHolder,
    ) {
    }

    /**
     * Delete a time entry.
     */
    #[McpTool(name: 'delete_time_entry')]
    public function deleteTimeEntry(
        #[Schema(minimum: 1, description: 'The time entry ID to delete')]
        int $time_entry_id,
    ): array {
        $adapter = $this->adapterHolder->getRedmine();
        $adapter->deleteTimeEntry($time_entry_id);

        return [
            'success' => true,
            'message' => sprintf('Time entry #%d deleted successfully.', $time_entry_id),
        ];
    }
}