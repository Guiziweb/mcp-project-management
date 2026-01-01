<?php

declare(strict_types=1);

namespace App\Mcp\Application\Tool\Redmine;

use App\Mcp\Infrastructure\Adapter\AdapterHolder;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Autoconfigure(public: true)]
final class AddCommentTool
{
    public function __construct(
        private readonly AdapterHolder $adapterHolder,
    ) {
    }

    /**
     * Add a comment to an issue.
     */
    #[McpTool(name: 'add_comment')]
    public function addComment(
        #[Schema(minimum: 1, description: 'The issue ID to add the comment to')]
        int $issue_id,
        #[Schema(description: 'The comment content')]
        string $comment,
        #[Schema(description: 'Whether the comment is private (visible only to roles with "View private notes" permission)')]
        bool $private = false,
    ): array {
        $adapter = $this->adapterHolder->getRedmine();
        $adapter->addComment($issue_id, $comment, $private);

        return [
            'success' => true,
            'message' => sprintf('Comment added to issue #%d', $issue_id),
        ];
    }
}