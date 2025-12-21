<?php

declare(strict_types=1);

namespace App\Infrastructure\Redmine\Normalizer;

use App\Domain\Model\Attachment;
use App\Domain\Model\Comment;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * Denormalizes Redmine API journal data to Comment domain model.
 */
class CommentNormalizer implements DenormalizerInterface, DenormalizerAwareInterface
{
    use DenormalizerAwareTrait;

    /**
     * @param array<string, mixed> $context
     */
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): Comment
    {
        $attachments = [];
        if (isset($data['details']) && is_array($data['details'])) {
            foreach ($data['details'] as $detail) {
                // Redmine stores attachment info in details with property = 'attachment'
                if (isset($detail['property']) && 'attachment' === $detail['property']) {
                    // This is just a reference, not the full attachment data
                    // The full attachment data is in the issue's attachments array
                }
            }
        }

        return new Comment(
            id: (int) ($data['id'] ?? 0),
            notes: isset($data['notes']) && '' !== $data['notes'] ? (string) $data['notes'] : null,
            author: isset($data['user']['name']) ? (string) $data['user']['name'] : null,
            createdOn: isset($data['created_on']) ? new \DateTimeImmutable($data['created_on']) : null,
            attachments: $attachments,
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return Comment::class === $type && 'redmine' === ($context['provider'] ?? null);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            Comment::class => true,
        ];
    }
}
