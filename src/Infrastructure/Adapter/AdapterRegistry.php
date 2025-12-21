<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapter;

use App\Domain\Port\PortMetadataInterface;
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
     * Get credential fields for a specific adapter.
     *
     * @return array<string, array{type: string, label: string, placeholder: string, help?: string, required?: bool}>|null
     */
    public function getCredentialFields(string $key): ?array
    {
        if (!isset($this->adapters[$key])) {
            return null;
        }

        return $this->adapters[$key]::getCredentialFields();
    }
}
