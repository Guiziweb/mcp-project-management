<?php

declare(strict_types=1);

namespace App\Mcp\Domain\Model;

/**
 * Represents an issue/task in the time tracking system.
 */
readonly class Issue
{
    /**
     * @param array<Comment>    $comments
     * @param array<Attachment> $attachments
     * @param array<Status>     $allowedStatuses Statuses the issue can transition to (workflow-aware)
     */
    public function __construct(
        public int $id,
        public string $title,
        public string $description,
        public Project $project,
        public string $status,
        public ?string $assignee = null,
        public ?string $type = null,
        public ?string $priority = null,
        public array $comments = [],
        public array $attachments = [],
        public array $allowedStatuses = [],
    ) {
    }
}
