<?php

namespace App\Repository;

use App\Entity\AdvertisingLink;
use App\Entity\Partner;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AdvertisingLink>
 */
class AdvertisingLinkRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AdvertisingLink::class);
    }

    /**
     * @return array
     */
    public function findByUserOrdered(User $user): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.user = :user')
            ->setParameter('user', $user)
            ->orderBy('l.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function isKeyAvailable(Partner $partner, string $linkKey): bool
    {
        $count = (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->andWhere('l.partner = :partner')
            ->andWhere('l.linkKey = :key')
            ->setParameter('partner', $partner)
            ->setParameter('key', $linkKey)
            ->getQuery()
            ->getSingleScalarResult();

        return 0 === $count;
    }

    public function countByPartner(Partner $partner): int
    {
        return (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->andWhere('l.partner = :partner')
            ->setParameter('partner', $partner)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return array
     */
    public function findByLinkKey(string $linkKey): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.linkKey = :key')
            ->setParameter('key', $linkKey)
            ->getQuery()
            ->getResult();
    }
}
