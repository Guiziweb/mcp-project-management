<?php

declare(strict_types=1);

namespace App\Mcp\Infrastructure\Adapter;

use App\Mcp\Domain\Port\AttachmentReadPort;
use App\Mcp\Domain\Port\IssueReadPort;
use App\Mcp\Domain\Port\ProjectPort;
use App\Mcp\Domain\Port\TimeEntryReadPort;
use App\Mcp\Domain\Port\UserPort;
use App\Mcp\Infrastructure\Provider\Jira\JiraAdapter;
use App\Mcp\Infrastructure\Provider\Jira\JiraClient;
use App\Mcp\Infrastructure\Provider\Monday\MondayAdapter;
use App\Mcp\Infrastructure\Provider\Monday\MondayClient;
use App\Mcp\Infrastructure\Provider\Redmine\RedmineAdapter;
use App\Mcp\Infrastructure\Provider\Redmine\RedmineClient;
use App\Shared\Domain\UserCredential;
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
    public function createForUser(UserCredential $credential): UserPort&ProjectPort&IssueReadPort&TimeEntryReadPort&AttachmentReadPort
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
        $url = $credential->getUrl();
        $apiKey = $credential->getApiKey();

        if (null === $url || null === $apiKey) {
            throw new \InvalidArgumentException('Redmine credentials require url and api_key');
        }

        $redmineClient = new RedmineClient($url, $apiKey, $this->logger);

        return new RedmineAdapter($redmineClient, $this->serializer);
    }

    private function createJiraAdapter(UserCredential $credential): JiraAdapter
    {
        $url = $credential->getUrl();
        $email = $credential->getEmail();
        $apiKey = $credential->getApiKey();

        if (null === $url || null === $email || null === $apiKey) {
            throw new \InvalidArgumentException('Jira credentials require url, email and api_key');
        }

        $jiraClient = new JiraClient($url, $email, $apiKey);

        return new JiraAdapter($jiraClient, $this->serializer);
    }

    private function createMondayAdapter(UserCredential $credential): MondayAdapter
    {
        $apiKey = $credential->getApiKey();

        if (null === $apiKey) {
            throw new \InvalidArgumentException('Monday credentials require api_key');
        }

        $mondayClient = new MondayClient($apiKey);

        return new MondayAdapter($mondayClient, $this->serializer);
    }
}
