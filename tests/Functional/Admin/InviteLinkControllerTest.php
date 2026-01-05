<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use App\Admin\Infrastructure\Doctrine\Entity\InviteLink;
use App\Admin\Infrastructure\Doctrine\Entity\User;
use App\Tests\Functional\FunctionalTestCase;

final class InviteLinkControllerTest extends FunctionalTestCase
{
    // ===========================================
    // List invites
    // ===========================================

    public function testListInvitesRequiresAuthentication(): void
    {
        $this->client->request('GET', '/admin/invites');

        $this->assertResponseRedirects('/admin/login');
    }

    public function testOrgAdminCanListInvites(): void
    {
        $this->loginAsOrgAdmin();

        $this->client->request('GET', '/admin/invites');

        $this->assertResponseIsSuccessful();
    }

    public function testOrgAdminOnlySeesInvitesFromOwnOrganization(): void
    {
        // Setup: 2 orgs with invites
        $org1 = $this->createOrganization('Org One');
        $org2 = $this->createOrganization('Org Two');

        $admin1 = $this->createUser($org1, 'admin1@example.com', [User::ROLE_ORG_ADMIN]);
        $admin2 = $this->createUser($org2, 'admin2@example.com', [User::ROLE_ORG_ADMIN]);

        // Create invites
        $this->createInviteLink($org1, $admin1, 'Org1 Invite 1');
        $this->createInviteLink($org1, $admin1, 'Org1 Invite 2');
        $this->createInviteLink($org2, $admin2, 'Org2 Invite');

        // Login as org1 admin
        $this->loginAs($admin1);
        $crawler = $this->client->request('GET', '/admin/invites');

        $this->assertResponseIsSuccessful();

        // Should see only org1's invites
        $this->assertStringContainsString('Org1 Invite 1', $crawler->text());
        $this->assertStringContainsString('Org1 Invite 2', $crawler->text());
        $this->assertStringNotContainsString('Org2 Invite', $crawler->text());
    }

    public function testSuperAdminSeesAllInvites(): void
    {
        // Setup
        $org1 = $this->createOrganization('Org One');
        $org2 = $this->createOrganization('Org Two');

        $superAdmin = $this->createUser($org1, 'super@example.com', [User::ROLE_SUPER_ADMIN]);
        $admin2 = $this->createUser($org2, 'admin2@example.com', [User::ROLE_ORG_ADMIN]);

        $this->createInviteLink($org1, $superAdmin, 'Org1 Invite');
        $this->createInviteLink($org2, $admin2, 'Org2 Invite');

        $this->loginAs($superAdmin);
        $crawler = $this->client->request('GET', '/admin/invites');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Org1 Invite', $crawler->text());
        $this->assertStringContainsString('Org2 Invite', $crawler->text());
    }

    // ===========================================
    // Create invite
    // ===========================================

    public function testCreateInviteFormIsDisplayed(): void
    {
        $this->loginAsOrgAdmin();

        $crawler = $this->client->request('GET', '/admin/invites/create');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form');
        $this->assertSelectorExists('input[name*="label"]');
        $this->assertSelectorExists('input[name*="expiresAt"]');
    }

    public function testCreateInviteWithLabel(): void
    {
        $admin = $this->loginAsOrgAdmin();

        $crawler = $this->client->request('GET', '/admin/invites/create');
        $form = $crawler->selectButton('Create Link')->form();

        $form['form[label]'] = 'Backend Team';
        $form['form[expiresAt]'] = '2024-02-15T10:00';

        $this->client->submit($form);

        $this->assertResponseRedirects('/admin/invites');

        // Verify invite was created
        $invite = $this->em->getRepository(InviteLink::class)->findOneBy(['label' => 'Backend Team']);
        $this->assertNotNull($invite);
        $this->assertSame($admin->getOrganization()->getId(), $invite->getOrganization()->getId());
        $this->assertSame($admin->getId(), $invite->getCreatedBy()->getId());
    }

    public function testCreateInviteWithMaxUses(): void
    {
        $this->loginAsOrgAdmin();

        $crawler = $this->client->request('GET', '/admin/invites/create');
        $form = $crawler->selectButton('Create Link')->form();

        $form['form[label]'] = 'Limited Invite';
        $form['form[expiresAt]'] = '2024-02-15T10:00';
        $form['form[maxUses]'] = '5';

        $this->client->submit($form);

        $this->assertResponseRedirects('/admin/invites');

        $invite = $this->em->getRepository(InviteLink::class)->findOneBy(['label' => 'Limited Invite']);
        $this->assertNotNull($invite);
        $this->assertSame(5, $invite->getMaxUses());
    }

