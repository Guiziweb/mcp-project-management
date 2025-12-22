<?php

declare(strict_types=1);

namespace App\Infrastructure\Redmine\Normalizer;

use App\Domain\Project\Project;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * Denormalizes Redmine API project data to Project domain model.
 */
class ProjectNormalizer implements DenormalizerInterface
{
    /**
     * @param array<string, mixed> $context
     */
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): Project
    {
        $parent = null;
        if (isset($data['parent']) && is_array($data['parent'])) {
            $parent = new Project(
                id: (int) ($data['parent']['id'] ?? 0),
                name: (string) ($data['parent']['name'] ?? ''),
            );
        }

        return new Project(
            id: (int) ($data['id'] ?? 0),
            name: (string) ($data['name'] ?? ''),
            parent: $parent,
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return Project::class === $type && 'redmine' === ($context['provider'] ?? null);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            Project::class => true,
        ];
    }
}
