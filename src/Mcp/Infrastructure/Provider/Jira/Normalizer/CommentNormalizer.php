<?php

declare(strict_types=1);

namespace App\Mcp\Infrastructure\Provider\Jira\Normalizer;

use App\Mcp\Domain\Model\Comment;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * Denormalizes Jira API comment data to Comment domain model.
 */
class CommentNormalizer implements DenormalizerInterface
{
    /**
     * @param array<string, mixed> $context
     */
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): Comment
    {
        $createdOn = null;
        if (isset($data['created'])) {
            try {
                $createdOn = new \DateTimeImmutable($data['created']);
            } catch (\Exception) {
                // Ignore invalid dates
            }
        }

        // Prefer renderedBody (HTML) if available, otherwise extract from ADF
        $notes = $data['renderedBody'] ?? $this->extractTextFromAdf($data['body'] ?? null);

        // Handle author - can be object with displayName
        $author = null;
        if (isset($data['author'])) {
            $author = is_array($data['author'])
                ? ($data['author']['displayName'] ?? null)
                : (string) $data['author'];
        }

        return new Comment(
            id: (int) ($data['id'] ?? 0),
            notes: $notes ?: null,
            author: $author,
            createdOn: $createdOn,
            attachments: [], // Jira comments don't have direct attachments
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return Comment::class === $type && 'jira' === ($context['provider'] ?? null);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            Comment::class => true,
        ];
    }

    /**
     * Extract plain text from Jira's Atlassian Document Format.
     */
    private function extractTextFromAdf(mixed $body): string
    {
        if (is_string($body)) {
            return $body;
        }

        if (!is_array($body) && !is_object($body)) {
            return '';
        }

        $body = (array) $body;
        if (!isset($body['content'])) {
            return '';
        }

        $text = '';
        foreach ($body['content'] as $block) {
            $block = (array) $block;
            if (isset($block['content'])) {
                foreach ($block['content'] as $inline) {
                    $inline = (array) $inline;
                    if (isset($inline['text'])) {
                        $text .= $inline['text'];
                    }
                }
                $text .= "\n";
            }
        }

        return trim($text);
    }
}
