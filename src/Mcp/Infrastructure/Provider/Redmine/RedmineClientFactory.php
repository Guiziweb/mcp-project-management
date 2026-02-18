<?php

declare(strict_types=1);

namespace App\Mcp\Infrastructure\Provider\Redmine;

final readonly class RedmineClientFactory implements RedmineClientFactoryInterface
{
    public function create(string $url, string $apiKey): RedmineClientInterface
    {
        return new RedmineClient($url, $apiKey);
    }
}
