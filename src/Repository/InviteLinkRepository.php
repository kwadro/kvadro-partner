<?php

namespace App\Repository;

use App\Entity\InviteLink;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<InviteLink>
 */
class InviteLinkRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InviteLink::class);
    }

    /**
     * @return array
     */
    public function findByUserOrdered(User $user): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.user = :user')
            ->setParameter('user', $user)
            ->orderBy('i.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByLinkKey(string $linkKey): ?InviteLink
    {
        return $this->findOneBy(['linkKey' => $linkKey]);
    }

    public function isKeyAvailable(string $linkKey, ?InviteLink $except = null): bool
    {
        $existing = $this->findOneByLinkKey($linkKey);
        if (null === $existing) {
            return true;
        }

        return null !== $except
            && null !== $except->getId()
            && $existing->getId() === $except->getId();
    }
}
