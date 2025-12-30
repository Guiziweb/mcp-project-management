<?php

declare(strict_types=1);

namespace App\Mcp\Infrastructure\Provider\Jira\Normalizer;

use App\Mcp\Domain\Model\ProviderUser;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * Denormalizes Jira API user data to ProviderUser domain model.
 */
class UserNormalizer implements DenormalizerInterface
{
    /**
     * @param array<string, mixed> $context
     */
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): ProviderUser
    {
        return new ProviderUser(
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
        return ProviderUser::class === $type && 'jira' === ($context['provider'] ?? null);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            ProviderUser::class => true,
        ];
    }

    /**
     * Convert Jira accountId string to integer for compatibility with ProviderUser model.
     */
    private function accountIdToInt(string $accountId): int
    {
        return abs(crc32($accountId));
    }
}
