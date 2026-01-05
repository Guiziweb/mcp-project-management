<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Twig;

use App\Mcp\Infrastructure\Adapter\AdapterRegistry;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig extension for provider-related helpers.
 */
final class ProviderExtension extends AbstractExtension
{
    public function __construct(
        private readonly AdapterRegistry $adapterRegistry,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('provider_icon', $this->getProviderIcon(...)),
        ];
    }

    /**
     * Get the icon path for a provider.
     *
     * Returns a fallback icon path if the provider is not found.
     */
    public function getProviderIcon(string $providerKey): string
    {
        $cards = $this->adapterRegistry->getProviderCards();

        return $cards[$providerKey]['icon'] ?? '/images/providers/default.svg';
    }
}
