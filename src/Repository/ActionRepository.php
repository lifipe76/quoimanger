<?php

namespace App\Repository;

use App\Entity\Action;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Action>
 */
class ActionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Action::class);
    }

    /**
     * @return Action[]
     */
    public function findAllOrderedByLastRealisation(): array
    {
        $actions = $this->createQueryBuilder('a')
            ->leftJoin('a.realisations', 'ar')
            ->addSelect('ar')
            ->getQuery()
            ->getResult();

        usort($actions, function (Action $a, Action $b) {
            $dateA = $a->getLastRealiseAt();
            $dateB = $b->getLastRealiseAt();

            if ($dateA === $dateB) {
                return strcmp($a->getDesignation() ?? '', $b->getDesignation() ?? '');
            }

            if ($dateA === null) {
                return 1;
            }
            if ($dateB === null) {
                return -1;
            }

            return $dateB <=> $dateA;
        });

        return $actions;
    }
}
