<?php

namespace App\Repository;

use App\Entity\Famille;
use App\Entity\Frigo;
use App\Entity\Recette;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Frigo>
 */
class FrigoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Frigo::class);
    }

    /**
     * @return Frigo[]
     */
    public function findForUserOrFamille(?User $user, ?Famille $famille): array
    {
        if (!$user && !$famille) {
            return [];
        }

        $qb = $this->createQueryBuilder('f')
            ->leftJoin('f.recette', 'r')
            ->addSelect('r');

        if ($famille) {
            $qb->where('f.famille = :famille')
                ->setParameter('famille', $famille);
        } else {
            $qb->where('f.user = :user AND f.famille IS NULL')
                ->setParameter('user', $user);
        }

        return $qb->orderBy('f.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return int[]
     */
    public function findRecetteIdsForUserOrFamille(?User $user, ?Famille $famille): array
    {
        $items = $this->findForUserOrFamille($user, $famille);
        $ids = [];
        foreach ($items as $item) {
            if ($item->getRecette() && $item->getRecette()->getId()) {
                $ids[] = $item->getRecette()->getId();
            }
        }
        return array_values(array_unique($ids));
    }

    public function findOneByRecetteAndUserOrFamille(Recette $recette, ?User $user, ?Famille $famille): ?Frigo
    {
        $qb = $this->createQueryBuilder('f')
            ->where('f.recette = :recette')
            ->setParameter('recette', $recette);

        if ($famille) {
            $qb->andWhere('f.famille = :famille')
                ->setParameter('famille', $famille);
        } else {
            $qb->andWhere('f.user = :user AND f.famille IS NULL')
                ->setParameter('user', $user);
        }

        return $qb->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function removeByRecette(Recette $recette, ?User $user, ?Famille $famille): int
    {
        $qb = $this->createQueryBuilder('f')
            ->delete()
            ->where('f.recette = :recette')
            ->setParameter('recette', $recette);

        if ($famille) {
            $qb->andWhere('f.famille = :famille')
                ->setParameter('famille', $famille);
        } else {
            $qb->andWhere('f.user = :user AND f.famille IS NULL')
                ->setParameter('user', $user);
        }

        return $qb->getQuery()->execute();
    }
}
