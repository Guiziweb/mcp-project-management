<?php

declare(strict_types=1);

namespace App\Tools;

use App\Domain\Issue\IssueWritePort;
use Mcp\Capability\Attribute\McpTool;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Autoconfigure(public: true)]
final class UpdateCommentTool
{
    public function __construct(
        private readonly IssueWritePort $adapter,
    ) {
    }

    /**
     * Update an existing comment on an issue.
     *
     * @param int    $comment_id The comment ID to update
     * @param string $comment    The new comment content
     *
     * @return array<string, mixed>
     */
    #[McpTool(name: 'update_comment')]
    public function updateComment(
        int $comment_id,
        string $comment,
    ): array {
        try {
            $this->adapter->updateComment($comment_id, $comment);

            return [
                'success' => true,
                'message' => sprintf('Comment #%d updated', $comment_id),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
