<?php

declare(strict_types=1);

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Shared\Infrastructure\Security;

use League\OAuth2\Client\Provider\Google;
use League\OAuth2\Client\Provider\GoogleUser;

/**
 * Handles Google OAuth2 authentication flow.
 * Wraps the league/oauth2-google library for easier integration.
 */
final class GoogleAuthService
{
    public function __construct(
        private readonly string $googleClientId,
        private readonly string $googleClientSecret,
        private readonly string $redirectUri,
    ) {
    }

    /**
     * Get the authorization URL to redirect the user to Google.
     *
     * @return array{url: string, state: string}
     */
    public function getAuthorizationUrl(?string $customRedirectUri = null): array
    {
        $provider = $this->createProvider($customRedirectUri);
        $authUrl = $provider->getAuthorizationUrl([
            'scope' => ['email', 'profile'],
        ]);

        $state = $provider->getState();

        return [
            'url' => $authUrl,
            'state' => $state,
        ];
    }

    /**
     * Handle the callback from Google and extract user information.
     *
     * @return array{email: string, name: string, id: string}
     *
     * @throws \RuntimeException if the code is invalid or the state doesn't match
     */
    public function handleCallback(string $code, string $state, string $expectedState, ?string $customRedirectUri = null): array
    {
        // Validate state to prevent CSRF
        if ($state !== $expectedState) {
            throw new \RuntimeException('Invalid state parameter (CSRF protection)');
        }

        try {
            $provider = $this->createProvider($customRedirectUri);

            // Exchange authorization code for access token
            $token = $provider->getAccessToken('authorization_code', [
                'code' => $code,
            ]);

            // Get user details using the access token
            /** @var GoogleUser $user */
            $user = $provider->getResourceOwner($token);

            return [
                'email' => $user->getEmail(),
                'name' => $user->getName(),
                'id' => $user->getId(),
            ];
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to authenticate with Google: '.$e->getMessage(), 0, $e);
        }
    }

    private function createProvider(?string $customRedirectUri = null): Google
    {
        return new Google([
            'clientId' => $this->googleClientId,
            'clientSecret' => $this->googleClientSecret,
            'redirectUri' => $customRedirectUri ?? $this->redirectUri,
        ]);
    }
}
