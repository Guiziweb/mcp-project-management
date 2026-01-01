<?php

declare(strict_types=1);

namespace App\Mcp\Infrastructure\Provider\Redmine;

use Psr\Log\LoggerInterface;

final readonly class RedmineClientFactory implements RedmineClientFactoryInterface
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public function create(string $url, string $apiKey): RedmineClientInterface
    {
        return new RedmineClient($url, $apiKey, $this->logger);
    }
}