<?php

declare(strict_types=1);

namespace App\Mcp\Application\Tool;

use App\Mcp\Domain\Port\IssueWritePort;
use Mcp\Capability\Attribute\McpTool;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Autoconfigure(public: true)]
final class AddCommentTool
{
    public function __construct(
        private readonly IssueWritePort $adapter,
    ) {
    }

    /**
     * Add a comment to an issue.
     *
     * @param int    $issue_id The issue ID to add the comment to
     * @param string $comment  The comment content
     * @param bool   $private  Whether the comment is private (visible only to roles with "View private notes" permission)
     *
     * @return array<string, mixed>
     */
    #[McpTool(name: 'add_comment')]
    public function addComment(
        int $issue_id,
        string $comment,
        bool $private = false,
    ): array {
        try {
            $this->adapter->addComment($issue_id, $comment, $private);

            return [
                'success' => true,
                'message' => sprintf('Comment added to issue #%d', $issue_id),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
