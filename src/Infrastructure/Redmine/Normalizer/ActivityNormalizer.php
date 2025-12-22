<?php

declare(strict_types=1);

namespace App\Infrastructure\Redmine\Normalizer;

use App\Domain\Activity\Activity;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * Denormalizes Redmine API activity data to Activity domain model.
 */
class ActivityNormalizer implements DenormalizerInterface
{
    /**
     * @param array<string, mixed> $context
     */
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): Activity
    {
        return new Activity(
            id: (int) ($data['id'] ?? 0),
            name: (string) ($data['name'] ?? ''),
            isDefault: (bool) ($data['is_default'] ?? false),
            active: (bool) ($data['active'] ?? true),
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return Activity::class === $type && 'redmine' === ($context['provider'] ?? null);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            Activity::class => true,
        ];
    }
}
