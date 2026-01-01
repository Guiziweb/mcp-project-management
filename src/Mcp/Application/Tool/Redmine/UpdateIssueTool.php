<?php

declare(strict_types=1);

namespace App\Mcp\Application\Tool\Redmine;

use App\Mcp\Domain\Model\Status;
use App\Mcp\Infrastructure\Adapter\AdapterHolder;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Exception\ToolCallException;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Autoconfigure(public: true)]
final class UpdateIssueTool
{
    public function __construct(
        private readonly AdapterHolder $adapterHolder,
    ) {
    }

    /**
     * Update an issue.
     */
    #[McpTool(name: 'update_issue')]
    public function updateIssue(
        #[Schema(minimum: 1, description: 'The issue ID to update')]
        int $issue_id,
        #[Schema(minimum: 1, description: 'New status ID (optional, use provider://statuses to get valid IDs)')]
        mixed $status_id = null,
        #[Schema(minimum: 0, maximum: 100, description: 'Percentage of completion 0-100 (optional)')]
        mixed $done_ratio = null,
        #[Schema(minimum: 1, description: 'User ID to assign the issue to (optional, use provider://projects/{project_id}/members to get valid IDs)')]
        mixed $assigned_to_id = null,
    ): array {
        $adapter = $this->adapterHolder->getRedmine();

        // Normalize status_id (some clients send strings)
        $status_id = null !== $status_id ? (int) $status_id : null;

        // Normalize done_ratio (some clients send strings)
        $done_ratio = null !== $done_ratio ? (int) $done_ratio : null;

        // Normalize assigned_to_id (some clients send strings)
        $assigned_to_id = null !== $assigned_to_id ? (int) $assigned_to_id : null;

        // Validate status_id format
        if (null !== $status_id && $status_id <= 0) {
            throw new ToolCallException('status_id must be a positive integer.');
        }

        // Validate done_ratio range (0-100)
        if (null !== $done_ratio && ($done_ratio < 0 || $done_ratio > 100)) {
            throw new ToolCallException('done_ratio must be between 0 and 100.');
        }

        // Validate assigned_to_id format
        if (null !== $assigned_to_id && $assigned_to_id <= 0) {
            throw new ToolCallException('assigned_to_id must be a positive integer.');
        }

        // Validate at least one field is provided
        if (null === $status_id && null === $done_ratio && null === $assigned_to_id) {
            throw new ToolCallException('At least one field (status_id, done_ratio, assigned_to_id) must be provided to update.');
        }

        // Fetch issue to validate status_id against allowed transitions
        $issue = $adapter->getIssue($issue_id);

        // Validate status_id is in allowed_statuses (Redmine workflow)
        if (null !== $status_id && !empty($issue->allowedStatuses)) {
            $allowedIds = array_map(fn (Status $s) => $s->id, $issue->allowedStatuses);

            if (!in_array($status_id, $allowedIds, true)) {
                $allowedList = array_map(
                    fn (Status $s) => sprintf('%d (%s)', $s->id, $s->name),
                    $issue->allowedStatuses
                );

                throw new ToolCallException(sprintf(
                    'Status ID %d is not allowed for this issue. Allowed statuses: %s',
                    $status_id,
                    implode(', ', $allowedList)
                ));
            }
        }

        $adapter->updateIssue(
            issueId: $issue_id,
            statusId: $status_id,
            doneRatio: $done_ratio,
            assignedToId: $assigned_to_id,
        );

        return [
            'success' => true,
            'message' => sprintf('Issue #%d updated successfully.', $issue_id),
        ];
    }
}