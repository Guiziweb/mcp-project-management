<?php

declare(strict_types=1);

namespace App\Mcp\Domain\Model;

/**
 * Represents a wiki page.
 */
readonly class WikiPage
{
    public function __construct(
        public string $title,
        public ?string $text = null,
        public ?string $version = null,
        public ?string $author = null,
        public ?\DateTimeInterface $createdOn = null,
        public ?\DateTimeInterface $updatedOn = null,
    ) {
    }
}
