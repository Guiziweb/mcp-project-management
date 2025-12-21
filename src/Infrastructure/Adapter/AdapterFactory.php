<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapter;

use App\Domain\Model\UserCredential;
use App\Domain\Port\AttachmentPort;
use App\Domain\Port\IssuePort;
use App\Domain\Port\ProjectPort;
use App\Domain\Port\TimeEntryPort;
use App\Domain\Port\UserPort;
use App\Infrastructure\Jira\JiraAdapter;
use App\Infrastructure\Jira\JiraClient;
use App\Infrastructure\Redmine\RedmineAdapter;
use App\Infrastructure\Redmine\RedmineClient;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * Factory for creating user-specific time tracking adapters.
 *
 * Supports multiple adapters: Redmine, Jira, etc.
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
    public function createForUser(UserCredential $credential): UserPort&ProjectPort&IssuePort&TimeEntryPort&AttachmentPort
    {
        return match ($credential->provider) {
            UserCredential::PROVIDER_REDMINE => $this->createRedmineAdapter($credential),
            UserCredential::PROVIDER_JIRA => $this->createJiraAdapter($credential),
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
}
