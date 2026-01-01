<?php

declare(strict_types=1);

namespace App\Mcp\Application\Tool\Redmine;

use App\Mcp\Infrastructure\Adapter\AdapterHolder;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Autoconfigure(public: true)]
final class DeleteCommentTool
{
    public function __construct(
        private readonly AdapterHolder $adapterHolder,
    ) {
    }

    /**
     * Delete a comment from an issue.
     */
    #[McpTool(name: 'delete_comment')]
    public function deleteComment(
        #[Schema(minimum: 1, description: 'The comment ID to delete')]
        int $comment_id,
    ): array {
        $adapter = $this->adapterHolder->getRedmine();
        $adapter->deleteComment($comment_id);

        return [
            'success' => true,
            'message' => sprintf('Comment #%d deleted', $comment_id),
        ];
    }
}