<?php

declare(strict_types=1);

namespace App\Infrastructure\Provider;

use App\Domain\Provider\ProviderMetadataInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

/**
 * Registry of all available providers.
 *
 * Collects providers tagged with 'app.provider' and exposes their metadata.
 */
final class ProviderRegistry
{
    /** @var array<string, class-string<ProviderMetadataInterface>> */
    private array $providers = [];

    /**
     * @param iterable<ProviderMetadataInterface> $taggedProviders
     */
    public function __construct(
        #[TaggedIterator('app.provider')] iterable $taggedProviders,
    ) {
        foreach ($taggedProviders as $provider) {
            $key = $provider::getProviderKey();
            $this->providers[$key] = $provider::class;
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
        foreach ($this->providers as $key => $class) {
            $choices[$class::getProviderLabel()] = $key;
        }

        return $choices;
    }

    /**
     * Get credential fields for a specific provider.
     *
     * @return array<string, array{type: string, label: string, placeholder: string, help?: string, required?: bool}>|null
     */
    public function getCredentialFields(string $key): ?array
    {
        if (!isset($this->providers[$key])) {
            return null;
        }

        return $this->providers[$key]::getCredentialFields();
    }
}
