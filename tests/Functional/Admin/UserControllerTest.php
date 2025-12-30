<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use App\Admin\Infrastructure\Doctrine\Entity\User;
use App\Tests\Functional\FunctionalTestCase;

final class UserControllerTest extends FunctionalTestCase
{
    // ===========================================
    // Multi-tenancy: OrgAdmin isolation
    // ===========================================

    public function testOrgAdminOnlySeesUsersFromOwnOrganization(): void
    {
        $org1 = $this->createOrganization('Org One');
        $org2 = $this->createOrganization('Org Two');

        $admin1 = $this->createUser($org1, 'admin1@example.com', [User::ROLE_ORG_ADMIN]);
        $this->createUser($org1, 'user1@org1.com');
        $this->createUser($org2, 'user2@org2.com');

        $this->loginAs($admin1);
        $crawler = $this->client->request('GET', '/admin/users');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('admin1@example.com', $crawler->text());
        $this->assertStringContainsString('user1@org1.com', $crawler->text());
        $this->assertStringNotContainsString('user2@org2.com', $crawler->text());
    }

    public function testOrgAdminCannotAccessUserFromOtherOrg(): void
    {
        $org1 = $this->createOrganization('Org One');
        $org2 = $this->createOrganization('Org Two');

        $admin1 = $this->createUser($org1, 'admin1@example.com', [User::ROLE_ORG_ADMIN]);
        $user2 = $this->createUser($org2, 'user2@org2.com');

        $this->loginAs($admin1);
        $this->client->request('GET', '/admin/users/'.$user2->getId().'/edit');

        // Voter denies access to users from other orgs
        $this->assertResponseStatusCodeSame(403);
    }

    public function testOrgAdminCannotDeleteUserFromOtherOrg(): void
    {
        $org1 = $this->createOrganization('Org One');
        $org2 = $this->createOrganization('Org Two');

        $admin1 = $this->createUser($org1, 'admin1@example.com', [User::ROLE_ORG_ADMIN]);
        $user2 = $this->createUser($org2, 'user2@org2.com');
        $user2Id = $user2->getId();

        $this->loginAs($admin1);
        $this->client->request('GET', '/admin/users/'.$user2Id.'/delete');

        // Voter denies access to users from other orgs
        $this->assertResponseStatusCodeSame(403);

        // Verify NOT deleted - disable filter to query across orgs
        $this->em->clear();
        $this->em->getFilters()->disable('organization');
        $stillExists = $this->em->find(User::class, $user2Id);
        $this->assertNotNull($stillExists);
    }

    // ===========================================
    // SuperAdmin: sees all organizations
    // ===========================================

    public function testSuperAdminSeesAllUsers(): void
    {
        $org1 = $this->createOrganization('Org One');
        $org2 = $this->createOrganization('Org Two');

        $superAdmin = $this->createUser($org1, 'super@example.com', [User::ROLE_SUPER_ADMIN]);
        $this->createUser($org1, 'user1@org1.com');
        $this->createUser($org2, 'user2@org2.com');

        $this->loginAs($superAdmin);
        $crawler = $this->client->request('GET', '/admin/users');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('user1@org1.com', $crawler->text());
        $this->assertStringContainsString('user2@org2.com', $crawler->text());
    }

    public function testSuperAdminCanEditUserFromAnyOrg(): void
    {
        $org1 = $this->createOrganization('Org One');
        $org2 = $this->createOrganization('Org Two');

        $superAdmin = $this->createUser($org1, 'super@example.com', [User::ROLE_SUPER_ADMIN]);
        $user2 = $this->createUser($org2, 'user2@org2.com');

        $this->loginAs($superAdmin);
        $this->client->request('GET', '/admin/users/'.$user2->getId().'/edit');

        $this->assertResponseIsSuccessful();
    }

    // ===========================================
    // Role escalation protection
    // ===========================================

