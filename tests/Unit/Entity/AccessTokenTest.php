<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Admin\Infrastructure\Doctrine\Entity\AccessToken;
use App\Admin\Infrastructure\Doctrine\Entity\Organization;
use App\Admin\Infrastructure\Doctrine\Entity\User;
use PHPUnit\Framework\TestCase;

final class AccessTokenTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        $now = new \DateTimeImmutable('2024-01-01 00:00:00');
        $organization = new Organization('Test Org', 'test-org', $now);
        $this->user = new User('test@example.com', 'google123', $organization, $now);
    }

    public function testAccessTokenExpiresIn24Hours(): void
    {
        $createdAt = new \DateTimeImmutable('2024-01-15 10:00:00');

        $token = new AccessToken(
            user: $this->user,
            tokenHash: 'hash123',
            credentials: 'encrypted',
            type: 'access',
            parentToken: null,
            createdAt: $createdAt,
        );

        $expectedExpiry = $createdAt->modify('+24 hours');
        $this->assertEquals($expectedExpiry, $token->getExpiresAt());
    }

    public function testRefreshTokenExpiresIn30Days(): void
    {
        $createdAt = new \DateTimeImmutable('2024-01-15 10:00:00');

        $token = new AccessToken(
            user: $this->user,
            tokenHash: 'hash123',
            credentials: 'encrypted',
            type: 'refresh',
            parentToken: null,
            createdAt: $createdAt,
        );

        $expectedExpiry = $createdAt->modify('+30 days');
        $this->assertEquals($expectedExpiry, $token->getExpiresAt());
    }

    public function testTokenIsNotExpiredBeforeExpiryTime(): void
    {
        $createdAt = new \DateTimeImmutable('2024-01-15 10:00:00');

        $token = new AccessToken(
            user: $this->user,
            tokenHash: 'hash123',
            credentials: 'encrypted',
            type: 'access',
            parentToken: null,
            createdAt: $createdAt,
        );

        // 23 hours later - not expired
        $now = $createdAt->modify('+23 hours');
        $this->assertFalse($token->isExpired($now));
    }

    public function testTokenIsExpiredAfterExpiryTime(): void
    {
        $createdAt = new \DateTimeImmutable('2024-01-15 10:00:00');

        $token = new AccessToken(
            user: $this->user,
            tokenHash: 'hash123',
            credentials: 'encrypted',
            type: 'access',
            parentToken: null,
            createdAt: $createdAt,
        );

        // 25 hours later - expired
        $now = $createdAt->modify('+25 hours');
        $this->assertTrue($token->isExpired($now));
    }

    public function testTokenIsValidWhenNotExpiredAndNotRevoked(): void
    {
        $createdAt = new \DateTimeImmutable('2024-01-15 10:00:00');

        $token = new AccessToken(
            user: $this->user,
            tokenHash: 'hash123',
            credentials: 'encrypted',
            type: 'access',
            parentToken: null,
            createdAt: $createdAt,
        );

        $now = $createdAt->modify('+12 hours');
        $this->assertTrue($token->isValid($now));
    }

    public function testTokenIsInvalidWhenRevoked(): void
    {
        $createdAt = new \DateTimeImmutable('2024-01-15 10:00:00');

        $token = new AccessToken(
            user: $this->user,
            tokenHash: 'hash123',
            credentials: 'encrypted',
            type: 'access',
            parentToken: null,
            createdAt: $createdAt,
        );

        $revokedAt = $createdAt->modify('+1 hour');
        $token->revoke($revokedAt);

        $now = $createdAt->modify('+2 hours');
        $this->assertFalse($token->isValid($now));
        $this->assertTrue($token->isRevoked());
        $this->assertEquals($revokedAt, $token->getRevokedAt());
    }

    public function testTouchUpdatesLastUsedAt(): void
    {
        $createdAt = new \DateTimeImmutable('2024-01-15 10:00:00');

        $token = new AccessToken(
            user: $this->user,
            tokenHash: 'hash123',
            credentials: 'encrypted',
            type: 'access',
            parentToken: null,
            createdAt: $createdAt,
        );

        $this->assertNull($token->getLastUsedAt());

        $touchedAt = $createdAt->modify('+5 hours');
        $token->touch($touchedAt);

        $this->assertEquals($touchedAt, $token->getLastUsedAt());
    }

    public function testHashTokenIsDeterministic(): void
    {
        $plainToken = 'my_secret_token';

        $hash1 = AccessToken::hashToken($plainToken);
        $hash2 = AccessToken::hashToken($plainToken);

        $this->assertSame($hash1, $hash2);
        $this->assertSame(64, strlen($hash1)); // SHA-256 = 64 hex chars
    }

    public function testGenerateTokenReturnsUniqueValues(): void
    {
        $token1 = AccessToken::generateToken();
        $token2 = AccessToken::generateToken();

        $this->assertNotSame($token1, $token2);
        $this->assertSame(64, strlen($token1)); // 32 bytes = 64 hex chars
    }

    public function testIsAccessToken(): void
    {
        $token = new AccessToken(
            user: $this->user,
            tokenHash: 'hash123',
            credentials: 'encrypted',
            type: 'access',
        );

        $this->assertTrue($token->isAccessToken());
        $this->assertFalse($token->isRefreshToken());
    }

    public function testIsRefreshToken(): void
    {
        $token = new AccessToken(
            user: $this->user,
            tokenHash: 'hash123',
            credentials: 'encrypted',
            type: 'refresh',
        );

        $this->assertFalse($token->isAccessToken());
        $this->assertTrue($token->isRefreshToken());
    }
}
