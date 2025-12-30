<?php

declare(strict_types=1);

namespace App\Mcp\Infrastructure\Provider\Redmine\Normalizer;

use App\Mcp\Domain\Model\Status;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * Denormalizes Redmine API status data to Status domain model.
 */
class StatusNormalizer implements DenormalizerInterface
{
    /**
     * @param array<string, mixed> $context
     */
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): Status
    {
        return new Status(
            id: (int) ($data['id'] ?? 0),
            name: (string) ($data['name'] ?? ''),
            isClosed: (bool) ($data['is_closed'] ?? false),
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return Status::class === $type && 'redmine' === ($context['provider'] ?? null);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            Status::class => true,
        ];
    }
}
