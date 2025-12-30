<?php

declare(strict_types=1);

namespace App\Mcp\Domain\Model;

/**
 * Represents a comment on an issue.
 */
readonly class Comment
{
    /**
     * @param array<Attachment> $attachments
     */
    public function __construct(
        public int $id,
        public ?string $notes = null,
        public ?string $author = null,
        public ?\DateTimeImmutable $createdOn = null,
        public array $attachments = [],
    ) {
    }
}