    public function testOrgAdminCannotSeeSuperAdminRoleOption(): void
    {
        $org = $this->createOrganization();
        $admin = $this->createUser($org, 'admin@example.com', [User::ROLE_ORG_ADMIN]);
        $user = $this->createUser($org, 'user@example.com');

        $this->loginAs($admin);
        $crawler = $this->client->request('GET', '/admin/users/'.$user->getId().'/edit');

        $this->assertResponseIsSuccessful();
        // Super Admin option should NOT be visible
        $this->assertStringNotContainsString('Super Admin', $crawler->filter('form')->text());
    }

    public function testSuperAdminCanSeeSuperAdminRoleOption(): void
    {
        $org = $this->createOrganization();
        $superAdmin = $this->createUser($org, 'super@example.com', [User::ROLE_SUPER_ADMIN]);
        $user = $this->createUser($org, 'user@example.com');

        $this->loginAs($superAdmin);
        $crawler = $this->client->request('GET', '/admin/users/'.$user->getId().'/edit');

        $this->assertResponseIsSuccessful();
        // Super Admin option SHOULD be visible
        $this->assertStringContainsString('Super Admin', $crawler->filter('form')->text());
    }

    // ===========================================
    // User approval workflow
    // ===========================================

    public function testOrgAdminCanApprovePendingUser(): void
    {
        $org = $this->createOrganization();
        $admin = $this->createUser($org, 'admin@example.com', [User::ROLE_ORG_ADMIN]);
        $pendingUser = $this->createUser($org, 'pending@example.com', [], approved: false);

        $this->assertTrue($pendingUser->isPending());

        $this->loginAs($admin);
        $this->client->request('POST', '/admin/users/'.$pendingUser->getId().'/approve');

        $this->assertResponseRedirects('/admin/users');

        // Verify approved
        $this->em->clear();
        $approved = $this->em->find(User::class, $pendingUser->getId());
        $this->assertFalse($approved->isPending());
    }

    public function testOrgAdminCannotApproveUserFromOtherOrg(): void
    {
        $org1 = $this->createOrganization('Org One');
        $org2 = $this->createOrganization('Org Two');

        $admin1 = $this->createUser($org1, 'admin1@example.com', [User::ROLE_ORG_ADMIN]);
        $pendingUser2 = $this->createUser($org2, 'pending@org2.com', [], approved: false);
        $userId = $pendingUser2->getId();

        $this->loginAs($admin1);
        $this->client->request('POST', '/admin/users/'.$userId.'/approve');

        // Voter denies access to users from other orgs
        $this->assertResponseStatusCodeSame(403);

        // Verify NOT approved - disable filter to query across orgs
        $this->em->clear();
        $this->em->getFilters()->disable('organization');
        $stillPending = $this->em->find(User::class, $userId);
        $this->assertTrue($stillPending->isPending());
    }

    // ===========================================
    // Delete protection
    // ===========================================

    public function testOrgAdminCanDeleteUserFromOwnOrg(): void
    {
        $org = $this->createOrganization();
        $admin = $this->createUser($org, 'admin@example.com', [User::ROLE_ORG_ADMIN]);
        $user = $this->createUser($org, 'user@example.com');
        $userId = $user->getId();

        $this->loginAs($admin);
        $this->client->request('GET', '/admin/users/'.$userId.'/delete');

        $this->assertResponseRedirects('/admin/users');

        // Verify deleted
        $this->em->clear();
        $deleted = $this->em->find(User::class, $userId);
        $this->assertNull($deleted);
    }

    public function testOrgAdminCannotDeleteSelf(): void
    {
        $org = $this->createOrganization();
        $admin = $this->createUser($org, 'admin@example.com', [User::ROLE_ORG_ADMIN]);

        $this->loginAs($admin);
        $this->client->request('GET', '/admin/users/'.$admin->getId().'/delete');

        // Voter should deny self-deletion
        $this->assertResponseStatusCodeSame(403);

        // Verify NOT deleted
        $this->em->clear();
        $stillExists = $this->em->find(User::class, $admin->getId());
        $this->assertNotNull($stillExists);
    }
}
