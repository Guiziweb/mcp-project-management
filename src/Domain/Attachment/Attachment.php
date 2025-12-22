<?php

declare(strict_types=1);

namespace App\Domain\Attachment;

/**
 * Represents a file attachment.
 */
readonly class Attachment
{
    public function __construct(
        public int $id,
        public string $filename,
        public int $filesize,
        public string $contentType,
        public ?string $description = null,
        public ?string $contentUrl = null,
        public ?string $author = null,
        public ?\DateTimeImmutable $createdOn = null,
    ) {
    }
}
