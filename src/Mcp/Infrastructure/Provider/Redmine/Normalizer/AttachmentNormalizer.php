<?php

declare(strict_types=1);

namespace App\Mcp\Infrastructure\Provider\Redmine\Normalizer;

use App\Mcp\Domain\Model\Attachment;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * Denormalizes Redmine API attachment data to Attachment domain model.
 */
class AttachmentNormalizer implements DenormalizerInterface
{
    /**
     * @param array<string, mixed> $context
     */
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): Attachment
    {
        return new Attachment(
            id: (int) ($data['id'] ?? 0),
            filename: (string) ($data['filename'] ?? ''),
            filesize: (int) ($data['filesize'] ?? 0),
            contentType: (string) ($data['content_type'] ?? ''),
            description: isset($data['description']) ? (string) $data['description'] : null,
            contentUrl: isset($data['content_url']) ? (string) $data['content_url'] : null,
            author: isset($data['author']['name']) ? (string) $data['author']['name'] : null,
            createdOn: isset($data['created_on']) ? new \DateTimeImmutable($data['created_on']) : null,
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return Attachment::class === $type && 'redmine' === ($context['provider'] ?? null);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            Attachment::class => true,
        ];
    }
}
