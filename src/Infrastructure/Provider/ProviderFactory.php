<?php

declare(strict_types=1);

namespace App\Infrastructure\Provider;

use App\Domain\Model\UserCredential;
use App\Domain\Provider\TimeTrackingProviderInterface;
use App\Infrastructure\Jira\JiraProvider;
use App\Infrastructure\Jira\JiraService;
use App\Infrastructure\Redmine\RedmineProvider;
use App\Infrastructure\Redmine\RedmineService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * Factory for creating user-specific time tracking providers.
 *
 * Supports multiple providers: Redmine, Jira, etc.
 */
final readonly class ProviderFactory
{
    public function __construct(
        private DenormalizerInterface $serializer,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Create a provider for a specific user based on their credentials.
     *
     * @throws \InvalidArgumentException if provider type is not supported
     */
    public function createForUser(UserCredential $credential): TimeTrackingProviderInterface
    {
        return match ($credential->provider) {
            UserCredential::PROVIDER_REDMINE => $this->createRedmineProvider($credential),
            UserCredential::PROVIDER_JIRA => $this->createJiraProvider($credential),
            default => throw new \InvalidArgumentException(sprintf('Unsupported provider: %s', $credential->provider)),
        };
    }

    private function createRedmineProvider(UserCredential $credential): RedmineProvider
    {
        $redmineService = new RedmineService(
            $credential->url,
            $credential->apiKey,
            $this->logger
        );

        return new RedmineProvider($redmineService, $this->serializer);
    }

    private function createJiraProvider(UserCredential $credential): JiraProvider
    {
        if (null === $credential->email) {
            throw new \InvalidArgumentException('Jira credentials require an email address');
        }

        $jiraService = new JiraService(
            $credential->url,
            $credential->email,
            $credential->apiKey,
        );

        return new JiraProvider($jiraService);
    }
}
