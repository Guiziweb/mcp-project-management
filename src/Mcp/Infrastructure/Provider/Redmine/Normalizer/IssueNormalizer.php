<?php

declare(strict_types=1);

namespace App\Mcp\Infrastructure\Provider\Redmine\Normalizer;

use App\Mcp\Domain\Model\Attachment;
use App\Mcp\Domain\Model\Comment;
use App\Mcp\Domain\Model\Issue;
use App\Mcp\Domain\Model\Project;
use App\Mcp\Domain\Model\Status;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * Denormalizes Redmine API issue data to Issue domain model.
 */
class IssueNormalizer implements DenormalizerInterface, DenormalizerAwareInterface
{
    use DenormalizerAwareTrait;

    /**
     * @param array<string, mixed> $context
     */
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): Issue
    {
        $issue = $data['issue'] ?? $data;

        // Denormalize nested project using ProjectNormalizer
        $project = $this->denormalizer->denormalize(
            $issue['project'] ?? [],
            Project::class,
            $format,
            $context
        );

        // Denormalize comments (journals in Redmine API) if present
        $comments = [];
        if (isset($issue['journals']) && is_array($issue['journals'])) {
            foreach ($issue['journals'] as $commentData) {
                $comments[] = $this->denormalizer->denormalize(
                    $commentData,
                    Comment::class,
                    $format,
                    $context
                );
            }
        }

        // Denormalize attachments if present
        $attachments = [];
        if (isset($issue['attachments']) && is_array($issue['attachments'])) {
            foreach ($issue['attachments'] as $attachmentData) {
                $attachments[] = $this->denormalizer->denormalize(
                    $attachmentData,
                    Attachment::class,
                    $format,
                    $context
                );
            }
        }

        // Denormalize allowed_statuses if present (Redmine 5.0+)
        $allowedStatuses = [];
        if (isset($issue['allowed_statuses']) && is_array($issue['allowed_statuses'])) {
            foreach ($issue['allowed_statuses'] as $statusData) {
                $allowedStatuses[] = $this->denormalizer->denormalize(
                    $statusData,
                    Status::class,
                    $format,
                    $context
                );
            }
        }

        return new Issue(
            id: (int) ($issue['id'] ?? 0),
            title: (string) ($issue['subject'] ?? ''),
            description: (string) ($issue['description'] ?? ''),
            project: $project,
            status: (string) ($issue['status']['name'] ?? ''),
            assignee: isset($issue['assigned_to']['name']) ? (string) $issue['assigned_to']['name'] : null,
            type: isset($issue['tracker']['name']) ? (string) $issue['tracker']['name'] : null,
            priority: isset($issue['priority']['name']) ? (string) $issue['priority']['name'] : null,
            comments: $comments,
            attachments: $attachments,
            allowedStatuses: $allowedStatuses,
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return Issue::class === $type && 'redmine' === ($context['provider'] ?? null);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            Issue::class => true,
        ];
    }
}
