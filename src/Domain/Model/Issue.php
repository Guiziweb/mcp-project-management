<?php

declare(strict_types=1);

namespace App\Domain\Model;

/**
 * Represents an issue/task in the time tracking system.
 */
readonly class Issue
{
    /**
     * @param array<Journal>    $journals
     * @param array<Attachment> $attachments
     */
    public function __construct(
        public int $id,
        public string $title,
        public string $description,
        public Project $project,
        public string $status,
        public ?string $assignee = null,
        public ?string $tracker = null,
        public ?string $priority = null,
        public array $journals = [],
        public array $attachments = [],
    ) {
    }
}
