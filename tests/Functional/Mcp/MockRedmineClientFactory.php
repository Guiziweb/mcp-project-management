<?php

declare(strict_types=1);

namespace App\Tests\Functional\Mcp;

use App\Mcp\Infrastructure\Provider\Redmine\RedmineClientFactoryInterface;
use App\Mcp\Infrastructure\Provider\Redmine\RedmineClientInterface;

final class MockRedmineClientFactory implements RedmineClientFactoryInterface
{
    private static ?RedmineClientInterface $mockClient = null;

    public static function setMockClient(RedmineClientInterface $client): void
    {
        self::$mockClient = $client;
    }

    public static function reset(): void
    {
        self::$mockClient = null;
    }

    public function create(string $url, string $apiKey): RedmineClientInterface
    {
        if (null === self::$mockClient) {
            throw new \RuntimeException('Mock client not configured. Call MockRedmineClientFactory::setMockClient() first.');
        }

        return self::$mockClient;
    }
}