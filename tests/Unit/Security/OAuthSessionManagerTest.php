<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Shared\Infrastructure\Security\OAuthSessionManager;
use App\Shared\Infrastructure\Security\SocialAuthProviderInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

final class OAuthSessionManagerTest extends TestCase
{
    private OAuthSessionManager $manager;
    private SocialAuthProviderInterface&MockObject $authProvider;
    private Session $session;

    protected function setUp(): void
    {
        $this->authProvider = $this->createMock(SocialAuthProviderInterface::class);
        $this->authProvider->method('getKey')->willReturn('google');

        $this->session = new Session(new MockArraySessionStorage());
        $request = new Request();
        $request->setSession($this->session);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $this->manager = new OAuthSessionManager($this->authProvider, $requestStack);
    }

    // ========================================
    // startAuth() tests
    // ========================================

    public function testStartAuthReturnsAuthorizationUrl(): void
    {
        $this->authProvider
            ->method('getAuthorizationUrl')
            ->willReturn(['url' => 'https://google.com/auth', 'state' => 'random-state']);

        $url = $this->manager->startAuth();

        $this->assertEquals('https://google.com/auth', $url);
    }

    public function testStartAuthStoresStateInSession(): void
    {
        $this->authProvider
            ->method('getAuthorizationUrl')
            ->willReturn(['url' => 'https://google.com/auth', 'state' => 'random-state-123']);

        $this->manager->startAuth();

        $this->assertEquals('random-state-123', $this->session->get('auth_oauth_state'));
        $this->assertEquals('google', $this->session->get('auth_provider'));
    }

    public function testStartAuthPassesCustomCallbackUrl(): void
    {
        $this->authProvider
            ->expects($this->once())
            ->method('getAuthorizationUrl')
            ->with('https://custom.callback/url')
            ->willReturn(['url' => 'https://google.com/auth', 'state' => 'state']);

        $this->manager->startAuth('https://custom.callback/url');
    }

    // ========================================
    // handleCallback() tests
    // ========================================

    public function testHandleCallbackReturnsUserData(): void
    {
        $this->session->set('auth_oauth_state', 'expected-state');

        $this->authProvider
            ->method('handleCallback')
            ->with('auth-code', 'expected-state', 'expected-state', null)
            ->willReturn(['email' => 'user@test.com', 'name' => 'Test User', 'id' => '123']);

        $user = $this->manager->handleCallback('auth-code', 'expected-state');

        $this->assertEquals('user@test.com', $user['email']);
        $this->assertEquals('Test User', $user['name']);
        $this->assertEquals('123', $user['id']);
    }

    public function testHandleCallbackThrowsWhenNoStateInSession(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Session expired');

        $this->manager->handleCallback('auth-code', 'some-state');
    }

    // ========================================
    // Signup Flow tests
    // ========================================

    public function testSignupFlowMarking(): void
    {
        $this->assertFalse($this->manager->isSignupFlow());

        $this->manager->markAsSignupFlow();

        $this->assertTrue($this->manager->isSignupFlow());
    }

    public function testStoreAndRetrieveSignupUser(): void
    {
        $user = ['email' => 'signup@test.com', 'name' => 'Signup User', 'id' => 'signup-123'];

        $this->manager->storeSignupUser($user);

        $retrieved = $this->manager->getSignupUser();
        $this->assertEquals($user, $retrieved);
    }

    public function testGetSignupUserReturnsNullWhenNotSet(): void
    {
        $this->assertNull($this->manager->getSignupUser());
    }

    public function testGetSignupUserReturnsNullForInvalidData(): void
    {
        $this->session->set('signup_auth_user', 'invalid-string');

        $this->assertNull($this->manager->getSignupUser());
    }

    public function testGetSignupUserReturnsNullForIncompleteData(): void
    {
        $this->session->set('signup_auth_user', ['email' => 'test@test.com']); // missing name and id

        $this->assertNull($this->manager->getSignupUser());
    }

    public function testClearSignupFlowRemovesAllRelatedKeys(): void
    {
        $this->manager->markAsSignupFlow();
        $this->manager->storeSignupUser(['email' => 'a', 'name' => 'b', 'id' => 'c']);
        $this->session->set('auth_oauth_state', 'state');
        $this->session->set('auth_provider', 'google');

        $this->manager->clearSignupFlow();

        $this->assertFalse($this->manager->isSignupFlow());
        $this->assertNull($this->manager->getSignupUser());
        $this->assertNull($this->session->get('auth_oauth_state'));
        $this->assertNull($this->session->get('auth_provider'));
    }

    // ========================================
    // Admin Login Flow tests
    // ========================================

    public function testAdminLoginFlowMarking(): void
    {
        $this->assertFalse($this->manager->isAdminLogin());

        $this->manager->markAsAdminLogin();

        $this->assertTrue($this->manager->isAdminLogin());
    }

