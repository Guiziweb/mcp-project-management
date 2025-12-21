<?php

declare(strict_types=1);

namespace App\Tools;

use App\Domain\Port\TimeTrackingPort;
use Mcp\Capability\Attribute\McpTool;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Autoconfigure(public: true)]
final class GetIssueDetailsTool
{
    public function __construct(
        private readonly TimeTrackingPort $adapter,
    ) {
    }

    /**
     * Get detailed information about a specific issue by its ID.
     *
     * Returns comprehensive issue data including description, status,
     * priority, assignee, and project information.
     *
     * @param int $issue_id The ID of the issue to retrieve
     *
     * @return array<string, mixed>
     */
    #[McpTool(name: 'get_issue_details')]
    public function getIssueDetails(int $issue_id): array
    {
        try {
            $issue = $this->adapter->getIssue($issue_id);

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
                ],
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
