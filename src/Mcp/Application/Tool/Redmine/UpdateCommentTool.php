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
final class UpdateCommentTool implements RedmineTool
{
    public function __construct(
        private readonly AdapterHolder $adapterHolder,
    ) {
    }

    /**
     * Update an existing comment on an issue.
     *
     * @return array<string, mixed>
     */
    #[McpTool(name: 'update_comment')]
    public function updateComment(
        #[Schema(description: 'The comment ID to update')]
        mixed $comment_id,
        #[Schema(description: 'The new comment content')]
        string $comment,
    ): array {
        try {
            $comment_id = (int) $comment_id;
            $adapter = $this->adapterHolder->getRedmine();
            $adapter->updateComment($comment_id, $comment);

            return [
                'success' => true,
                'message' => sprintf('Comment #%d updated', $comment_id),
            ];
        } catch (RedmineApiException $e) {
            throw new ToolCallException($e->getMessage());
        }
    }
}
