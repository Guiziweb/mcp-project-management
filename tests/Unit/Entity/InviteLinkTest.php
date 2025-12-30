<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Admin\Infrastructure\Doctrine\Entity\InviteLink;
use App\Admin\Infrastructure\Doctrine\Entity\Organization;
use App\Admin\Infrastructure\Doctrine\Entity\User;
use PHPUnit\Framework\TestCase;

final class InviteLinkTest extends TestCase
{
    private Organization $organization;
    private User $admin;
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2024-01-15 10:00:00');
        $this->organization = new Organization('Test Org', 'test-org', 'redmine', $this->now);
        $this->admin = new User('admin@example.com', 'google123', $this->organization, $this->now);
    }

    public function testNewInviteLinkIsActiveByDefault(): void
    {
        $expiresAt = $this->now->modify('+7 days');
        $invite = new InviteLink($this->organization, $this->admin, $expiresAt, $this->now);

        $this->assertTrue($invite->isActive());
        $this->assertSame(0, $invite->getUsesCount());
        $this->assertNull($invite->getMaxUses());
    }

    public function testInviteLinkIsValidWhenActiveAndNotExpired(): void
    {
        $expiresAt = $this->now->modify('+7 days');
        $invite = new InviteLink($this->organization, $this->admin, $expiresAt, $this->now);

        $this->assertTrue($invite->isValid($this->now));
        $this->assertTrue($invite->isValid($this->now->modify('+6 days')));
    }

    public function testInviteLinkIsInvalidWhenExpired(): void
    {
        $expiresAt = $this->now->modify('+7 days');
        $invite = new InviteLink($this->organization, $this->admin, $expiresAt, $this->now);

        $afterExpiry = $this->now->modify('+8 days');
        $this->assertFalse($invite->isValid($afterExpiry));
    }

    public function testInviteLinkIsInvalidWhenDeactivated(): void
    {
        $expiresAt = $this->now->modify('+7 days');
        $invite = new InviteLink($this->organization, $this->admin, $expiresAt, $this->now);

        $invite->setActive(false);

        $this->assertFalse($invite->isValid($this->now));
    }

    public function testInviteLinkIsInvalidWhenMaxUsesReached(): void
    {
        $expiresAt = $this->now->modify('+7 days');
        $invite = new InviteLink($this->organization, $this->admin, $expiresAt, $this->now);
        $invite->setMaxUses(2);

        // First use
        $invite->use($this->now);
        $this->assertTrue($invite->isValid($this->now));

        // Second use
        $invite->use($this->now);
        $this->assertFalse($invite->isValid($this->now));
    }

    public function testUseIncrementsUsesCount(): void
    {
        $expiresAt = $this->now->modify('+7 days');
        $invite = new InviteLink($this->organization, $this->admin, $expiresAt, $this->now);

        $this->assertSame(0, $invite->getUsesCount());

        $invite->use($this->now);
        $this->assertSame(1, $invite->getUsesCount());

        $invite->use($this->now);
        $this->assertSame(2, $invite->getUsesCount());
    }

    public function testUseThrowsExceptionWhenInvalid(): void
    {
        $expiresAt = $this->now->modify('+7 days');
        $invite = new InviteLink($this->organization, $this->admin, $expiresAt, $this->now);
        $invite->setActive(false);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Cannot use an invalid invite link');

        $invite->use();
    }

    public function testUnlimitedUsesWhenMaxUsesIsNull(): void
    {
        $expiresAt = $this->now->modify('+7 days');
        $invite = new InviteLink($this->organization, $this->admin, $expiresAt, $this->now);

        // Use it many times
        for ($i = 0; $i < 100; ++$i) {
            $invite->use($this->now);
        }

        $this->assertSame(100, $invite->getUsesCount());
        $this->assertTrue($invite->isValid($this->now));
    }

    public function testLabelCanBeSet(): void
    {
        $expiresAt = $this->now->modify('+7 days');
        $invite = new InviteLink($this->organization, $this->admin, $expiresAt, $this->now);

        $this->assertNull($invite->getLabel());

        $invite->setLabel('Backend Team');

        $this->assertSame('Backend Team', $invite->getLabel());
    }

    public function testTokenIsUuid(): void
    {
        $expiresAt = $this->now->modify('+7 days');
        $invite = new InviteLink($this->organization, $this->admin, $expiresAt, $this->now);

        $token = $invite->getToken();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            (string) $token
        );
    }

    public function testCreatedAtAndCreatedBy(): void
    {
        $expiresAt = $this->now->modify('+7 days');
        $invite = new InviteLink($this->organization, $this->admin, $expiresAt, $this->now);

        $this->assertSame($this->now, $invite->getCreatedAt());
        $this->assertSame($this->admin, $invite->getCreatedBy());
    }

    public function testExpiresAtCanBeChanged(): void
    {
        $expiresAt = $this->now->modify('+7 days');
        $invite = new InviteLink($this->organization, $this->admin, $expiresAt, $this->now);

        $newExpiry = $this->now->modify('+30 days');
        $invite->setExpiresAt($newExpiry);

        $this->assertSame($newExpiry, $invite->getExpiresAt());
    }
}
