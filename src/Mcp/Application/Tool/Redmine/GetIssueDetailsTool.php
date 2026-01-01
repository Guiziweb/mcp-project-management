<?php

declare(strict_types=1);

namespace App\Mcp\Application\Tool\Redmine;

use App\Mcp\Infrastructure\Adapter\AdapterHolder;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Autoconfigure(public: true)]
final class GetIssueDetailsTool
{
    public function __construct(
        private readonly AdapterHolder $adapterHolder,
    ) {
    }

    /**
     * Get detailed information about a specific issue by its ID.
     */
    #[McpTool(name: 'get_issue_details')]
    public function getIssueDetails(
        #[Schema(minimum: 1, description: 'The ID of the issue to retrieve')]
        int $issue_id,
    ): array
    {
        $adapter = $this->adapterHolder->getRedmine();
        $issue = $adapter->getIssue($issue_id);

        // Format comments
        $comments = array_map(fn ($comment) => [
            'id' => $comment->id,
            'notes' => $comment->notes,
            'author' => $comment->author,
            'created_on' => $comment->createdOn?->format('Y-m-d H:i:s'),
        ], $issue->comments);

        // Format attachments
        $attachments = array_map(fn ($attachment) => [
            'id' => $attachment->id,
            'filename' => $attachment->filename,
            'filesize' => $attachment->filesize,
            'content_type' => $attachment->contentType,
            'description' => $attachment->description,
            'author' => $attachment->author,
            'created_on' => $attachment->createdOn?->format('Y-m-d H:i:s'),
        ], $issue->attachments);

        // Format allowed statuses (Redmine workflow)
        $allowedStatuses = array_map(fn ($status) => [
            'id' => $status->id,
            'name' => $status->name,
        ], $issue->allowedStatuses);

        return [
            'success' => true,
            'issue' => [
                'id' => $issue->id,
                'title' => $issue->title,
                'description' => $issue->description,
                'status' => $issue->status,
                'project' => [
                    'id' => $issue->project->id,
                    'name' => $issue->project->name,
                ],
                'assignee' => $issue->assignee,
                'type' => $issue->type,
                'priority' => $issue->priority,
                'comments' => $comments,
                'attachments' => $attachments,
                'allowed_statuses' => $allowedStatuses,
            ],
        ];
    }
}