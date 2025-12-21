<?php

declare(strict_types=1);

namespace App\Infrastructure\Monday;

use App\Domain\Port\PortMetadataInterface;

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

    public static function getCredentialFields(): array
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
