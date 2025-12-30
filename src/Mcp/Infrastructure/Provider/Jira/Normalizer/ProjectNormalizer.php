<?php

declare(strict_types=1);

namespace App\Mcp\Infrastructure\Provider\Jira\Normalizer;

use App\Mcp\Domain\Model\Project;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * Denormalizes Jira API project data to Project domain model.
 */
class ProjectNormalizer implements DenormalizerInterface
{
    /**
     * @param array<string, mixed> $context
     */
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): Project
    {
        $key = $data['key'] ?? '';
        $name = $data['name'] ?? '';

        // Format: "Project Name (KEY)" for display
        $displayName = $key ? sprintf('%s (%s)', $name, $key) : $name;

        return new Project(
            id: (int) ($data['id'] ?? 0),
            name: $displayName,
            parent: null, // Jira projects don't have hierarchy like Redmine
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return Project::class === $type && 'jira' === ($context['provider'] ?? null);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            Project::class => true,
        ];
    }
}
