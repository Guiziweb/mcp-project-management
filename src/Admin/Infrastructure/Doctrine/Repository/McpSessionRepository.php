<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Doctrine\Repository;

use App\Admin\Infrastructure\Doctrine\Entity\McpSession;
use App\Admin\Infrastructure\Doctrine\Entity\Organization;
use App\Admin\Infrastructure\Doctrine\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<McpSession>
 */
class McpSessionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, McpSession::class);
    }

    public function findById(Uuid $id): ?McpSession
    {
        return $this->find($id);
    }

    /**
     * @return McpSession[]
     */
    public function findByUser(User $user): array
    {
        return $this->findBy(['user' => $user], ['lastActivityAt' => 'DESC']);
    }

    /**
     * @return McpSession[]
     */
    public function findByOrganization(Organization $organization): array
    {
        return $this->findBy(['organization' => $organization], ['lastActivityAt' => 'DESC']);
    }

    /**
     * @return McpSession[]
     */
    public function findActiveByOrganization(Organization $organization, int $ttlSeconds = 3600): array
    {
        $since = new \DateTimeImmutable(sprintf('-%d seconds', $ttlSeconds));

        return $this->createQueryBuilder('s')
            ->where('s.organization = :org')
            ->andWhere('s.lastActivityAt >= :since')
            ->setParameter('org', $organization)
            ->setParameter('since', $since)
            ->orderBy('s.lastActivityAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count active sessions for an organization.
     */
    public function countActiveByOrganization(Organization $organization, int $ttlSeconds = 3600): int
    {
        $since = new \DateTimeImmutable(sprintf('-%d seconds', $ttlSeconds));

        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->where('s.organization = :org')
            ->andWhere('s.lastActivityAt >= :since')
            ->setParameter('org', $organization)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Delete sessions for a user with matching client name.
     * Used to replace old sessions when a client reconnects.
     *
     * @return int Number of deleted sessions
     */
    public function deleteByUserAndClientName(User $user, string $clientName): int
    {
        return $this->createQueryBuilder('s')
            ->delete()
            ->where('s.user = :user')
            ->andWhere('s.clientInfo = :clientName')
            ->setParameter('user', $user)
            ->setParameter('clientName', $clientName)
            ->getQuery()
            ->execute();
    }

    /**
     * Delete expired sessions.
     *
     * @return int Number of deleted sessions
     */
    public function deleteExpired(int $ttlSeconds = 3600): int
    {
        $expiredBefore = new \DateTimeImmutable(sprintf('-%d seconds', $ttlSeconds));

        return $this->createQueryBuilder('s')
            ->delete()
            ->where('s.lastActivityAt < :expiredBefore')
            ->setParameter('expiredBefore', $expiredBefore)
            ->getQuery()
            ->execute();
    }
}
