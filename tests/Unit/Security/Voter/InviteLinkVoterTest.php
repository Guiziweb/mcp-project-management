<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security\Voter;

use App\Admin\Infrastructure\Doctrine\Entity\InviteLink;
use App\Admin\Infrastructure\Doctrine\Entity\Organization;
use App\Admin\Infrastructure\Doctrine\Entity\User;
use App\Admin\Infrastructure\Security\Voter\InviteLinkVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

final class InviteLinkVoterTest extends TestCase
{
    private InviteLinkVoter $voter;
    private Organization $org1;
    private Organization $org2;
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->voter = new InviteLinkVoter();
        $this->now = new \DateTimeImmutable('2024-01-01 00:00:00');
        $this->org1 = $this->createOrganization(1, 'Org 1');
        $this->org2 = $this->createOrganization(2, 'Org 2');
    }

    public function testSupportsInviteLinkSubject(): void
    {
        $admin = $this->createUser(1, $this->org1, [User::ROLE_ORG_ADMIN]);
        $invite = $this->createInviteLink($this->org1, $admin);
        $reflection = new \ReflectionMethod($this->voter, 'supports');

        $this->assertTrue($reflection->invoke($this->voter, InviteLinkVoter::VIEW, $invite));
        $this->assertTrue($reflection->invoke($this->voter, InviteLinkVoter::CREATE, $invite));
        $this->assertTrue($reflection->invoke($this->voter, InviteLinkVoter::DELETE, $invite));
        $this->assertFalse($reflection->invoke($this->voter, 'INVALID', $invite));
        $this->assertFalse($reflection->invoke($this->voter, InviteLinkVoter::VIEW, new \stdClass()));
    }

    public function testSuperAdminCanDoEverything(): void
    {
        $superAdmin = $this->createUser(1, $this->org1, [User::ROLE_SUPER_ADMIN]);
        $orgAdmin = $this->createUser(2, $this->org2, [User::ROLE_ORG_ADMIN]);
        $invite = $this->createInviteLink($this->org2, $orgAdmin);
        $token = $this->createToken($superAdmin);

        $this->assertTrue($this->vote($token, $invite, InviteLinkVoter::VIEW));
        $this->assertTrue($this->vote($token, $invite, InviteLinkVoter::CREATE));
        $this->assertTrue($this->vote($token, $invite, InviteLinkVoter::DELETE));
    }

    public function testOrgAdminCanManageInviteLinksInSameOrg(): void
    {
        $orgAdmin = $this->createUser(1, $this->org1, [User::ROLE_ORG_ADMIN]);
        $invite = $this->createInviteLink($this->org1, $orgAdmin);
        $token = $this->createToken($orgAdmin);

        $this->assertTrue($this->vote($token, $invite, InviteLinkVoter::VIEW));
        $this->assertTrue($this->vote($token, $invite, InviteLinkVoter::CREATE));
        $this->assertTrue($this->vote($token, $invite, InviteLinkVoter::DELETE));
    }

    public function testOrgAdminCannotManageInviteLinksInOtherOrg(): void
    {
        $orgAdmin1 = $this->createUser(1, $this->org1, [User::ROLE_ORG_ADMIN]);
        $orgAdmin2 = $this->createUser(2, $this->org2, [User::ROLE_ORG_ADMIN]);
        $invite = $this->createInviteLink($this->org2, $orgAdmin2);
        $token = $this->createToken($orgAdmin1);

        $this->assertFalse($this->vote($token, $invite, InviteLinkVoter::VIEW));
        $this->assertFalse($this->vote($token, $invite, InviteLinkVoter::CREATE));
        $this->assertFalse($this->vote($token, $invite, InviteLinkVoter::DELETE));
    }

    public function testRegularUserCannotManageInviteLinks(): void
    {
        $regularUser = $this->createUser(1, $this->org1);
        $orgAdmin = $this->createUser(2, $this->org1, [User::ROLE_ORG_ADMIN]);
        $invite = $this->createInviteLink($this->org1, $orgAdmin);
        $token = $this->createToken($regularUser);

        $this->assertFalse($this->vote($token, $invite, InviteLinkVoter::VIEW));
        $this->assertFalse($this->vote($token, $invite, InviteLinkVoter::CREATE));
        $this->assertFalse($this->vote($token, $invite, InviteLinkVoter::DELETE));
    }

    public function testAnonymousUserCannotManageInviteLinks(): void
    {
        $orgAdmin = $this->createUser(1, $this->org1, [User::ROLE_ORG_ADMIN]);
        $invite = $this->createInviteLink($this->org1, $orgAdmin);
        $token = $this->createToken(null);

        $this->assertFalse($this->vote($token, $invite, InviteLinkVoter::VIEW));
        $this->assertFalse($this->vote($token, $invite, InviteLinkVoter::DELETE));
    }

    private function vote(TokenInterface $token, InviteLink $subject, string $attribute): bool
    {
        $reflection = new \ReflectionMethod($this->voter, 'voteOnAttribute');

        return $reflection->invoke($this->voter, $attribute, $subject, $token);
    }

    private function createToken(?User $user): TokenInterface
    {
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        return $token;
    }

    private function createOrganization(int $id, string $name): Organization
    {
        $org = new Organization($name, null, 'redmine', $this->now);

        $reflection = new \ReflectionProperty(Organization::class, 'id');
        $reflection->setValue($org, $id);

        return $org;
    }

    /**
     * @param array<string> $roles
     */
    private function createUser(int $id, Organization $org, array $roles = []): User
    {
        $user = new User("user{$id}@example.com", "google{$id}", $org, $this->now);

        $reflection = new \ReflectionProperty(User::class, 'id');
        $reflection->setValue($user, $id);

        if (!empty($roles)) {
            $user->setRoles($roles);
        }

        return $user;
    }

    private function createInviteLink(Organization $org, User $createdBy): InviteLink
    {
        return new InviteLink($org, $createdBy, $this->now->modify('+7 days'), $this->now);
    }
}
