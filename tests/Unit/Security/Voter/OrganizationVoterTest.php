<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security\Voter;

use App\Admin\Infrastructure\Doctrine\Entity\Organization;
use App\Admin\Infrastructure\Doctrine\Entity\User;
use App\Admin\Infrastructure\Security\Voter\OrganizationVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

final class OrganizationVoterTest extends TestCase
{
    private OrganizationVoter $voter;
    private Organization $org1;
    private Organization $org2;
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->voter = new OrganizationVoter();
        $this->now = new \DateTimeImmutable('2024-01-01 00:00:00');
        $this->org1 = $this->createOrganization(1, 'Org 1');
        $this->org2 = $this->createOrganization(2, 'Org 2');
    }

    public function testSupportsOrganizationSubject(): void
    {
        $reflection = new \ReflectionMethod($this->voter, 'supports');

        $this->assertTrue($reflection->invoke($this->voter, OrganizationVoter::VIEW, $this->org1));
        $this->assertTrue($reflection->invoke($this->voter, OrganizationVoter::EDIT, $this->org1));
        $this->assertTrue($reflection->invoke($this->voter, OrganizationVoter::DELETE, $this->org1));
        $this->assertTrue($reflection->invoke($this->voter, OrganizationVoter::CREATE, $this->org1));
        $this->assertFalse($reflection->invoke($this->voter, 'INVALID', $this->org1));
        $this->assertFalse($reflection->invoke($this->voter, OrganizationVoter::VIEW, new \stdClass()));
    }

    public function testSuperAdminCanDoEverything(): void
    {
        $superAdmin = $this->createUser(1, $this->org1, [User::ROLE_SUPER_ADMIN]);
        $token = $this->createToken($superAdmin);

        $this->assertTrue($this->vote($token, $this->org1, OrganizationVoter::VIEW));
        $this->assertTrue($this->vote($token, $this->org1, OrganizationVoter::EDIT));
        $this->assertTrue($this->vote($token, $this->org1, OrganizationVoter::DELETE));
        $this->assertTrue($this->vote($token, $this->org1, OrganizationVoter::CREATE));

        // Also on other org
        $this->assertTrue($this->vote($token, $this->org2, OrganizationVoter::VIEW));
        $this->assertTrue($this->vote($token, $this->org2, OrganizationVoter::EDIT));
    }

    public function testOrgAdminCanOnlyViewOwnOrganization(): void
    {
        $orgAdmin = $this->createUser(1, $this->org1, [User::ROLE_ORG_ADMIN]);
        $token = $this->createToken($orgAdmin);

        // Can VIEW own org
        $this->assertTrue($this->vote($token, $this->org1, OrganizationVoter::VIEW));

        // Cannot EDIT/DELETE/CREATE own org
        $this->assertFalse($this->vote($token, $this->org1, OrganizationVoter::EDIT));
        $this->assertFalse($this->vote($token, $this->org1, OrganizationVoter::DELETE));
        $this->assertFalse($this->vote($token, $this->org1, OrganizationVoter::CREATE));
    }

    public function testOrgAdminCannotViewOtherOrganization(): void
    {
        $orgAdmin = $this->createUser(1, $this->org1, [User::ROLE_ORG_ADMIN]);
        $token = $this->createToken($orgAdmin);

        $this->assertFalse($this->vote($token, $this->org2, OrganizationVoter::VIEW));
        $this->assertFalse($this->vote($token, $this->org2, OrganizationVoter::EDIT));
        $this->assertFalse($this->vote($token, $this->org2, OrganizationVoter::DELETE));
    }

    public function testRegularUserCannotDoAnything(): void
    {
        $regularUser = $this->createUser(1, $this->org1);
        $token = $this->createToken($regularUser);

        $this->assertFalse($this->vote($token, $this->org1, OrganizationVoter::VIEW));
        $this->assertFalse($this->vote($token, $this->org1, OrganizationVoter::EDIT));
        $this->assertFalse($this->vote($token, $this->org1, OrganizationVoter::DELETE));
        $this->assertFalse($this->vote($token, $this->org1, OrganizationVoter::CREATE));
    }

    public function testAnonymousUserCannotDoAnything(): void
    {
        $token = $this->createToken(null);

        $this->assertFalse($this->vote($token, $this->org1, OrganizationVoter::VIEW));
        $this->assertFalse($this->vote($token, $this->org1, OrganizationVoter::EDIT));
    }

    private function vote(TokenInterface $token, Organization $subject, string $attribute): bool
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
}
