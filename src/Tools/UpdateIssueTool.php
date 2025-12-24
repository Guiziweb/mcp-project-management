<?php

declare(strict_types=1);

namespace App\Tools;

use App\Domain\Issue\IssueReadPort;
use App\Domain\Issue\IssueWritePort;
use App\Domain\Status\Status;
use Mcp\Capability\Attribute\McpTool;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Autoconfigure(public: true)]
final class UpdateIssueTool
{
    public function __construct(
        private readonly IssueWritePort $writeAdapter,
        private readonly IssueReadPort $readAdapter,
    ) {
    }

    /**
     * Update an issue.
     *
     * Allows modifying issue fields like status.
     * At least one field must be provided.
     * Use the provider://statuses resource to get available status IDs.
     *
     * @param int             $issue_id  The issue ID to update
     * @param int|string|null $status_id New status ID (optional, use provider://statuses to get valid IDs)
     *
     * @return array<string, mixed>
     */
    #[McpTool(name: 'update_issue')]
    public function updateIssue(
        int $issue_id,
        int|string|null $status_id = null,
    ): array {
        try {
            // Normalize status_id (some clients send strings)
            $status_id = null !== $status_id ? (int) $status_id : null;

            // Validate status_id format
            if (null !== $status_id && $status_id <= 0) {
                return [
                    'success' => false,
                    'error' => 'status_id must be a positive integer.',
                ];
            }

            // Validate at least one field is provided
            if (null === $status_id) {
                return [
                    'success' => false,
                    'error' => 'At least one field (status_id) must be provided to update.',
                ];
            }

            // Fetch issue to validate status_id against allowed transitions
            $issue = $this->readAdapter->getIssue($issue_id);

            // Validate status_id is in allowed_statuses (if available)
            if (!empty($issue->allowedStatuses)) {
                $allowedIds = array_map(fn (Status $s) => $s->id, $issue->allowedStatuses);

                if (!in_array($status_id, $allowedIds, true)) {
                    $allowedList = array_map(
                        fn (Status $s) => sprintf('%d (%s)', $s->id, $s->name),
                        $issue->allowedStatuses
                    );

                    return [
                        'success' => false,
                        'error' => sprintf(
                            'Status ID %d is not allowed for this issue. Allowed statuses: %s',
                            $status_id,
                            implode(', ', $allowedList)
                        ),
                        'current_status' => $issue->status,
                        'allowed_statuses' => array_map(
                            fn (Status $s) => ['id' => $s->id, 'name' => $s->name],
                            $issue->allowedStatuses
                        ),
                    ];
                }
            }

            $this->writeAdapter->updateIssue(
                issueId: $issue_id,
                statusId: $status_id,
            );

            return [
                'success' => true,
                'message' => sprintf('Issue #%d updated successfully.', $issue_id),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
