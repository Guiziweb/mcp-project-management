<?php

declare(strict_types=1);

namespace App\Mcp\Infrastructure\Provider\Redmine;

use App\Mcp\Infrastructure\Adapter\PortMetadataInterface;

/**
 * Metadata for the Redmine adapter.
 */
final class RedmineMetadata implements PortMetadataInterface
{
    public static function getAdapterKey(): string
    {
        return 'redmine';
    }

    public static function getAdapterLabel(): string
    {
        return 'Redmine';
    }

    public static function getDescription(): string
    {
        return 'Gestion de projet open source flexible et personnalisable';
    }

    public static function getUrlPlaceholder(): string
    {
        return 'https://redmine.votre-entreprise.com';
    }

    public static function getOrgFields(): array
    {
        return [
            'url' => [
                'type' => 'url',
                'label' => 'Redmine URL',
                'placeholder' => 'https://redmine.example.com',
                'help' => 'The full URL of your Redmine instance',
            ],
        ];
    }

    public static function getUserFields(): array
    {
        return [
            'api_key' => [
                'type' => 'text',
                'label' => 'API Key',
                'placeholder' => 'Your API key',
                'help' => 'My account → API access key (right column)',
            ],
        ];
    }

    public static function getIconPath(): string
    {
        return '/images/providers/redmine.svg';
    }
}
