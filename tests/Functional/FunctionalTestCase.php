<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Admin\Infrastructure\Doctrine\Entity\Organization;
use App\Admin\Infrastructure\Doctrine\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\MockClock;

abstract class FunctionalTestCase extends WebTestCase
{
    protected KernelBrowser $client;
    protected EntityManagerInterface $em;
    protected \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $container = static::getContainer();

        $this->em = $container->get(EntityManagerInterface::class);

        // Get the MockClock configured in config/packages/test/clock.yaml
        $clock = $container->get(ClockInterface::class);
        \assert($clock instanceof MockClock);
        $this->now = $clock->now();

        $this->createSchema();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    private function createSchema(): void
    {
        $schemaTool = new SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();

        // Drop and recreate schema
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    /**
     * Create an organization for testing.
     */
    protected function createOrganization(string $name = 'Test Org'): Organization
    {
        $org = new Organization($name, null, $this->now);
        $this->em->persist($org);
        $this->em->flush();

        return $org;
    }

    /**
     * Create a user for testing.
     *
     * @param array<string> $roles
     */
    protected function createUser(
        Organization $organization,
        string $email = 'user@example.com',
        array $roles = [],
        bool $approved = true,
    ): User {
        $user = new User($email, 'google_'.uniqid(), $organization, $this->now);

        if (!empty($roles)) {
            $user->setRoles($roles);
        }

        if ($approved) {
            $user->approve();
        }

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    /**
     * Login as a specific user by setting session.
     */
    protected function loginAs(User $user): void
    {
        $session = $this->client->getContainer()->get('session.factory')->createSession();
        $session->set('admin_user_id', $user->getId());
        $session->save();

        $this->client->getCookieJar()->set(
            new \Symfony\Component\BrowserKit\Cookie($session->getName(), $session->getId())
        );
    }

    /**
     * Create and login as an org admin.
     */
    protected function loginAsOrgAdmin(?Organization $org = null): User
    {
        $org = $org ?? $this->createOrganization();
        $admin = $this->createUser($org, 'admin@example.com', [User::ROLE_ORG_ADMIN]);
        $this->loginAs($admin);

        return $admin;
    }

    /**
     * Create and login as a super admin.
     */
    protected function loginAsSuperAdmin(?Organization $org = null): User
    {
        $org = $org ?? $this->createOrganization();
        $superAdmin = $this->createUser($org, 'superadmin@example.com', [User::ROLE_SUPER_ADMIN]);
        $this->loginAs($superAdmin);

        return $superAdmin;
    }
}
