<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security\Voter;

use App\Admin\Infrastructure\Doctrine\Entity\Organization;
use App\Admin\Infrastructure\Doctrine\Entity\User;
use App\Admin\Infrastructure\Security\Voter\UserVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

final class UserVoterTest extends TestCase
{
    private UserVoter $voter;
    private Organization $org1;
    private Organization $org2;
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->voter = new UserVoter();
        $this->now = new \DateTimeImmutable('2024-01-01 00:00:00');
        $this->org1 = $this->createOrganization(1, 'Org 1');
        $this->org2 = $this->createOrganization(2, 'Org 2');
    }

    public function testSupportsUserSubject(): void
    {
        $user = $this->createUser(1, $this->org1);
        $token = $this->createToken($user);

        // Use reflection to test supports() directly
        $reflection = new \ReflectionMethod($this->voter, 'supports');

        $this->assertTrue($reflection->invoke($this->voter, UserVoter::VIEW, $user));
        $this->assertTrue($reflection->invoke($this->voter, UserVoter::EDIT, $user));
        $this->assertTrue($reflection->invoke($this->voter, UserVoter::DELETE, $user));
        $this->assertFalse($reflection->invoke($this->voter, 'INVALID', $user));
        $this->assertFalse($reflection->invoke($this->voter, UserVoter::VIEW, new \stdClass()));
    }

    public function testSuperAdminCanDoEverything(): void
    {
        $superAdmin = $this->createUser(1, $this->org1, [User::ROLE_SUPER_ADMIN]);
        $targetUser = $this->createUser(2, $this->org2);
        $token = $this->createToken($superAdmin);

        $this->assertTrue($this->vote($token, $targetUser, UserVoter::VIEW));
        $this->assertTrue($this->vote($token, $targetUser, UserVoter::EDIT));
        $this->assertTrue($this->vote($token, $targetUser, UserVoter::DELETE));
    }

    public function testOrgAdminCanManageUsersInSameOrg(): void
    {
        $orgAdmin = $this->createUser(1, $this->org1, [User::ROLE_ORG_ADMIN]);
        $targetUser = $this->createUser(2, $this->org1);
        $token = $this->createToken($orgAdmin);

        $this->assertTrue($this->vote($token, $targetUser, UserVoter::VIEW));
        $this->assertTrue($this->vote($token, $targetUser, UserVoter::EDIT));
        $this->assertTrue($this->vote($token, $targetUser, UserVoter::DELETE));
    }

    public function testOrgAdminCannotManageUsersInOtherOrg(): void
    {
        $orgAdmin = $this->createUser(1, $this->org1, [User::ROLE_ORG_ADMIN]);
        $targetUser = $this->createUser(2, $this->org2);
        $token = $this->createToken($orgAdmin);

        $this->assertFalse($this->vote($token, $targetUser, UserVoter::VIEW));
        $this->assertFalse($this->vote($token, $targetUser, UserVoter::EDIT));
        $this->assertFalse($this->vote($token, $targetUser, UserVoter::DELETE));
    }

    public function testOrgAdminCannotDeleteThemselves(): void
    {
        $orgAdmin = $this->createUser(1, $this->org1, [User::ROLE_ORG_ADMIN]);
        $token = $this->createToken($orgAdmin);

        $this->assertTrue($this->vote($token, $orgAdmin, UserVoter::VIEW));
        $this->assertTrue($this->vote($token, $orgAdmin, UserVoter::EDIT));
        $this->assertFalse($this->vote($token, $orgAdmin, UserVoter::DELETE));
    }

    public function testOrgAdminCannotManageSuperAdmin(): void
    {
        $orgAdmin = $this->createUser(1, $this->org1, [User::ROLE_ORG_ADMIN]);
        $superAdmin = $this->createUser(2, $this->org1, [User::ROLE_SUPER_ADMIN]);
        $token = $this->createToken($orgAdmin);

        $this->assertFalse($this->vote($token, $superAdmin, UserVoter::VIEW));
        $this->assertFalse($this->vote($token, $superAdmin, UserVoter::EDIT));
        $this->assertFalse($this->vote($token, $superAdmin, UserVoter::DELETE));
    }

    public function testRegularUserCannotManageAnyone(): void
    {
        $regularUser = $this->createUser(1, $this->org1);
        $targetUser = $this->createUser(2, $this->org1);
        $token = $this->createToken($regularUser);

        $this->assertFalse($this->vote($token, $targetUser, UserVoter::VIEW));
        $this->assertFalse($this->vote($token, $targetUser, UserVoter::EDIT));
        $this->assertFalse($this->vote($token, $targetUser, UserVoter::DELETE));
    }

    public function testAnonymousUserCannotManageAnyone(): void
    {
        $targetUser = $this->createUser(1, $this->org1);
        $token = $this->createToken(null);

        $this->assertFalse($this->vote($token, $targetUser, UserVoter::VIEW));
        $this->assertFalse($this->vote($token, $targetUser, UserVoter::EDIT));
        $this->assertFalse($this->vote($token, $targetUser, UserVoter::DELETE));
    }

    private function vote(TokenInterface $token, User $subject, string $attribute): bool
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
        $org = new Organization($name, null, $this->now);

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
}
