<?php

declare(strict_types=1);

namespace App\Mcp\Infrastructure\Provider\Monday\Normalizer;

use App\Mcp\Domain\Model\ProviderUser;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * Denormalizes Monday.com API user data to ProviderUser domain model.
 */
class UserNormalizer implements DenormalizerInterface
{
    /**
     * @param array<string, mixed> $context
     */
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): ProviderUser
    {
        /* @var array{id?: int|string, name?: string, email?: string|null} $data */
        return new ProviderUser(
            id: (int) ($data['id'] ?? 0),
            name: (string) ($data['name'] ?? ''),
            email: (string) ($data['email'] ?? ''),
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return ProviderUser::class === $type && 'monday' === ($context['provider'] ?? null);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [ProviderUser::class => true];
    }
}
