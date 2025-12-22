<?php

declare(strict_types=1);

namespace App\Infrastructure\Redmine\Normalizer;

use App\Domain\User\User;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * Denormalizes Redmine API user data to User domain model.
 */
class UserNormalizer implements DenormalizerInterface
{
    /**
     * @param array<string, mixed> $context
     */
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): User
    {
        $user = $data['user'] ?? $data;

        return new User(
            id: (int) ($user['id'] ?? 0),
            name: trim(($user['firstname'] ?? '').' '.($user['lastname'] ?? '')),
            email: (string) ($user['mail'] ?? ''),
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return User::class === $type && 'redmine' === ($context['provider'] ?? null);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            User::class => true,
        ];
    }
}
