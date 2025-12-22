<?php

declare(strict_types=1);

namespace App\Domain\Attachment;

interface AttachmentReadPort
{
    /**
     * Get attachment metadata.
     *
     * @return array{id: int, filename: string, filesize: int, content_type: string, description: ?string, author: ?string}
     */
    public function getAttachment(int $attachmentId): array;

    /**
     * Download attachment content.
     *
     * @return string Binary content of the attachment
     */
    public function downloadAttachment(int $attachmentId): string;
}
