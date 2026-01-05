<?php

declare(strict_types=1);

namespace App\Mcp\Infrastructure\Adapter;

use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

/**
 * Registry of all available adapters.
 *
 * Collects adapters tagged with 'app.adapter' and exposes their metadata.
 */
final class AdapterRegistry
{
    /** @var array<string, class-string<PortMetadataInterface>> */
    private array $adapters = [];

    /**
     * @param iterable<PortMetadataInterface> $taggedAdapters
     */
    public function __construct(
        #[TaggedIterator('app.adapter')] iterable $taggedAdapters,
    ) {
        foreach ($taggedAdapters as $adapter) {
            $key = $adapter::getAdapterKey();
            $this->adapters[$key] = $adapter::class;
        }
    }

    /**
     * Get choices for a Symfony form ChoiceType.
     *
     * @return array<string, string> Label => key
     */
    public function getFormChoices(): array
    {
        $choices = [];
        foreach ($this->adapters as $key => $class) {
            $choices[$class::getAdapterLabel()] = $key;
        }

        return $choices;
    }

    /**
     * Get provider cards data for the signup UI.
     *
     * @return array<string, array{key: string, label: string, description: string, urlPlaceholder: string, requiresUrl: bool, icon: string}>
     */
    public function getProviderCards(): array
    {
        $cards = [];
        foreach ($this->adapters as $key => $class) {
            $urlPlaceholder = $class::getUrlPlaceholder();
            $cards[$key] = [
                'key' => $key,
                'label' => $class::getAdapterLabel(),
                'description' => $class::getDescription(),
                'urlPlaceholder' => $urlPlaceholder,
                'requiresUrl' => '' !== $urlPlaceholder,
                'icon' => $class::getIconPath(),
            ];
        }

        return $cards;
    }

    /**
     * Get organization-level fields for a specific adapter.
     *
     * @return array<string, array{type: string, label: string, placeholder: string, help?: string, required?: bool}>
     */
    public function getOrgFields(string $key): array
    {
        if (!isset($this->adapters[$key])) {
            return [];
        }

        return $this->adapters[$key]::getOrgFields();
    }

    /**
     * Get user-level fields for a specific adapter.
     *
     * @return array<string, array{type: string, label: string, placeholder: string, help?: string, required?: bool}>
     */
    public function getUserFields(string $key): array
    {
        if (!isset($this->adapters[$key])) {
            return [];
        }

        return $this->adapters[$key]::getUserFields();
    }
}
