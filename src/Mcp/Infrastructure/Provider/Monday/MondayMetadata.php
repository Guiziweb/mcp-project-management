<?php

declare(strict_types=1);

namespace App\Mcp\Infrastructure\Provider\Monday;

use App\Mcp\Infrastructure\Adapter\PortMetadataInterface;

/**
 * Metadata for the Monday.com adapter.
 */
final class MondayMetadata implements PortMetadataInterface
{
    public static function getAdapterKey(): string
    {
        return 'monday';
    }

    public static function getAdapterLabel(): string
    {
        return 'Monday.com';
    }

    public static function getDescription(): string
    {
        return 'Plateforme de gestion de travail visuelle et collaborative';
    }

    public static function getUrlPlaceholder(): string
    {
        return ''; // Monday.com is SaaS, no custom URL needed
    }

    public static function getOrgFields(): array
    {
        // Monday.com is a hosted SaaS, no org-level config needed
        return [];
    }

    public static function getUserFields(): array
    {
        return [
            'api_key' => [
                'type' => 'text',
                'label' => 'API Token',
                'placeholder' => 'Your Monday.com API token',
                'help' => 'Monday.com → Profile → Developers → My Access Tokens',
            ],
        ];
    }
}
