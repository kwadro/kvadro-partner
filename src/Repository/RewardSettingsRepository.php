<?php

namespace App\Repository;

use App\Entity\RewardSettings;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RewardSettings>
 */
class RewardSettingsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RewardSettings::class);
    }

    public function getSettings(): RewardSettings
    {
        $settings = $this->findOneBy([], ['id' => 'ASC']);
        if (null !== $settings) {
            return $settings;
        }

        $settings = new RewardSettings();
        $this->getEntityManager()->persist($settings);
        $this->getEntityManager()->flush();

        return $settings;
    }
}
