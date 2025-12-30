<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Admin\Infrastructure\Doctrine\Entity\Organization;
use PHPUnit\Framework\TestCase;

final class OrganizationTest extends TestCase
{
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2024-01-01 00:00:00');
    }

    public function testSlugIsGeneratedFromName(): void
    {
        $org = new Organization('My Company', null, 'redmine', $this->now);

        $this->assertSame('my-company', $org->getSlug());
    }

    public function testSlugWithSpecialCharacters(): void
    {
        $org = new Organization('Acme & Co. (France)', null, 'redmine', $this->now);

        $this->assertSame('acme-co-france', $org->getSlug());
    }

    public function testSlugWithAccents(): void
    {
        $org = new Organization('Société Générale', null, 'redmine', $this->now);

        // Accented chars are removed, leaving short slug
        $this->assertStringContainsString('g-n-rale', $org->getSlug());
    }

    public function testShortNameGetsOrgSuffix(): void
    {
        $org = new Organization('AB', null, 'redmine', $this->now);

        $this->assertSame('ab-org', $org->getSlug());
    }

    public function testCustomSlugIsUsed(): void
    {
        $org = new Organization('My Company', 'custom-slug', 'redmine', $this->now);

        $this->assertSame('custom-slug', $org->getSlug());
    }

    public function testSetSlugWithValidValue(): void
    {
        $org = new Organization('Test', null, 'redmine', $this->now);

        $org->setSlug('new-valid-slug');

        $this->assertSame('new-valid-slug', $org->getSlug());
    }

    public function testSetSlugTooShortThrowsException(): void
    {
        $org = new Organization('Test', null, 'redmine', $this->now);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('between 3 and 50 characters');

        $org->setSlug('ab');
    }

    public function testSetSlugWithInvalidCharactersThrowsException(): void
    {
        $org = new Organization('Test', null, 'redmine', $this->now);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('lowercase alphanumeric');

        $org->setSlug('Invalid_Slug');
    }

    public function testProviderUrl(): void
    {
        $org = new Organization('Test', null, 'redmine', $this->now);

        $this->assertNull($org->getProviderUrl());

        $org->setProviderUrl('https://redmine.example.com/');

        // Trailing slash should be removed
        $this->assertSame('https://redmine.example.com', $org->getProviderUrl());
    }

    public function testProviderUrlNullRemovesFromConfig(): void
    {
        $org = new Organization('Test', null, 'redmine', $this->now);
        $org->setProviderUrl('https://example.com');

        $org->setProviderUrl(null);

        $this->assertNull($org->getProviderUrl());
        $this->assertArrayNotHasKey('url', $org->getProviderConfig());
    }

    public function testProviderConfigValues(): void
    {
        $org = new Organization('Test', null, 'redmine', $this->now);

        $org->setProviderConfigValue('workspace', 'my-workspace');
        $org->setProviderConfigValue('api_version', 'v2');

        $this->assertSame('my-workspace', $org->getProviderConfigValue('workspace'));
        $this->assertSame('v2', $org->getProviderConfigValue('api_version'));
        $this->assertNull($org->getProviderConfigValue('nonexistent'));
    }

    public function testEnabledToolsEmptyMeansAllAllowed(): void
    {
        $org = new Organization('Test', null, 'redmine', $this->now);

        $this->assertTrue($org->hasToolEnabled('any_tool'));
        $this->assertTrue($org->hasToolEnabled('another_tool'));
    }

    public function testEnabledToolsRestrictsAccess(): void
    {
        $org = new Organization('Test', null, 'redmine', $this->now);

        $org->setEnabledTools(['list_issues', 'log_time']);

        $this->assertTrue($org->hasToolEnabled('list_issues'));
        $this->assertTrue($org->hasToolEnabled('log_time'));
        $this->assertFalse($org->hasToolEnabled('delete_issue'));
    }

    public function testCreatedAtIsSet(): void
    {
        $org = new Organization('Test', null, 'redmine', $this->now);

        $this->assertSame($this->now, $org->getCreatedAt());
    }

    public function testToString(): void
    {
        $org = new Organization('My Company', null, 'redmine', $this->now);

        $this->assertSame('My Company', (string) $org);
    }
}
