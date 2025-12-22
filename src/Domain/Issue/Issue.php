<?php

declare(strict_types=1);

namespace App\Domain\Issue;

use App\Domain\Attachment\Attachment;
use App\Domain\Comment\Comment;
use App\Domain\Project\Project;

/**
 * Represents an issue/task in the time tracking system.
 */
readonly class Issue
{
    /**
     * @param array<Comment>    $comments
     * @param array<Attachment> $attachments
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
    ) {
    }
}
