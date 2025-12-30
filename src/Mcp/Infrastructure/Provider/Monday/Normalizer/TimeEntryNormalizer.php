<?php

declare(strict_types=1);

namespace App\Mcp\Infrastructure\Provider\Monday\Normalizer;

use App\Mcp\Domain\Model\Issue;
use App\Mcp\Domain\Model\ProviderUser;
use App\Mcp\Domain\Model\TimeEntry;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * Denormalizes Monday.com time tracking data to TimeEntry domain model.
 *
 * Monday's time tracking is aggregated per item, not per work session.
 * The 'duration' is the total tracked time in seconds.
 */
class TimeEntryNormalizer implements DenormalizerInterface, DenormalizerAwareInterface
{
    use DenormalizerAwareTrait;

    /**
     * @param array<string, mixed> $context
     */
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): TimeEntry
    {
        /** @var array{id?: int|string, column_values?: list<array{duration?: int, updated_at?: string, started_at?: string}>} $data */

        // Denormalize issue from item data
        $issue = $this->denormalizer->denormalize(
            $data,
            Issue::class,
            $format,
            $context
        );
        \assert($issue instanceof Issue);

        // User from context (current user)
        $user = $context['current_user'] ?? null;
        if (!$user instanceof ProviderUser) {
            $user = new ProviderUser(0, 'Unknown', '');
        }

        // Extract time tracking column data
        $duration = 0;
        $updatedAt = new \DateTime();

        /** @var list<array{duration?: int, updated_at?: string, started_at?: string}> $columnValues */
        $columnValues = $data['column_values'] ?? [];
        foreach ($columnValues as $column) {
            if (isset($column['duration'])) {
                $duration = $column['duration'];
                if (isset($column['updated_at'])) {
                    $updatedAt = new \DateTime($column['updated_at']);
                } elseif (isset($column['started_at'])) {
                    $updatedAt = new \DateTime($column['started_at']);
                }
                break;
            }
        }

        return new TimeEntry(
            id: (int) ($data['id'] ?? 0),
            issue: $issue,
            user: $user,
            seconds: $duration,
            comment: '',
            spentAt: $updatedAt,
            activity: null,
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return TimeEntry::class === $type && 'monday' === ($context['provider'] ?? null);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [TimeEntry::class => true];
    }
}
