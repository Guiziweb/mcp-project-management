<?php

declare(strict_types=1);

namespace App\Tools;

use App\Domain\Provider\TimeTrackingProviderInterface;
use Mcp\Capability\Attribute\McpTool;
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
        private readonly TimeTrackingProviderInterface $provider,
    ) {
    }

    /**
     * Download and display an attachment from Redmine.
     *
     * Returns the attachment content. For images, returns the image data that can be displayed.
     * For other files, returns information about the file.
     *
     * @param int $attachment_id The ID of the attachment to download
     */
    #[McpTool(name: 'get_attachment')]
    public function getAttachment(int $attachment_id): CallToolResult
    {
        try {
            // Get attachment metadata
            $attachment = $this->provider->getAttachment($attachment_id);

            $filename = $attachment['filename'];
            $contentType = $attachment['content_type'];
            $filesize = $attachment['filesize'];

            // Check if it's an image
            if (in_array($contentType, self::SUPPORTED_IMAGE_TYPES, true)) {
                // Download the image content
                $content = $this->provider->downloadAttachment($attachment_id);

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
