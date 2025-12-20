<?php

declare(strict_types=1);

namespace App\Domain\Provider;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Interface for providers that expose their metadata.
 *
 * Used for dynamic form building and provider discovery.
 */
#[AutoconfigureTag('app.provider')]
interface ProviderMetadataInterface
{
    /**
     * Get the provider key (e.g., 'redmine', 'jira').
     */
    public static function getProviderKey(): string;

    /**
     * Get the display label (e.g., 'Redmine', 'Jira Cloud').
     */
    public static function getProviderLabel(): string;

    /**
     * Get the credential fields required by this provider.
     *
     * Each field is an array with:
     * - type: 'url', 'email', 'text', 'password'
     * - label: Display label
     * - placeholder: Input placeholder
     * - help: Help text (optional)
     * - required: Whether field is required (default: true)
     *
     * @return array<string, array{type: string, label: string, placeholder: string, help?: string, required?: bool}>
     */
    public static function getCredentialFields(): array;
}
