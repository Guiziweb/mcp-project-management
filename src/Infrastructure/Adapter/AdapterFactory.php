<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapter;

use App\Domain\Model\UserCredential;
use App\Domain\Port\AttachmentPort;
use App\Domain\Port\IssuePort;
use App\Domain\Port\ProjectPort;
use App\Domain\Port\TimeEntryReadPort;
use App\Domain\Port\UserPort;
use App\Infrastructure\Jira\JiraAdapter;
use App\Infrastructure\Jira\JiraClient;
use App\Infrastructure\Monday\MondayAdapter;
use App\Infrastructure\Monday\MondayClient;
use App\Infrastructure\Redmine\RedmineAdapter;
use App\Infrastructure\Redmine\RedmineClient;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * Factory for creating user-specific adapters.
 *
 * Supports: Redmine, Jira, Monday.
 *
 * Use instanceof to check for optional capabilities:
 * - TimeEntryWritePort: logging/updating time (not supported by Monday)
 * - ActivityPort: time entry activities (only Redmine)
 */
final readonly class AdapterFactory
{
    public function __construct(
        private DenormalizerInterface $serializer,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Create an adapter for a specific user based on their credentials.
     *
     * @throws \InvalidArgumentException if adapter type is not supported
     */
    public function createForUser(UserCredential $credential): UserPort&ProjectPort&IssuePort&TimeEntryReadPort&AttachmentPort
    {
        return match ($credential->provider) {
            UserCredential::PROVIDER_REDMINE => $this->createRedmineAdapter($credential),
            UserCredential::PROVIDER_JIRA => $this->createJiraAdapter($credential),
            UserCredential::PROVIDER_MONDAY => $this->createMondayAdapter($credential),
            default => throw new \InvalidArgumentException(sprintf('Unsupported adapter: %s', $credential->provider)),
        };
    }

    private function createRedmineAdapter(UserCredential $credential): RedmineAdapter
    {
        $redmineClient = new RedmineClient(
            $credential->url,
            $credential->apiKey,
            $this->logger
        );

        return new RedmineAdapter($redmineClient, $this->serializer);
    }

    private function createJiraAdapter(UserCredential $credential): JiraAdapter
    {
        if (null === $credential->email) {
            throw new \InvalidArgumentException('Jira credentials require an email address');
        }

        $jiraClient = new JiraClient(
            $credential->url,
            $credential->email,
            $credential->apiKey,
        );

        return new JiraAdapter($jiraClient, $this->serializer);
    }

    private function createMondayAdapter(UserCredential $credential): MondayAdapter
    {
        $mondayClient = new MondayClient($credential->apiKey);

        return new MondayAdapter($mondayClient, $this->serializer);
    }
}
