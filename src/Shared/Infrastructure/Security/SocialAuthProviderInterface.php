<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Security;

/**
 * Interface for social authentication providers (Google, GitHub, etc.).
 *
 * Implementations wrap OAuth2 providers from league/oauth2-client.
 */
interface SocialAuthProviderInterface
{
    /**
     * Get the unique key for this provider.
     *
     * @return string e.g., 'google', 'github'
     */
    public function getKey(): string;

    /**
     * Get the authorization URL to redirect the user to the provider.
     *
     * @param string|null $customRedirectUri Override the default callback URL
     *
     * @return array{url: string, state: string}
     */
    public function getAuthorizationUrl(?string $customRedirectUri = null): array;

    /**
     * Handle the callback from the provider and extract user information.
     *
     * @param string      $code              Authorization code from provider
     * @param string      $state             State parameter from callback
     * @param string      $expectedState     Expected state (for CSRF validation)
     * @param string|null $customRedirectUri Must match the one used in getAuthorizationUrl
     *
     * @return array{email: string, name: string, id: string}
     *
     * @throws \RuntimeException if authentication fails
     */
    public function handleCallback(string $code, string $state, string $expectedState, ?string $customRedirectUri = null): array;
}
