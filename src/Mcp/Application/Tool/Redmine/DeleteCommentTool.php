<?php

declare(strict_types=1);

namespace App\Mcp\Application\Tool\Redmine;

use App\Mcp\Application\Tool\RedmineTool;
use App\Mcp\Infrastructure\Adapter\AdapterHolder;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Autoconfigure(public: true)]
final class DeleteCommentTool implements RedmineTool
{
    public function __construct(
        private readonly AdapterHolder $adapterHolder,
    ) {
    }

    /**
     * Delete a comment from an issue.
     *
     * @return array<string, mixed>
     */
    #[McpTool(name: 'delete_comment')]
    public function deleteComment(
        #[Schema(description: 'The comment ID to delete')]
        mixed $comment_id,
    ): array {
        $comment_id = (int) $comment_id;
        $adapter = $this->adapterHolder->getRedmine();
        $adapter->deleteComment($comment_id);

        return [
            'success' => true,
            'message' => sprintf('Comment #%d deleted', $comment_id),
        ];
    }
}
