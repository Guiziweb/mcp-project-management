<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use App\Admin\Infrastructure\Doctrine\Entity\McpSession;
use App\Admin\Infrastructure\Doctrine\Entity\User;
use App\Tests\Functional\FunctionalTestCase;
use Symfony\Component\Uid\Uuid;

final class DashboardControllerTest extends FunctionalTestCase
{
    // ===========================================
    // Authentication & Authorization
    // ===========================================

    public function testAnonymousUserIsRedirectedToLogin(): void
    {
        $this->client->request('GET', '/admin');

        $this->assertResponseRedirects('/admin/login');
    }

    public function testRegularUserCannotAccessDashboard(): void
    {
        $org = $this->createOrganization();
        $regularUser = $this->createUser($org, 'user@example.com', [User::ROLE_USER]);
        $this->loginAs($regularUser);

        $this->client->request('GET', '/admin');

        $this->assertResponseRedirects('/admin/login');
    }

    public function testOrgAdminCanAccessDashboard(): void
    {
        $this->loginAsOrgAdmin();

        $this->client->request('GET', '/admin');

        $this->assertResponseIsSuccessful();
    }

    public function testSuperAdminCanAccessDashboard(): void
    {
        $this->loginAsSuperAdmin();

        $this->client->request('GET', '/admin');

        $this->assertResponseIsSuccessful();
    }

    // ===========================================
    // Multi-tenancy: OrgAdmin isolation
    // ===========================================

    public function testOrgAdminOnlySeesUsersFromOwnOrganization(): void
    {
        // Setup: 2 organizations with users
        $org1 = $this->createOrganization('Org One');
        $org2 = $this->createOrganization('Org Two');

        $admin1 = $this->createUser($org1, 'admin1@example.com', [User::ROLE_ORG_ADMIN]);
        $this->createUser($org1, 'user1a@example.com');
        $this->createUser($org1, 'user1b@example.com');

        $this->createUser($org2, 'admin2@example.com', [User::ROLE_ORG_ADMIN]);
        $this->createUser($org2, 'user2a@example.com');

        // Login as org1 admin
        $this->loginAs($admin1);
        $crawler = $this->client->request('GET', '/admin');

        // Should see 3 users (admin1 + user1a + user1b), NOT 5
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('p.text-3xl', '3');
    }

    public function testOrgAdminOnlySeesSessionsFromOwnOrganization(): void
    {
        // Setup: 2 organizations with sessions
        $org1 = $this->createOrganization('Org One');
        $org2 = $this->createOrganization('Org Two');

        $admin1 = $this->createUser($org1, 'admin1@example.com', [User::ROLE_ORG_ADMIN]);
        $user1 = $this->createUser($org1, 'user1@example.com');

        $user2 = $this->createUser($org2, 'user2@example.com');

        // Create sessions: 2 for org1, 1 for org2
        $this->createSession($user1);
        $this->createSession($admin1);
        $this->createSession($user2);

        // Login as org1 admin
        $this->loginAs($admin1);
        $crawler = $this->client->request('GET', '/admin');

        // Should see 2 sessions, NOT 3
        $this->assertResponseIsSuccessful();
        $statsCards = $crawler->filter('p.text-3xl');
        $sessionCount = $statsCards->eq(1)->text(); // Second card is sessions
        $this->assertSame('2', $sessionCount);
    }

    // ===========================================
    // SuperAdmin: sees all organizations
    // ===========================================

    public function testSuperAdminSeesAllUsersAcrossOrganizations(): void
    {
        // Setup: 2 organizations with users
        $org1 = $this->createOrganization('Org One');
        $org2 = $this->createOrganization('Org Two');

        $superAdmin = $this->createUser($org1, 'super@example.com', [User::ROLE_SUPER_ADMIN]);
        $this->createUser($org1, 'user1@example.com');
        $this->createUser($org2, 'user2@example.com');
        $this->createUser($org2, 'user3@example.com');

        // Login as super admin
        $this->loginAs($superAdmin);
        $crawler = $this->client->request('GET', '/admin');

        // Should see ALL 4 users
        $this->assertResponseIsSuccessful();
        $userCount = $crawler->filter('p.text-3xl')->eq(0)->text();
        $this->assertSame('4', $userCount);
    }

    public function testSuperAdminSeesAllSessionsAcrossOrganizations(): void
    {
        // Setup: 2 organizations with sessions
        $org1 = $this->createOrganization('Org One');
        $org2 = $this->createOrganization('Org Two');

        $superAdmin = $this->createUser($org1, 'super@example.com', [User::ROLE_SUPER_ADMIN]);
        $user1 = $this->createUser($org1, 'user1@example.com');
        $user2 = $this->createUser($org2, 'user2@example.com');

        // Create sessions in both orgs
        $this->createSession($user1);
        $this->createSession($user2);
        $this->createSession($user2);

        // Login as super admin
        $this->loginAs($superAdmin);
        $crawler = $this->client->request('GET', '/admin');

        // Should see ALL 3 sessions
        $this->assertResponseIsSuccessful();
        $sessionCount = $crawler->filter('p.text-3xl')->eq(1)->text();
        $this->assertSame('3', $sessionCount);
    }

    // ===========================================
    // Active sessions count
    // ===========================================

    public function testActiveSessionCountOnlyIncludesRecentSessions(): void
    {
        $org = $this->createOrganization();
        $admin = $this->createUser($org, 'admin@example.com', [User::ROLE_ORG_ADMIN]);
        $user = $this->createUser($org, 'user@example.com');

        // Create 2 active sessions (recent)
        $this->createSession($user);
        $this->createSession($admin);

        // Create 1 expired session (old activity)
        $oldSession = new McpSession(Uuid::v4(), $user, '{}', $this->now->modify('-1 hour'));
        // Manually set lastActivityAt to old time
        $reflection = new \ReflectionProperty(McpSession::class, 'lastActivityAt');
        $reflection->setValue($oldSession, $this->now->modify('-10 minutes'));
        $this->em->persist($oldSession);
        $this->em->flush();

        $this->loginAs($admin);
        $crawler = $this->client->request('GET', '/admin');

        // Total sessions: 3, Active: 2
        $this->assertResponseIsSuccessful();
        $sessionCount = $crawler->filter('p.text-3xl')->eq(1)->text();
        $activeCount = $crawler->filter('p.text-3xl')->eq(2)->text();

        $this->assertSame('3', $sessionCount);
        $this->assertSame('2', $activeCount);
    }

    // ===========================================
    // Organization info displayed
    // ===========================================

    public function testDashboardDisplaysOrganizationInfo(): void
    {
        $org = $this->createOrganization('Acme Corp');
        $org->setProviderUrl('https://redmine.acme.com');
        $this->em->flush();

        $admin = $this->createUser($org, 'admin@example.com', [User::ROLE_ORG_ADMIN]);
        $this->loginAs($admin);

        $crawler = $this->client->request('GET', '/admin');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Acme Corp');
        $this->assertSelectorTextContains('body', 'Redmine');
        $this->assertSelectorTextContains('body', 'https://redmine.acme.com');
    }

    // ===========================================
    // Helpers
    // ===========================================

    private function createSession(User $user): McpSession
    {
        $session = new McpSession(Uuid::v4(), $user, '{}', $this->now);
        $this->em->persist($session);
        $this->em->flush();

        return $session;
    }
}
