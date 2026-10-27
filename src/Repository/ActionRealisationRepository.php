<?php

namespace App\Repository;

use App\Entity\ActionRealisation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ActionRealisation>
 */
class ActionRealisationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ActionRealisation::class);
    }

    /**
     * @return ActionRealisation[]
     */
    public function findTimeline(): array
    {
        return $this->createQueryBuilder('ar')
            ->join('ar.action', 'a')
            ->addSelect('a')
            ->leftJoin('a.realisations', 'a_realisations')
            ->addSelect('a_realisations')
            ->leftJoin('ar.participants', 'ar_participants')
            ->addSelect('ar_participants')
            ->orderBy('ar.realiseAt', 'DESC')
            ->addOrderBy('ar.id', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
