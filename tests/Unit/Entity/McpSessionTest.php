<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Admin\Infrastructure\Doctrine\Entity\McpSession;
use App\Admin\Infrastructure\Doctrine\Entity\Organization;
use App\Admin\Infrastructure\Doctrine\Entity\User;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class McpSessionTest extends TestCase
{
    private Organization $organization;
    private User $user;
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2024-01-15 10:00:00');
        $this->organization = new Organization('Test Org', 'test-org', $this->now);
        $this->user = new User('user@example.com', 'google123', $this->organization, $this->now);
    }

    public function testNewSessionHasCorrectDefaults(): void
    {
        $id = Uuid::v4();
        $session = new McpSession($id, $this->user, '{}', $this->now);

        $this->assertSame($id, $session->getId());
        $this->assertSame($this->user, $session->getUser());
        $this->assertSame($this->organization, $session->getOrganization());
        $this->assertSame('{}', $session->getData());
        $this->assertSame($this->now, $session->getCreatedAt());
        $this->assertSame($this->now, $session->getLastActivityAt());
    }

    public function testDataCanBeSet(): void
    {
        $session = new McpSession(Uuid::v4(), $this->user, '{}', $this->now);

        $newData = '{"key": "value"}';
        $session->setData($newData);

        $this->assertSame($newData, $session->getData());
    }

    public function testClientInfoCanBeSet(): void
    {
        $session = new McpSession(Uuid::v4(), $this->user, '{}', $this->now);

        $this->assertNull($session->getClientInfo());

        $session->setClientInfo('Claude Desktop 1.0');

        $this->assertSame('Claude Desktop 1.0', $session->getClientInfo());
    }

    public function testTouchUpdatesLastActivityAt(): void
    {
        $session = new McpSession(Uuid::v4(), $this->user, '{}', $this->now);

        $this->assertSame($this->now, $session->getLastActivityAt());

        $later = $this->now->modify('+30 minutes');
        $session->touch($later);

        $this->assertSame($later, $session->getLastActivityAt());
        // createdAt should not change
        $this->assertSame($this->now, $session->getCreatedAt());
    }

    public function testIsExpiredWithDefaultTtl(): void
    {
        $session = new McpSession(Uuid::v4(), $this->user, '{}', $this->now);

        // Default TTL is 3600 seconds (1 hour)
        // Not expired after 30 minutes
        $after30Min = $this->now->modify('+30 minutes');
        $this->assertFalse($session->isExpired(3600, $after30Min));

        // Not expired at exactly 1 hour
        $after1Hour = $this->now->modify('+1 hour');
        $this->assertFalse($session->isExpired(3600, $after1Hour));

        // Expired after 1 hour and 1 second
        $after1HourPlus = $this->now->modify('+3601 seconds');
        $this->assertTrue($session->isExpired(3600, $after1HourPlus));
    }

    public function testIsExpiredWithCustomTtl(): void
    {
        $session = new McpSession(Uuid::v4(), $this->user, '{}', $this->now);

        // Custom TTL of 5 minutes (300 seconds)
        $after4Min = $this->now->modify('+4 minutes');
        $this->assertFalse($session->isExpired(300, $after4Min));

        $after6Min = $this->now->modify('+6 minutes');
        $this->assertTrue($session->isExpired(300, $after6Min));
    }

    public function testTouchResetsExpiration(): void
    {
        $session = new McpSession(Uuid::v4(), $this->user, '{}', $this->now);

        // Touch at +50 minutes
        $touchTime = $this->now->modify('+50 minutes');
        $session->touch($touchTime);

        // Check at +70 minutes (20 min after touch) - should not be expired
        $checkTime = $this->now->modify('+70 minutes');
        $this->assertFalse($session->isExpired(3600, $checkTime));

        // Check at +2 hours (70 min after touch) - should be expired
        $laterCheck = $this->now->modify('+2 hours');
        $this->assertTrue($session->isExpired(3600, $laterCheck));
    }

    public function testOrganizationIsInheritedFromUser(): void
    {
        $otherOrg = new Organization('Other Org', 'other-org', $this->now);
        $otherUser = new User('other@example.com', 'google456', $otherOrg, $this->now);

        $session = new McpSession(Uuid::v4(), $otherUser, '{}', $this->now);

        $this->assertSame($otherOrg, $session->getOrganization());
    }
}
