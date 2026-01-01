<?php

declare(strict_types=1);

namespace App\Mcp\Application\Tool\Redmine;

use App\Mcp\Infrastructure\Adapter\AdapterHolder;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Autoconfigure(public: true)]
final class UpdateCommentTool
{
    public function __construct(
        private readonly AdapterHolder $adapterHolder,
    ) {
    }

    /**
     * Update an existing comment on an issue.
     */
    #[McpTool(name: 'update_comment')]
    public function updateComment(
        #[Schema(minimum: 1, description: 'The comment ID to update')]
        int $comment_id,
        #[Schema(description: 'The new comment content')]
        string $comment,
    ): array {
        $adapter = $this->adapterHolder->getRedmine();
        $adapter->updateComment($comment_id, $comment);

        return [
            'success' => true,
            'message' => sprintf('Comment #%d updated', $comment_id),
        ];
    }
}