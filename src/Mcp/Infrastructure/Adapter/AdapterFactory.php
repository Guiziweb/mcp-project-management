<?php

declare(strict_types=1);

namespace App\Mcp\Infrastructure\Adapter;

use App\Mcp\Infrastructure\Provider\Redmine\RedmineAdapter;
use App\Mcp\Infrastructure\Provider\Redmine\RedmineClientFactoryInterface;
use App\Shared\Domain\UserCredential;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * Factory for creating user-specific Redmine adapters.
 */
final readonly class AdapterFactory
{
    public function __construct(
        private DenormalizerInterface $serializer,
        private RedmineClientFactoryInterface $redmineClientFactory,
    ) {
    }

    /**
     * Create an adapter for a specific user based on their credentials.
     *
     * @throws \InvalidArgumentException if credentials are invalid
     */
    public function createForUser(UserCredential $credential): RedmineAdapter
    {
        $url = $credential->getUrl();
        $apiKey = $credential->getApiKey();

        if (null === $url || null === $apiKey) {
            throw new \InvalidArgumentException('Redmine credentials require url and api_key');
        }

        $redmineClient = $this->redmineClientFactory->create($url, $apiKey);

        return new RedmineAdapter($redmineClient, $this->serializer);
    }
}
