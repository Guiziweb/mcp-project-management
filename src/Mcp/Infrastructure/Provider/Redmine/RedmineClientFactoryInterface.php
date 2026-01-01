<?php

declare(strict_types=1);

namespace App\Mcp\Infrastructure\Provider\Redmine;

interface RedmineClientFactoryInterface
{
    public function create(string $url, string $apiKey): RedmineClientInterface;
}