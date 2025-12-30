<?php

declare(strict_types=1);

namespace App\Mcp\Infrastructure\Provider\Redmine\Normalizer;

use App\Mcp\Domain\Model\ProviderUser;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * Denormalizes Redmine API user data to ProviderUser domain model.
 */
class UserNormalizer implements DenormalizerInterface
{
    /**
     * @param array<string, mixed> $context
     */
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): ProviderUser
    {
        $user = $data['user'] ?? $data;

        return new ProviderUser(
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
        return ProviderUser::class === $type && 'redmine' === ($context['provider'] ?? null);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            ProviderUser::class => true,
        ];
    }
}
