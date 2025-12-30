<?php

declare(strict_types=1);

namespace App\Mcp\Infrastructure\Provider\Redmine\Normalizer;

use App\Mcp\Domain\Model\Activity;
use App\Mcp\Domain\Model\Issue;
use App\Mcp\Domain\Model\ProviderUser;
use App\Mcp\Domain\Model\TimeEntry;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * Denormalizes Redmine API time entry data to TimeEntry domain model.
 */
class TimeEntryNormalizer implements DenormalizerInterface, DenormalizerAwareInterface
{
    use DenormalizerAwareTrait;

    /**
     * @param array<string, mixed> $context
     */
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): TimeEntry
    {
        $entry = $data['time_entry'] ?? $data;

        // Denormalize nested issue (with minimal data from time entry response)
        $issueData = [
            'id' => $entry['issue']['id'] ?? 0,
            'subject' => '',
            'description' => '',
            'project' => $entry['project'] ?? [],
            'status' => ['name' => ''],
        ];

        $issue = $this->denormalizer->denormalize(
            $issueData,
            Issue::class,
            $format,
            $context
        );

        // Denormalize user
        $user = $this->denormalizer->denormalize(
            [
                'id' => $entry['user']['id'] ?? 0,
                'firstname' => $entry['user']['name'] ?? '',
                'lastname' => '',
                'mail' => '',
            ],
            ProviderUser::class,
            $format,
            $context
        );

        // Denormalize activity if present
        $activity = null;
        if (isset($entry['activity']) && is_array($entry['activity'])) {
            $activity = $this->denormalizer->denormalize(
                $entry['activity'],
                Activity::class,
                $format,
                $context
            );
        }

        // Parse spent_on date
        $spentAt = new \DateTime($entry['spent_on'] ?? 'now');

        // Convert hours to seconds
        $seconds = (int) (((float) ($entry['hours'] ?? 0)) * 3600);

        return new TimeEntry(
            id: (int) ($entry['id'] ?? 0),
            issue: $issue,
            user: $user,
            seconds: $seconds,
            comment: (string) ($entry['comments'] ?? ''),
            spentAt: $spentAt,
            activity: $activity,
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return TimeEntry::class === $type && 'redmine' === ($context['provider'] ?? null);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            TimeEntry::class => true,
        ];
    }
}
