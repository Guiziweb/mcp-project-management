<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Security;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Centralized OAuth session management.
 *
 * Handles session keys for social auth flow across different contexts:
 * - Signup flow (new organization creation)
 * - Invite flow (user joining via invite link)
 * - MCP OAuth flow (connecting AI assistant)
 * - Admin login flow
 *
 * Provider-agnostic: works with Google, GitHub, or any SocialAuthProviderInterface.
 */
final class OAuthSessionManager
{
    // Session keys (provider-agnostic)
    private const AUTH_STATE = 'auth_oauth_state';
    private const AUTH_EMAIL = 'auth_user_email';
    private const AUTH_NAME = 'auth_user_name';
    private const AUTH_ID = 'auth_user_id';
    private const AUTH_PROVIDER = 'auth_provider';

    // Flow-specific keys
    private const SIGNUP_FLOW = 'signup_flow';
    private const SIGNUP_USER = 'signup_auth_user';
    private const ADMIN_LOGIN = 'admin_login';
    private const INVITE_TOKEN = 'invite_token';
    private const OAUTH_CLIENT_ID = 'oauth_client_id';
    private const OAUTH_REDIRECT_URI = 'oauth_redirect_uri';
    private const OAUTH_STATE = 'oauth_state';

    public function __construct(
        private readonly SocialAuthProviderInterface $authProvider,
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * Get the current auth provider key.
     */
    public function getProviderKey(): string
    {
        return $this->authProvider->getKey();
    }

    /**
     * Start social auth flow and return redirect URL.
     *
     * @param string|null $callbackUrl Custom callback URL (null = default)
     *
     * @return string The authorization URL to redirect to
     */
    public function startAuth(?string $callbackUrl = null): string
    {
        $auth = $this->authProvider->getAuthorizationUrl($callbackUrl);
        $session = $this->getSession();
        $session->set(self::AUTH_STATE, $auth['state']);
        $session->set(self::AUTH_PROVIDER, $this->authProvider->getKey());

        return $auth['url'];
    }

    /**
     * Handle OAuth callback and return user data.
     *
     * @param string      $code        Authorization code from provider
     * @param string      $state       State parameter from callback
     * @param string|null $callbackUrl Custom callback URL used in startAuth
     *
     * @return array{email: string, name: string, id: string}
     *
     * @throws \RuntimeException If state validation fails or provider returns an error
     */
    public function handleCallback(string $code, string $state, ?string $callbackUrl = null): array
    {
        $expectedState = $this->getSession()->get(self::AUTH_STATE);

        if (!\is_string($expectedState)) {
            throw new \RuntimeException('Session expired, please start authorization again');
        }

        return $this->authProvider->handleCallback($code, $state, $expectedState, $callbackUrl);
    }

    // ========================================
    // Signup Flow
    // ========================================

    public function markAsSignupFlow(): void
    {
        $this->getSession()->set(self::SIGNUP_FLOW, true);
    }

    public function isSignupFlow(): bool
    {
        return (bool) $this->getSession()->get(self::SIGNUP_FLOW);
    }

    /**
     * @param array{email: string, name: string, id: string} $user
     */
    public function storeSignupUser(array $user): void
    {
        $this->getSession()->set(self::SIGNUP_USER, $user);
    }

    /**
     * @return array{email: string, name: string, id: string}|null
     */
    public function getSignupUser(): ?array
    {
        $user = $this->getSession()->get(self::SIGNUP_USER);

        if (!\is_array($user)) {
            return null;
        }

        // Validate expected structure
        if (!isset($user['email'], $user['name'], $user['id'])) {
            return null;
        }

        return [
            'email' => (string) $user['email'],
            'name' => (string) $user['name'],
            'id' => (string) $user['id'],
        ];
    }

    public function clearSignupFlow(): void
    {
        $session = $this->getSession();
        $session->remove(self::SIGNUP_FLOW);
        $session->remove(self::SIGNUP_USER);
        $session->remove(self::AUTH_STATE);
        $session->remove(self::AUTH_PROVIDER);
    }

    // ========================================
    // Admin Login Flow
    // ========================================

    public function markAsAdminLogin(): void
    {
        $this->getSession()->set(self::ADMIN_LOGIN, true);
    }

    public function isAdminLogin(): bool
    {
        return (bool) $this->getSession()->get(self::ADMIN_LOGIN);
    }

    public function clearAdminLogin(): void
    {
        $session = $this->getSession();
        $session->remove(self::ADMIN_LOGIN);
        $session->remove(self::AUTH_STATE);
        $session->remove(self::AUTH_PROVIDER);
    }

    // ========================================
    // Invite Flow
    // ========================================

    public function storeInviteToken(string $token): void
    {
        $this->getSession()->set(self::INVITE_TOKEN, $token);
    }

    public function getInviteToken(): ?string
    {
        $token = $this->getSession()->get(self::INVITE_TOKEN);

        return \is_string($token) ? $token : null;
    }

    /**
     * Store authenticated user data for invite flow.
     *
     * @param array{email: string, name: string, id: string} $user
     */
    public function storeInviteUser(array $user): void
    {
        $session = $this->getSession();
        $session->set(self::AUTH_EMAIL, $user['email']);
        $session->set(self::AUTH_NAME, $user['name']);
        $session->set(self::AUTH_ID, $user['id']);
    }

    /**
     * @return array{email: string, name: string, id: string}|null
     */
    public function getInviteUser(): ?array
    {
        $session = $this->getSession();
        $email = $session->get(self::AUTH_EMAIL);
        $name = $session->get(self::AUTH_NAME);
        $id = $session->get(self::AUTH_ID);

        if (!\is_string($email) || !\is_string($id)) {
            return null;
        }

        return [
            'email' => $email,
            'name' => \is_string($name) ? $name : '',
            'id' => $id,
        ];
    }

    public function clearInviteFlow(): void
    {
        $session = $this->getSession();
        $session->remove(self::INVITE_TOKEN);
        $session->remove(self::AUTH_STATE);
        $session->remove(self::AUTH_EMAIL);
        $session->remove(self::AUTH_NAME);
        $session->remove(self::AUTH_ID);
        $session->remove(self::AUTH_PROVIDER);
    }

    // ========================================
    // MCP OAuth Flow
    // ========================================

    public function storeMcpOAuthParams(string $clientId, string $redirectUri, string $state): void
    {
        $session = $this->getSession();
        $session->set(self::OAUTH_CLIENT_ID, $clientId);
        $session->set(self::OAUTH_REDIRECT_URI, $redirectUri);
        $session->set(self::OAUTH_STATE, $state);
    }

    /**
     * @return array{client_id: string, redirect_uri: string, state: string}|null
     */
    public function getMcpOAuthParams(): ?array
    {
        $session = $this->getSession();
        $clientId = $session->get(self::OAUTH_CLIENT_ID);
        $redirectUri = $session->get(self::OAUTH_REDIRECT_URI);
        $state = $session->get(self::OAUTH_STATE);

        if (!\is_string($redirectUri) || '' === $redirectUri) {
            return null;
        }

        return [
            'client_id' => \is_string($clientId) ? $clientId : '',
            'redirect_uri' => $redirectUri,
            'state' => \is_string($state) ? $state : '',
        ];
    }

    /**
     * Store authenticated user data for MCP OAuth flow.
     *
     * @param array{email: string, name: string, id: string} $user
     */
    public function storeMcpUser(array $user): void
    {
        $session = $this->getSession();
        $session->set(self::AUTH_EMAIL, $user['email']);
        $session->set(self::AUTH_NAME, $user['name']);
    }

    public function getMcpUserEmail(): ?string
    {
        $email = $this->getSession()->get(self::AUTH_EMAIL);

        return \is_string($email) ? $email : null;
    }

    public function getMcpUserName(): ?string
    {
        $name = $this->getSession()->get(self::AUTH_NAME);

        return \is_string($name) ? $name : null;
    }

    public function clearMcpOAuthFlow(): void
    {
        $session = $this->getSession();
        $session->remove(self::OAUTH_CLIENT_ID);
        $session->remove(self::OAUTH_REDIRECT_URI);
        $session->remove(self::OAUTH_STATE);
        $session->remove(self::AUTH_STATE);
        $session->remove(self::AUTH_EMAIL);
        $session->remove(self::AUTH_NAME);
        $session->remove(self::AUTH_PROVIDER);
    }

    /**
     * Get the session (for operations not covered by this manager).
     */
    public function getSession(): SessionInterface
    {
        return $this->requestStack->getSession();
    }
}
