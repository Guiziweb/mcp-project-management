<?php

declare(strict_types=1);

namespace App\Infrastructure\Jira;

use App\Domain\Port\PortMetadataInterface;

/**
 * Metadata for the Jira adapter.
 */
final class JiraMetadata implements PortMetadataInterface
{
    public static function getAdapterKey(): string
    {
        return 'jira';
    }

    public static function getAdapterLabel(): string
    {
        return 'Jira';
    }

    public static function getCredentialFields(): array
    {
        return [
            'url' => [
                'type' => 'url',
                'label' => 'Jira URL',
                'placeholder' => 'https://your-company.atlassian.net',
                'help' => 'Your Jira Cloud instance URL',
            ],
            'email' => [
                'type' => 'email',
                'label' => 'Email',
                'placeholder' => 'your-email@company.com',
                'help' => 'Your Atlassian account email',
            ],
            'api_key' => [
                'type' => 'text',
                'label' => 'API Token',
                'placeholder' => 'Your API token',
                'help' => 'Create at id.atlassian.com/manage-profile/security/api-tokens',
            ],
        ];
    }
}
