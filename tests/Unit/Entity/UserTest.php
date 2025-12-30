<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Admin\Infrastructure\Doctrine\Entity\Organization;
use App\Admin\Infrastructure\Doctrine\Entity\User;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    private Organization $organization;
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2024-01-01 00:00:00');
        $this->organization = new Organization('Test Org', 'test-org', 'redmine', $this->now);
    }

    public function testNewUserIsPendingByDefault(): void
    {
        $user = new User('test@example.com', 'google123', $this->organization, $this->now);

        $this->assertTrue($user->isPending());
        $this->assertFalse($user->isApproved());
        $this->assertSame(User::STATUS_PENDING, $user->getStatus());
    }

    public function testApproveUser(): void
    {
        $user = new User('test@example.com', 'google123', $this->organization, $this->now);

        $user->approve();

        $this->assertFalse($user->isPending());
        $this->assertTrue($user->isApproved());
        $this->assertSame(User::STATUS_APPROVED, $user->getStatus());
    }

    public function testSetStatusWithValidValue(): void
    {
        $user = new User('test@example.com', 'google123', $this->organization, $this->now);

        $user->setStatus(User::STATUS_APPROVED);
        $this->assertTrue($user->isApproved());

        $user->setStatus(User::STATUS_PENDING);
        $this->assertTrue($user->isPending());
    }

    public function testSetStatusWithInvalidValueThrowsException(): void
    {
        $user = new User('test@example.com', 'google123', $this->organization, $this->now);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid status');

        $user->setStatus('invalid_status');
    }

    public function testSetRolesWithValidValues(): void
    {
        $user = new User('test@example.com', 'google123', $this->organization, $this->now);

        $user->setRoles([User::ROLE_ORG_ADMIN]);

        $this->assertTrue($user->isOrgAdmin());
        $this->assertFalse($user->isSuperAdmin());
        $this->assertTrue($user->isAdmin());
    }

    public function testSetRolesWithInvalidValueThrowsException(): void
    {
        $user = new User('test@example.com', 'google123', $this->organization, $this->now);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid role');

        $user->setRoles(['INVALID_ROLE']);
    }

    public function testCreatedAtCanBeInjected(): void
    {
        $fixedTime = new \DateTimeImmutable('2024-01-15 10:00:00');

        $user = new User('test@example.com', 'google123', $this->organization, $fixedTime);

        $this->assertSame($fixedTime, $user->getCreatedAt());
    }

    public function testUpdateLastSeenAt(): void
    {
        $user = new User('test@example.com', 'google123', $this->organization, $this->now);
        $this->assertNull($user->getLastSeenAt());

        $fixedTime = new \DateTimeImmutable('2024-01-15 12:00:00');
        $user->updateLastSeenAt($fixedTime);

        $this->assertSame($fixedTime, $user->getLastSeenAt());
    }

    public function testHasProviderCredentials(): void
    {
        $user = new User('test@example.com', 'google123', $this->organization, $this->now);

        $this->assertFalse($user->hasProviderCredentials());

        $user->setProviderCredentials('encrypted_data');

        $this->assertTrue($user->hasProviderCredentials());
    }
}