    public function testClearAdminLoginRemovesAllRelatedKeys(): void
    {
        $this->manager->markAsAdminLogin();
        $this->session->set('auth_oauth_state', 'state');
        $this->session->set('auth_provider', 'google');

        $this->manager->clearAdminLogin();

        $this->assertFalse($this->manager->isAdminLogin());
        $this->assertNull($this->session->get('auth_oauth_state'));
        $this->assertNull($this->session->get('auth_provider'));
    }

    // ========================================
    // Invite Flow tests
    // ========================================

    public function testStoreAndRetrieveInviteToken(): void
    {
        $this->assertNull($this->manager->getInviteToken());

        $this->manager->storeInviteToken('invite-token-123');

        $this->assertEquals('invite-token-123', $this->manager->getInviteToken());
    }

    public function testStoreAndRetrieveInviteUser(): void
    {
        $user = ['email' => 'invite@test.com', 'name' => 'Invite User', 'id' => 'invite-123'];

        $this->manager->storeInviteUser($user);

        $retrieved = $this->manager->getInviteUser();
        $this->assertEquals($user, $retrieved);
    }

    public function testGetInviteUserReturnsNullWhenNotSet(): void
    {
        $this->assertNull($this->manager->getInviteUser());
    }

    public function testGetInviteUserHandlesMissingName(): void
    {
        $this->session->set('auth_user_email', 'test@test.com');
        $this->session->set('auth_user_id', '123');
        // name not set

        $user = $this->manager->getInviteUser();

        $this->assertEquals('test@test.com', $user['email']);
        $this->assertEquals('', $user['name']); // defaults to empty string
        $this->assertEquals('123', $user['id']);
    }

    public function testClearInviteFlowRemovesAllRelatedKeys(): void
    {
        $this->manager->storeInviteToken('token');
        $this->manager->storeInviteUser(['email' => 'a', 'name' => 'b', 'id' => 'c']);
        $this->session->set('auth_oauth_state', 'state');
        $this->session->set('auth_provider', 'google');

        $this->manager->clearInviteFlow();

        $this->assertNull($this->manager->getInviteToken());
        $this->assertNull($this->manager->getInviteUser());
        $this->assertNull($this->session->get('auth_oauth_state'));
        $this->assertNull($this->session->get('auth_provider'));
    }

    // ========================================
    // MCP OAuth Flow tests
    // ========================================

    public function testStoreAndRetrieveMcpOAuthParams(): void
    {
        $this->assertNull($this->manager->getMcpOAuthParams());

        $this->manager->storeMcpOAuthParams('client-123', 'https://redirect.uri', 'oauth-state');

        $params = $this->manager->getMcpOAuthParams();
        $this->assertEquals('client-123', $params['client_id']);
        $this->assertEquals('https://redirect.uri', $params['redirect_uri']);
        $this->assertEquals('oauth-state', $params['state']);
    }

    public function testGetMcpOAuthParamsReturnsNullWhenRedirectUriMissing(): void
    {
        $this->session->set('oauth_client_id', 'client');
        $this->session->set('oauth_state', 'state');
        // redirect_uri not set

        $this->assertNull($this->manager->getMcpOAuthParams());
    }

    public function testGetMcpOAuthParamsReturnsNullWhenRedirectUriEmpty(): void
    {
        $this->manager->storeMcpOAuthParams('client', '', 'state');

        $this->assertNull($this->manager->getMcpOAuthParams());
    }

    public function testStoreAndRetrieveMcpUser(): void
    {
        $this->manager->storeMcpUser(['email' => 'mcp@test.com', 'name' => 'MCP User', 'id' => 'mcp-123']);

        $this->assertEquals('mcp@test.com', $this->manager->getMcpUserEmail());
        $this->assertEquals('MCP User', $this->manager->getMcpUserName());
    }

    public function testGetMcpUserEmailReturnsNullWhenNotSet(): void
    {
        $this->assertNull($this->manager->getMcpUserEmail());
    }

    public function testGetMcpUserNameReturnsNullWhenNotSet(): void
    {
        $this->assertNull($this->manager->getMcpUserName());
    }

    public function testClearMcpOAuthFlowRemovesAllRelatedKeys(): void
    {
        $this->manager->storeMcpOAuthParams('client', 'redirect', 'state');
        $this->manager->storeMcpUser(['email' => 'a', 'name' => 'b', 'id' => 'c']);
        $this->session->set('auth_oauth_state', 'state');
        $this->session->set('auth_provider', 'google');

        $this->manager->clearMcpOAuthFlow();

        $this->assertNull($this->manager->getMcpOAuthParams());
        $this->assertNull($this->manager->getMcpUserEmail());
        $this->assertNull($this->manager->getMcpUserName());
        $this->assertNull($this->session->get('auth_oauth_state'));
        $this->assertNull($this->session->get('auth_provider'));
    }

    // ========================================
    // getProviderKey() test
    // ========================================

    public function testGetProviderKeyReturnsAuthProviderKey(): void
    {
        $this->assertEquals('google', $this->manager->getProviderKey());
    }
}