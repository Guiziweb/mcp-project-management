<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security\Voter;

use App\Admin\Infrastructure\Doctrine\Entity\McpSession;
use App\Admin\Infrastructure\Doctrine\Entity\Organization;
use App\Admin\Infrastructure\Doctrine\Entity\User;
use App\Admin\Infrastructure\Security\Voter\McpSessionVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Uid\Uuid;

final class McpSessionVoterTest extends TestCase
{
    private McpSessionVoter $voter;
    private Organization $org1;
    private Organization $org2;
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->voter = new McpSessionVoter();
        $this->now = new \DateTimeImmutable('2024-01-01 00:00:00');
        $this->org1 = $this->createOrganization(1, 'Org 1');
        $this->org2 = $this->createOrganization(2, 'Org 2');
    }

    public function testSupportsMcpSessionSubject(): void
    {
        $user = $this->createUser(1, $this->org1);
        $session = $this->createSession($user);
        $reflection = new \ReflectionMethod($this->voter, 'supports');

        $this->assertTrue($reflection->invoke($this->voter, McpSessionVoter::VIEW, $session));
        $this->assertTrue($reflection->invoke($this->voter, McpSessionVoter::DELETE, $session));
        $this->assertFalse($reflection->invoke($this->voter, 'INVALID', $session));
        $this->assertFalse($reflection->invoke($this->voter, McpSessionVoter::VIEW, new \stdClass()));
    }

    public function testSuperAdminCanDoEverything(): void
    {
        $superAdmin = $this->createUser(1, $this->org1, [User::ROLE_SUPER_ADMIN]);
        $otherUser = $this->createUser(2, $this->org2);
        $session = $this->createSession($otherUser);
        $token = $this->createToken($superAdmin);

        $this->assertTrue($this->vote($token, $session, McpSessionVoter::VIEW));
        $this->assertTrue($this->vote($token, $session, McpSessionVoter::DELETE));
    }

    public function testOrgAdminCanManageSessionsInSameOrg(): void
    {
        $orgAdmin = $this->createUser(1, $this->org1, [User::ROLE_ORG_ADMIN]);
        $regularUser = $this->createUser(2, $this->org1);
        $session = $this->createSession($regularUser);
        $token = $this->createToken($orgAdmin);

        $this->assertTrue($this->vote($token, $session, McpSessionVoter::VIEW));
        $this->assertTrue($this->vote($token, $session, McpSessionVoter::DELETE));
    }

    public function testOrgAdminCannotManageSessionsInOtherOrg(): void
    {
        $orgAdmin = $this->createUser(1, $this->org1, [User::ROLE_ORG_ADMIN]);
        $otherUser = $this->createUser(2, $this->org2);
        $session = $this->createSession($otherUser);
        $token = $this->createToken($orgAdmin);

        $this->assertFalse($this->vote($token, $session, McpSessionVoter::VIEW));
        $this->assertFalse($this->vote($token, $session, McpSessionVoter::DELETE));
    }

    public function testRegularUserCannotManageSessions(): void
    {
        $regularUser = $this->createUser(1, $this->org1);
        $otherUser = $this->createUser(2, $this->org1);
        $session = $this->createSession($otherUser);
        $token = $this->createToken($regularUser);

        $this->assertFalse($this->vote($token, $session, McpSessionVoter::VIEW));
        $this->assertFalse($this->vote($token, $session, McpSessionVoter::DELETE));
    }

    public function testRegularUserCannotManageOwnSession(): void
    {
        $regularUser = $this->createUser(1, $this->org1);
        $session = $this->createSession($regularUser);
        $token = $this->createToken($regularUser);

        // Regular users cannot manage sessions even their own (via admin panel)
        $this->assertFalse($this->vote($token, $session, McpSessionVoter::VIEW));
        $this->assertFalse($this->vote($token, $session, McpSessionVoter::DELETE));
    }

    public function testAnonymousUserCannotManageSessions(): void
    {
        $user = $this->createUser(1, $this->org1);
        $session = $this->createSession($user);
        $token = $this->createToken(null);

        $this->assertFalse($this->vote($token, $session, McpSessionVoter::VIEW));
        $this->assertFalse($this->vote($token, $session, McpSessionVoter::DELETE));
    }

    private function vote(TokenInterface $token, McpSession $subject, string $attribute): bool
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

    private function createSession(User $user): McpSession
    {
        return new McpSession(Uuid::v4(), $user, '{}', $this->now);
    }
}
