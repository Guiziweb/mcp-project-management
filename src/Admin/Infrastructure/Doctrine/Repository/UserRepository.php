<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Doctrine\Repository;

use App\Admin\Infrastructure\Doctrine\Entity\Organization;
use App\Admin\Infrastructure\Doctrine\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function findByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => $email]);
    }

    public function findByGoogleId(string $googleId): ?User
    {
        return $this->findOneBy(['googleId' => $googleId]);
    }

    /**
     * @return User[]
     */
    public function findByOrganization(Organization $organization): array
    {
        return $this->findBy(['organization' => $organization], ['email' => 'ASC']);
    }

    /**
     * @return User[]
     */
    public function findActiveByOrganization(Organization $organization, int $lastSeenMinutes = 60): array
    {
        $since = new \DateTimeImmutable(sprintf('-%d minutes', $lastSeenMinutes));

        return $this->createQueryBuilder('u')
            ->where('u.organization = :org')
            ->andWhere('u.lastSeenAt >= :since')
            ->setParameter('org', $organization)
            ->setParameter('since', $since)
            ->orderBy('u.lastSeenAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function save(User $user): void
    {
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }
}
