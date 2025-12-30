<?php

declare(strict_types=1);

namespace App\Mcp\Infrastructure\Provider\Monday\Normalizer;

use App\Mcp\Domain\Model\Attachment;
use App\Mcp\Domain\Model\Comment;
use App\Mcp\Domain\Model\Issue;
use App\Mcp\Domain\Model\Project;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * Denormalizes Monday.com item data to Issue domain model.
 */
class IssueNormalizer implements DenormalizerInterface, DenormalizerAwareInterface
{
    use DenormalizerAwareTrait;

    /**
     * @param array<string, mixed> $context
     */
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): Issue
    {
        /** @var array{id?: int|string, name?: string, board?: array<string, mixed>, description?: array{blocks?: list<array{content?: string}>}, column_values?: list<array<string, mixed>>, updates?: list<array<string, mixed>>} $data */

        // Denormalize board as project
        $boardData = $data['board'] ?? [];
        $project = $this->denormalizer->denormalize(
            $boardData,
            Project::class,
            $format,
            $context
        );
        \assert($project instanceof Project);

        // Extract description and images from blocks
        $descriptionData = $this->extractDescriptionAndImages($data['description'] ?? []);

        // Extract column values
        /** @var list<array{id?: string, text?: string|null}> $columnValues */
        $columnValues = $data['column_values'] ?? [];
        $columns = $this->extractColumns($columnValues);

        // Denormalize updates as comments
        $comments = [];
        /** @var list<array<string, mixed>> $updates */
        $updates = $data['updates'] ?? [];
        foreach ($updates as $update) {
            $comment = $this->denormalizer->denormalize(
                $update,
                Comment::class,
                $format,
                $context
            );
            \assert($comment instanceof Comment);
            $comments[] = $comment;
        }

        return new Issue(
            id: (int) ($data['id'] ?? 0),
            title: (string) ($data['name'] ?? ''),
            description: $descriptionData['text'],
            project: $project,
            status: $columns['status'] ?? 'Unknown',
            assignee: $columns['assignee'],
            type: $columns['type'],
            priority: $columns['priority'],
            comments: $comments,
            attachments: $descriptionData['attachments'],
        );
    }

    /**
     * Extract text and images from Monday Doc description blocks.
     *
     * @param array{blocks?: list<array{content?: string}>} $description
     *
     * @return array{text: string, attachments: list<Attachment>}
     */
    private function extractDescriptionAndImages(array $description): array
    {
        $blocks = $description['blocks'] ?? [];
        $texts = [];
        $attachments = [];

        foreach ($blocks as $block) {
            $content = $block['content'] ?? '';
            if ('' === $content) {
                continue;
            }

            $decoded = json_decode($content, true);
            if (!\is_array($decoded)) {
                continue;
            }

            // Check if this is an image block
            if (isset($decoded['assetId']) && isset($decoded['url'])) {
                $url = $decoded['url'];
                $assetId = (int) $decoded['assetId'];
                $filename = basename(parse_url($url, \PHP_URL_PATH) ?: 'image.jpg');

                $attachments[] = new Attachment(
                    id: $assetId,
                    filename: $filename,
                    filesize: 0,
                    contentType: $this->guessContentType($filename),
                    contentUrl: $url,
                );
                continue;
            }

            // Extract text from deltaFormat array
            $deltaFormat = $decoded['deltaFormat'] ?? [];
            if (!\is_array($deltaFormat)) {
                continue;
            }

            foreach ($deltaFormat as $delta) {
                if (\is_array($delta) && isset($delta['insert']) && \is_string($delta['insert'])) {
                    $texts[] = $delta['insert'];
                }
            }
        }

        return [
            'text' => implode("\n", $texts),
            'attachments' => $attachments,
        ];
    }

    private function guessContentType(string $filename): string
    {
        $ext = strtolower(pathinfo($filename, \PATHINFO_EXTENSION));

        return match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'pdf' => 'application/pdf',
            default => 'application/octet-stream',
        };
    }

    /**
     * Extract useful values from column_values array.
     *
     * @param list<array{id?: string, text?: string|null}> $columnValues
     *
     * @return array{status: string|null, assignee: string|null, type: string|null, priority: string|null}
     */
    private function extractColumns(array $columnValues): array
    {
        $result = [
            'status' => null,
            'assignee' => null,
            'type' => null,
            'priority' => null,
        ];

        foreach ($columnValues as $column) {
            $id = $column['id'] ?? '';
            $text = $column['text'] ?? null;

            if (!\is_string($text) || '' === $text) {
                continue;
            }

            if (\in_array($id, ['task_status', 'status', 'bug_status'], true)) {
                $result['status'] = $text;
            } elseif (\in_array($id, ['task_owner', 'people', 'people1'], true)) {
                $result['assignee'] = $text;
            } elseif (\in_array($id, ['task_type', 'type'], true)) {
                $result['type'] = $text;
            } elseif (\in_array($id, ['priority', 'priority_1'], true)) {
                $result['priority'] = $text;
            }
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return Issue::class === $type && 'monday' === ($context['provider'] ?? null);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [Issue::class => true];
    }
}
