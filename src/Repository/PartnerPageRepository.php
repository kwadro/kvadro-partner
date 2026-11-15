<?php

namespace App\Repository;

use App\Entity\Partner;
use App\Entity\PartnerPage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PartnerPage>
 */
class PartnerPageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PartnerPage::class);
    }

    public function findOneByCode(string $code): ?PartnerPage
    {
        return $this->findOneBy(['code' => strtolower($code)]);
    }

    public function isCodeAvailable(string $code, ?PartnerPage $except = null): bool
    {
        $qb = $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.code = :code')
            ->setParameter('code', strtolower($code));

        if (null !== $except && null !== $except->getId()) {
            $qb->andWhere('p.id != :id')
                ->setParameter('id', $except->getId());
        }

        return 0 === (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @return array
     */
    public function findByPartnerOrdered(Partner $partner): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.partner = :partner')
            ->setParameter('partner', $partner)
            ->orderBy('p.code', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
