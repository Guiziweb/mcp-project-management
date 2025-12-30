<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\DataFixtures;

use App\Admin\Infrastructure\Doctrine\Entity\InviteLink;
use App\Admin\Infrastructure\Doctrine\Entity\Organization;
use App\Admin\Infrastructure\Doctrine\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class AppFixtures extends Fixture
{
    public function __construct(
        private readonly ClockInterface $clock,
        #[Autowire(env: 'FIXTURE_ORG_NAME')]
        private readonly string $orgName,
        #[Autowire(env: 'FIXTURE_ORG_SLUG')]
        private readonly string $orgSlug,
        #[Autowire(env: 'FIXTURE_PROVIDER_TYPE')]
        private readonly string $providerType,
        #[Autowire(env: 'FIXTURE_PROVIDER_URL')]
        private readonly string $providerUrl,
        #[Autowire(env: 'FIXTURE_ADMIN_EMAIL')]
        private readonly string $adminEmail,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $now = $this->clock->now();

        $org = new Organization($this->orgName, $this->orgSlug, $this->providerType, $now);
        $org->setProviderUrl($this->providerUrl);
        $manager->persist($org);

        $admin = new User($this->adminEmail, 'fixture-placeholder', $org, $now);
        $admin->setRoles([User::ROLE_USER, User::ROLE_ORG_ADMIN, User::ROLE_SUPER_ADMIN]);
        $admin->approve();
        $manager->persist($admin);

        $invite = new InviteLink($org, $admin, $now->modify('+30 days'), $now);
        $invite->setLabel('Initial Setup');
        $manager->persist($invite);

        $manager->flush();
    }
}
