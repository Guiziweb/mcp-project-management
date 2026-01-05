<?php

declare(strict_types=1);

namespace App\Mcp\Infrastructure\Provider\Jira;

use App\Mcp\Infrastructure\Adapter\PortMetadataInterface;

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
        return 'Jira Cloud';
    }

    public static function getDescription(): string
    {
        return 'Solution Atlassian pour les équipes agiles';
    }

    public static function getUrlPlaceholder(): string
    {
        return 'https://votre-org.atlassian.net';
    }

    public static function getOrgFields(): array
    {
        return [
            'url' => [
                'type' => 'url',
                'label' => 'Jira URL',
                'placeholder' => 'https://your-company.atlassian.net',
                'help' => 'Your Jira Cloud instance URL',
            ],
        ];
    }

    public static function getUserFields(): array
    {
        return [
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

    public static function getIconPath(): string
    {
        return '/images/providers/jira.svg';
    }
}
