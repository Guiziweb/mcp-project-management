<?php

declare(strict_types=1);

namespace App\Infrastructure\Jira\Normalizer;

use App\Domain\Model\Issue;
use App\Domain\Model\TimeEntry;
use App\Domain\Model\User;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * Denormalizes Jira API worklog data to TimeEntry domain model.
 */
class TimeEntryNormalizer implements DenormalizerInterface, DenormalizerAwareInterface
{
    use DenormalizerAwareTrait;

    /**
     * @param array<string, mixed> $context
     */
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): TimeEntry
    {
        // Denormalize issue (expects issue data in data or context)
        $issueData = $data['issue'] ?? $context['issue'] ?? [];
        $issue = $this->denormalizer->denormalize(
            $issueData,
            Issue::class,
            $format,
            $context
        );

        // Denormalize user from author
        $authorData = $data['author'] ?? [];
        $userData = [
            'accountId' => $authorData['accountId'] ?? '',
            'displayName' => $authorData['displayName'] ?? '',
            'emailAddress' => $authorData['emailAddress'] ?? '',
        ];
        $user = $this->denormalizer->denormalize(
            $userData,
            User::class,
            $format,
            $context
        );

        // Parse started date
        $spentAt = new \DateTime($data['started'] ?? 'now');

        // Extract comment text (may be ADF or string)
        $comment = $this->extractComment($data['comment'] ?? '');

        return new TimeEntry(
            id: (int) ($data['id'] ?? 0),
            issue: $issue,
            user: $user,
            seconds: (int) ($data['timeSpentSeconds'] ?? 0),
            comment: $comment,
            spentAt: $spentAt,
            activity: null, // Jira doesn't have activities
            metadata: [
                'issueKey' => $data['issueKey'] ?? $issueData['key'] ?? null,
            ],
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return TimeEntry::class === $type && 'jira' === ($context['provider'] ?? null);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            TimeEntry::class => true,
        ];
    }

    /**
     * Extract plain text from comment (may be ADF or string).
     */
    private function extractComment(mixed $comment): string
    {
        if (is_string($comment)) {
            return $comment;
        }

        if (!is_array($comment) && !is_object($comment)) {
            return '';
        }

        $comment = (array) $comment;
        if (!isset($comment['content'])) {
            return '';
        }

        $text = '';
        foreach ($comment['content'] as $block) {
            $block = (array) $block;
            if (isset($block['content'])) {
                foreach ($block['content'] as $inline) {
                    $inline = (array) $inline;
                    if (isset($inline['text'])) {
                        $text .= $inline['text'];
                    }
                }
                $text .= "\n";
            }
        }

        return trim($text);
    }
}
