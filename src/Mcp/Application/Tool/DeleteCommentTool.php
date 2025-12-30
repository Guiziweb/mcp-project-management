<?php

declare(strict_types=1);

namespace App\Mcp\Application\Tool;

use App\Mcp\Domain\Port\IssueWritePort;
use Mcp\Capability\Attribute\McpTool;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Autoconfigure(public: true)]
final class DeleteCommentTool
{
    public function __construct(
        private readonly IssueWritePort $adapter,
    ) {
    }

    /**
     * Delete a comment from an issue.
     *
     * Note: This action is usually restricted to administrators in most providers.
     *
     * @param int $comment_id The comment ID to delete
     *
     * @return array<string, mixed>
     */
    #[McpTool(name: 'delete_comment')]
    public function deleteComment(
        int $comment_id,
    ): array {
        try {
            $this->adapter->deleteComment($comment_id);

            return [
                'success' => true,
                'message' => sprintf('Comment #%d deleted', $comment_id),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
