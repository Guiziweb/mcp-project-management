<?php

declare(strict_types=1);

namespace App\Infrastructure\Monday\Normalizer;

use App\Domain\Project\Project;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * Denormalizes Monday.com board data to Project domain model.
 */
class ProjectNormalizer implements DenormalizerInterface
{
    /**
     * @param array<string, mixed> $context
     */
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): Project
    {
        /* @var array{id?: int|string, name?: string} $data */
        return new Project(
            id: (int) ($data['id'] ?? 0),
            name: (string) ($data['name'] ?? ''),
            parent: null,
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return Project::class === $type && 'monday' === ($context['provider'] ?? null);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [Project::class => true];
    }
}
