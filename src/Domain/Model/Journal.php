<?php

declare(strict_types=1);

namespace App\Domain\Model;

/**
 * Represents a journal entry (comment/change) on an issue.
 */
readonly class Journal
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
