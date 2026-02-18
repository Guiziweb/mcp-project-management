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
final class DeleteTimeEntryTool implements RedmineTool
{
    public function __construct(
        private readonly AdapterHolder $adapterHolder,
    ) {
    }

    /**
     * Delete a time entry.
     *
     * @return array<string, mixed>
     */
    #[McpTool(name: 'delete_time_entry')]
    public function deleteTimeEntry(
        #[Schema(description: 'The time entry ID to delete')]
        mixed $time_entry_id,
    ): array {
        try {
            $time_entry_id = (int) $time_entry_id;
            $adapter = $this->adapterHolder->getRedmine();
            $adapter->deleteTimeEntry($time_entry_id);

            return [
                'success' => true,
                'message' => sprintf('Time entry #%d deleted successfully.', $time_entry_id),
            ];
        } catch (RedmineApiException $e) {
            throw new ToolCallException($e->getMessage());
        }
    }
}
