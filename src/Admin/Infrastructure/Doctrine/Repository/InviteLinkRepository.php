<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Doctrine\Repository;

use App\Admin\Infrastructure\Doctrine\Entity\InviteLink;
use App\Admin\Infrastructure\Doctrine\Entity\Organization;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<InviteLink>
 */
class InviteLinkRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InviteLink::class);
    }

    public function findByToken(Uuid $token): ?InviteLink
    {
        return $this->find($token);
    }

    public function findValidByToken(Uuid $token): ?InviteLink
    {
        $link = $this->find($token);

        if (null === $link || !$link->isValid()) {
            return null;
        }

        return $link;
    }

    /**
     * @return InviteLink[]
     */
    public function findByOrganization(Organization $organization): array
    {
        return $this->findBy(['organization' => $organization], ['createdAt' => 'DESC']);
    }

    /**
     * @return InviteLink[]
     */
    public function findActiveByOrganization(Organization $organization): array
    {
        $now = new \DateTimeImmutable();

        return $this->createQueryBuilder('i')
            ->where('i.organization = :org')
            ->andWhere('i.active = true')
            ->andWhere('i.expiresAt > :now')
            ->setParameter('org', $organization)
            ->setParameter('now', $now)
            ->orderBy('i.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
