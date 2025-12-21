<?php

declare(strict_types=1);

namespace App\Infrastructure\Jira\Normalizer;

use App\Domain\Model\User;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * Denormalizes Jira API user data to User domain model.
 */
class UserNormalizer implements DenormalizerInterface
{
    /**
     * @param array<string, mixed> $context
     */
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): User
    {
        return new User(
            id: $this->accountIdToInt($data['accountId'] ?? ''),
            name: (string) ($data['displayName'] ?? ''),
            email: (string) ($data['emailAddress'] ?? ''),
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return User::class === $type && 'jira' === ($context['provider'] ?? null);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            User::class => true,
        ];
    }

    /**
     * Convert Jira accountId string to integer for compatibility with User model.
     */
    private function accountIdToInt(string $accountId): int
    {
        return abs(crc32($accountId));
    }
}
