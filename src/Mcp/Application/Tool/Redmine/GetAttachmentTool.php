<?php

declare(strict_types=1);

namespace App\Mcp\Application\Tool\Redmine;

use App\Mcp\Infrastructure\Adapter\AdapterHolder;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\Content\ImageContent;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Result\CallToolResult;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Autoconfigure(public: true)]
final class GetAttachmentTool
{
    private const SUPPORTED_IMAGE_TYPES = [
        'image/png',
        'image/jpeg',
        'image/jpg',
        'image/gif',
        'image/webp',
    ];

    public function __construct(
        private readonly AdapterHolder $adapterHolder,
    ) {
    }

    /**
     * Download and display an attachment from Redmine.
     */
    #[McpTool(name: 'get_attachment')]
    public function getAttachment(
        #[Schema(minimum: 1, description: 'The ID of the attachment to download')]
        int $attachment_id,
    ): CallToolResult
    {
        try {
            $adapter = $this->adapterHolder->getRedmine();

            // Get attachment metadata
            $attachment = $adapter->getAttachment($attachment_id);

            $filename = $attachment['filename'];
            $contentType = $attachment['content_type'];
            $filesize = $attachment['filesize'];

            // Check if it's an image
            if (in_array($contentType, self::SUPPORTED_IMAGE_TYPES, true)) {
                // Download the image content
                $content = $adapter->downloadAttachment($attachment_id);

                return CallToolResult::success([
                    new TextContent(sprintf(
                        "Attachment: %s\nType: %s\nSize: %d bytes",
                        $filename,
                        $contentType,
                        $filesize
                    )),
                    ImageContent::fromString($content, $contentType),
                ]);
            }

            // For non-image files, return text info
            return CallToolResult::success([
                new TextContent(sprintf(
                    "Attachment: %s\nType: %s\nSize: %d bytes\n\nNote: This file type (%s) cannot be displayed directly. It can be downloaded from Redmine.",
                    $filename,
                    $contentType,
                    $filesize,
                    $contentType
                )),
            ]);
        } catch (\Throwable $e) {
            return CallToolResult::error([
                new TextContent('Error downloading attachment: '.$e->getMessage()),
            ]);
        }
    }
}