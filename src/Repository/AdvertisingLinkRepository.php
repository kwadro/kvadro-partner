<?php

namespace App\Repository;

use App\Entity\AdvertisingLink;
use App\Entity\Partner;
use App\Entity\PartnerPage;
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

    public function isKeyAvailable(
        PartnerPage $partnerPage,
        string $linkKey,
        ?AdvertisingLink $except = null,
    ): bool {
        $qb = $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->andWhere('l.partnerPage = :page')
            ->andWhere('l.linkKey = :key')
            ->setParameter('page', $partnerPage)
            ->setParameter('key', $linkKey);

        if (null !== $except && null !== $except->getId()) {
            $qb->andWhere('l.id != :exceptId')
                ->setParameter('exceptId', $except->getId());
        }

        return 0 === (int) $qb->getQuery()->getSingleScalarResult();
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

    public function countByPartnerPage(PartnerPage $partnerPage): int
    {
        return (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->andWhere('l.partnerPage = :page')
            ->setParameter('page', $partnerPage)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findOneByReferralKey(string $referralKey): ?AdvertisingLink
    {
        $referralKey = trim($referralKey);
        if (strlen($referralKey) < 5) {
            return null;
        }

        $code = strtolower(substr($referralKey, 0, 2));
        $linkKey = substr($referralKey, 2);

        return $this->createQueryBuilder('l')
            ->innerJoin('l.partnerPage', 'p')
            ->andWhere('p.code = :code')
            ->andWhere('l.linkKey = :key')
            ->setParameter('code', $code)
            ->setParameter('key', $linkKey)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
