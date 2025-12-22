<?php

declare(strict_types=1);

namespace App\Infrastructure\Jira\Normalizer;

use App\Domain\Attachment\Attachment;
use App\Domain\Comment\Comment;
use App\Domain\Issue\Issue;
use App\Domain\Project\Project;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * Denormalizes Jira API issue data to Issue domain model.
 */
class IssueNormalizer implements DenormalizerInterface, DenormalizerAwareInterface
{
    use DenormalizerAwareTrait;

    /**
     * @param array<string, mixed> $context
     */
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): Issue
    {
        // Denormalize nested project
        $project = $this->denormalizer->denormalize(
            $data['project'] ?? [],
            Project::class,
            $format,
            $context
        );

        // Denormalize attachments if present
        $attachments = [];
        if (isset($data['attachments']) && is_array($data['attachments'])) {
            foreach ($data['attachments'] as $attachmentData) {
                $attachments[] = $this->denormalizer->denormalize(
                    $attachmentData,
                    Attachment::class,
                    $format,
                    $context
                );
            }
        }

        // Denormalize comments if present
        $comments = [];
        if (isset($data['comments']) && is_array($data['comments'])) {
            foreach ($data['comments'] as $commentData) {
                $comments[] = $this->denormalizer->denormalize(
                    $commentData,
                    Comment::class,
                    $format,
                    $context
                );
            }
        }

        // Handle assignee - can be object with displayName
        $assignee = null;
        if (isset($data['assignee'])) {
            $assignee = is_array($data['assignee'])
                ? ($data['assignee']['displayName'] ?? null)
                : (string) $data['assignee'];
        }

        // Handle issuetype
        $type = null;
        if (isset($data['issuetype'])) {
            $type = is_array($data['issuetype'])
                ? ($data['issuetype']['name'] ?? null)
                : (string) $data['issuetype'];
        }

        // Handle priority
        $priority = null;
        if (isset($data['priority'])) {
            $priority = is_array($data['priority'])
                ? ($data['priority']['name'] ?? null)
                : (string) $data['priority'];
        }

        return new Issue(
            id: (int) ($data['id'] ?? 0),
            title: (string) ($data['summary'] ?? ''),
            description: (string) ($data['description'] ?? ''),
            project: $project,
            status: (string) ($data['status'] ?? 'Unknown'),
            assignee: $assignee,
            type: $type,
            priority: $priority,
            comments: $comments,
            attachments: $attachments,
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return Issue::class === $type && 'jira' === ($context['provider'] ?? null);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            Issue::class => true,
        ];
    }
}
