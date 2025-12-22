<?php

declare(strict_types=1);

namespace App\Infrastructure\Monday\Normalizer;

use App\Domain\Comment\Comment;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * Denormalizes Monday.com update data to Comment domain model.
 */
class CommentNormalizer implements DenormalizerInterface
{
    /**
     * @param array<string, mixed> $context
     */
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): Comment
    {
        /** @var array{id?: int|string, body?: string, created_at?: string, creator?: array{name?: string}} $data */
        $createdOn = null;
        if (isset($data['created_at'])) {
            $createdOn = new \DateTimeImmutable($data['created_at']);
        }

        $authorName = null;
        if (isset($data['creator']['name'])) {
            $authorName = $data['creator']['name'];
        }

        return new Comment(
            id: (int) ($data['id'] ?? 0),
            notes: $data['body'] ?? null,
            author: $authorName,
            createdOn: $createdOn,
            attachments: [],
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return Comment::class === $type && 'monday' === ($context['provider'] ?? null);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [Comment::class => true];
    }
}
