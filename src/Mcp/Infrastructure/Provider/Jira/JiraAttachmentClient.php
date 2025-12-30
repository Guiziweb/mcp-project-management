<?php

declare(strict_types=1);

namespace App\Mcp\Infrastructure\Provider\Jira;

use JiraCloud\Attachment\AttachmentService;

/**
 * Extended AttachmentService with download-to-memory capability.
 *
 * The official SDK only supports downloading to a file.
 * This adds support for the /attachment/content/{id} endpoint
 * which returns binary content directly.
 *
 * @see https://developer.atlassian.com/cloud/jira/platform/rest/v3/api-group-issue-attachments/
 */
class JiraAttachmentClient extends AttachmentService
{
    /**
     * Download attachment content directly to memory.
     *
     * Uses the official Jira API endpoint: GET /rest/api/3/attachment/content/{id}
     *
     * @param int|string $id Attachment ID
     *
     * @return string Binary content
     */
    public function downloadContent(int|string $id): string
    {
        $result = $this->exec('/attachment/content/'.$id, null);

        if (false === $result || true === $result) {
            throw new \RuntimeException('Failed to download attachment content');
        }

        return $result;
    }
}
