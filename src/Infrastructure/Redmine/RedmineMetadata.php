<?php

declare(strict_types=1);

namespace App\Infrastructure\Redmine;

use App\Domain\Provider\ProviderMetadataInterface;

/**
 * Metadata for the Redmine provider.
 */
final class RedmineMetadata implements ProviderMetadataInterface
{
    public static function getProviderKey(): string
    {
        return 'redmine';
    }

    public static function getProviderLabel(): string
    {
        return 'Redmine';
    }

    public static function getCredentialFields(): array
    {
        return [
            'url' => [
                'type' => 'url',
                'label' => 'Redmine URL',
                'placeholder' => 'https://redmine.example.com',
                'help' => 'The full URL of your Redmine instance',
            ],
            'api_key' => [
                'type' => 'text',
                'label' => 'API Key',
                'placeholder' => 'Your API key',
                'help' => 'My account → API access key (right column)',
            ],
        ];
    }
}
