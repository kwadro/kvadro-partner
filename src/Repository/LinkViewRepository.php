<?php

namespace App\Repository;

use App\Entity\AdvertisingLink;
use App\Entity\LinkView;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LinkView>
 */
class LinkViewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LinkView::class);
    }

    public function countUniqueIps(AdvertisingLink $advertisingLink): int
    {
        return (int) $this->createQueryBuilder('v')
            ->select('COUNT(DISTINCT v.ipAddress)')
            ->andWhere('v.advertisingLink = :link')
            ->andWhere('v.ipAddress IS NOT NULL')
            ->andWhere("v.ipAddress != ''")
            ->setParameter('link', $advertisingLink)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function hasViewFromIp(AdvertisingLink $advertisingLink, string $ipAddress): bool
    {
        $count = (int) $this->createQueryBuilder('v')
            ->select('COUNT(v.id)')
            ->andWhere('v.advertisingLink = :link')
            ->andWhere('v.ipAddress = :ip')
            ->setParameter('link', $advertisingLink)
            ->setParameter('ip', $ipAddress)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * @param array $filters
     *
     * @return array{items: array, total: int, page: int, pages: int, perPage: int}
     */
    public function findFilteredForLink(
        AdvertisingLink $advertisingLink,
        array $filters,
        int $page = 1,
        int $perPage = 10,
    ): array {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));

        $qb = $this->createQueryBuilder('v')
            ->andWhere('v.advertisingLink = :link')
            ->setParameter('link', $advertisingLink)
            ->orderBy('v.createdAt', 'DESC')
            ->addOrderBy('v.id', 'DESC');

        $date = trim((string) ($filters['date'] ?? ''));
        if ('' !== $date && 1 === preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $from = new \DateTimeImmutable($date.' 00:00:00');
            $to = $from->modify('+1 day');
            $qb->andWhere('v.createdAt >= :dateFrom')
                ->andWhere('v.createdAt < :dateTo')
                ->setParameter('dateFrom', $from)
                ->setParameter('dateTo', $to);
        }

        $ip = trim((string) ($filters['ip'] ?? ''));
        if ('' !== $ip) {
            $qb->andWhere('v.ipAddress LIKE :ip')
                ->setParameter('ip', '%'.$ip.'%');
        }

        $visitorId = trim((string) ($filters['visitor_id'] ?? ''));
        if ('' !== $visitorId) {
            $qb->andWhere('v.visitorId LIKE :visitorId')
                ->setParameter('visitorId', '%'.$visitorId.'%');
        }

        $qb->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        $paginator = new Paginator($qb, true);
        $total = \count($paginator);
        $pages = max(1, (int) ceil($total / $perPage));
        if ($page > $pages) {
            $page = $pages;
            $qb->setFirstResult(($page - 1) * $perPage);
            $paginator = new Paginator($qb, true);
        }

        $items = [];
        foreach ($paginator as $view) {
            /** @var LinkView $view */
            $items[] = $view;
        }

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'perPage' => $perPage,
        ];
    }
}
