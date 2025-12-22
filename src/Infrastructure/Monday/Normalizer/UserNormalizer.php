<?php

declare(strict_types=1);

namespace App\Infrastructure\Monday\Normalizer;

use App\Domain\User\User;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * Denormalizes Monday.com API user data to User domain model.
 */
class UserNormalizer implements DenormalizerInterface
{
    /**
     * @param array<string, mixed> $context
     */
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): User
    {
        /* @var array{id?: int|string, name?: string, email?: string|null} $data */
        return new User(
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
        return User::class === $type && 'monday' === ($context['provider'] ?? null);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [User::class => true];
    }
}
