<?php

declare(strict_types=1);

namespace App\Infrastructure\Jira\Normalizer;

use App\Domain\Model\Attachment;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * Denormalizes Jira API attachment data to Attachment domain model.
 */
class AttachmentNormalizer implements DenormalizerInterface
{
    /**
     * @param array<string, mixed> $context
     */
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): Attachment
    {
        $createdOn = null;
        if (isset($data['created'])) {
            try {
                $createdOn = new \DateTimeImmutable($data['created']);
            } catch (\Exception) {
                // Ignore invalid dates
            }
        }

        // Handle author - can be object with displayName or direct string
        $author = null;
        if (isset($data['author'])) {
            $author = is_array($data['author'])
                ? ($data['author']['displayName'] ?? null)
                : (string) $data['author'];
        }

        return new Attachment(
            id: (int) ($data['id'] ?? 0),
            filename: (string) ($data['filename'] ?? ''),
            filesize: (int) ($data['size'] ?? 0),
            contentType: (string) ($data['mimeType'] ?? 'application/octet-stream'),
            description: null, // Jira attachments don't have descriptions
            contentUrl: isset($data['content']) ? (string) $data['content'] : null,
            author: $author,
            createdOn: $createdOn,
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return Attachment::class === $type && 'jira' === ($context['provider'] ?? null);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            Attachment::class => true,
        ];
    }
}
