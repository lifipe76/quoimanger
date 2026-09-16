<?php

namespace App\Repository;

use App\Entity\RecetteRealisation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RecetteRealisation>
 */
class RecetteRealisationRepository extends ServiceEntityRepository
{
    public function __construct(
        private readonly ManagerRegistry $registry,
    ) {
        parent::__construct($registry, RecetteRealisation::class);
    }

    /**
     * Retourne les réalisations triées par ordre chronologique décroissant (pour la timeline)
     *
     * @return RecetteRealisation[]
     */
    public function findTimeline(): array
    {
        return $this->createQueryBuilder('rr')
            ->join('rr.recette', 'r')
            ->addSelect('r')
            ->leftJoin('r.recetteIngredients', 'ri')
            ->addSelect('ri')
            ->leftJoin('ri.ingredient', 'i')
            ->addSelect('i')
            ->orderBy('rr.realiseAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
