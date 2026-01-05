<?php

declare(strict_types=1);

namespace App\Mcp\Infrastructure\Adapter;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Interface for adapters that expose their metadata.
 *
 * Used for dynamic form building and adapter discovery.
 */
#[AutoconfigureTag('app.adapter')]
interface PortMetadataInterface
{
    /**
     * Get the adapter key (e.g., 'redmine', 'jira').
     */
    public static function getAdapterKey(): string;

    /**
     * Get the display label (e.g., 'Redmine', 'Jira Cloud').
     */
    public static function getAdapterLabel(): string;

    /**
     * Get a short description for the card UI.
     */
    public static function getDescription(): string;

    /**
     * Get the URL placeholder example.
     */
    public static function getUrlPlaceholder(): string;

    /**
     * Get organization-level fields (shared across all users).
     *
     * These are configured by the org admin (e.g., instance URL).
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
    public static function getOrgFields(): array;

    /**
     * Get user-level fields (personal credentials).
     *
     * These are configured by each user (e.g., API key, email).
     *
     * @return array<string, array{type: string, label: string, placeholder: string, help?: string, required?: bool}>
     */
    public static function getUserFields(): array;

    /**
     * Get the path to the provider's icon/logo.
     *
     * @return string Path relative to public/ (e.g., '/images/providers/redmine.svg')
     */
    public static function getIconPath(): string;
}