    public function testCreatedInviteBelongsToAdminsOrganization(): void
    {
        $org = $this->createOrganization('My Org');
        $admin = $this->createUser($org, 'admin@example.com', [User::ROLE_ORG_ADMIN]);
        $this->loginAs($admin);

        $crawler = $this->client->request('GET', '/admin/invites/create');
        $form = $crawler->selectButton('Create Link')->form();
        $form['form[expiresAt]'] = '2024-02-15T10:00';

        $this->client->submit($form);

        $invite = $this->em->getRepository(InviteLink::class)->findOneBy([]);
        $this->assertSame('My Org', $invite->getOrganization()->getName());
    }

    // ===========================================
    // Delete invite
    // ===========================================

    public function testOrgAdminCanDeleteOwnOrgInvite(): void
    {
        $org = $this->createOrganization();
        $admin = $this->createUser($org, 'admin@example.com', [User::ROLE_ORG_ADMIN]);
        $invite = $this->createInviteLink($org, $admin, 'To Delete');

        $this->loginAs($admin);
        $crawler = $this->client->request('GET', '/admin/invites');

        // Find and submit the delete form for our invite
        $form = $crawler->filter('form[action$="/'.$invite->getToken().'/delete"]')->form();
        $this->client->submit($form);

        $this->assertResponseRedirects('/admin/invites');

        // Verify deleted
        $this->em->clear();
        $deleted = $this->em->getRepository(InviteLink::class)->findOneBy(['label' => 'To Delete']);
        $this->assertNull($deleted);
    }

    public function testOrgAdminCannotDeleteOtherOrgInvite(): void
    {
        $org1 = $this->createOrganization('Org One');
        $org2 = $this->createOrganization('Org Two');

        $admin1 = $this->createUser($org1, 'admin1@example.com', [User::ROLE_ORG_ADMIN]);
        $admin2 = $this->createUser($org2, 'admin2@example.com', [User::ROLE_ORG_ADMIN]);

        $invite = $this->createInviteLink($org2, $admin2, 'Org2 Invite');
        $token = (string) $invite->getToken();

        $this->loginAs($admin1);
        $crawler = $this->client->request('GET', '/admin/invites');

        // Org1 admin shouldn't see org2's invites, so no delete form should exist
        $forms = $crawler->filter('form[action$="/'.$token.'/delete"]');
        $this->assertCount(0, $forms, 'Org admin should not see other org invites');

        // Verify NOT deleted - disable filter to query across orgs
        $this->em->clear();
        $this->em->getFilters()->disable('organization');
        $stillExists = $this->em->find(InviteLink::class, $token);
        $this->assertNotNull($stillExists);
    }

    public function testSuperAdminCanDeleteAnyInvite(): void
    {
        $org1 = $this->createOrganization('Org One');
        $org2 = $this->createOrganization('Org Two');

        $superAdmin = $this->createUser($org1, 'super@example.com', [User::ROLE_SUPER_ADMIN]);
        $admin2 = $this->createUser($org2, 'admin2@example.com', [User::ROLE_ORG_ADMIN]);

        $invite = $this->createInviteLink($org2, $admin2, 'Org2 Invite');

        $this->loginAs($superAdmin);
        $crawler = $this->client->request('GET', '/admin/invites');

        // Super admin can see all invites, find and submit the delete form
        $form = $crawler->filter('form[action$="/'.$invite->getToken().'/delete"]')->form();
        $this->client->submit($form);

        $this->assertResponseRedirects('/admin/invites');

        // Verify deleted
        $this->em->clear();
        $deleted = $this->em->getRepository(InviteLink::class)->findOneBy(['label' => 'Org2 Invite']);
        $this->assertNull($deleted);
    }

    // ===========================================
    // Helpers
    // ===========================================

    private function createInviteLink(
        \App\Admin\Infrastructure\Doctrine\Entity\Organization $org,
        User $createdBy,
        string $label,
    ): InviteLink {
        $invite = new InviteLink($org, $createdBy, $this->now->modify('+7 days'), $this->now);
        $invite->setLabel($label);
        $this->em->persist($invite);
        $this->em->flush();

        return $invite;
    }
}
