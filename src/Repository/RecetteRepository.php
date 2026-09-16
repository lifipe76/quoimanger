<?php

namespace App\Repository;

use App\Entity\Recette;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Recette>
 */
class RecetteRepository extends ServiceEntityRepository
{
    public function __construct(
        private readonly ManagerRegistry $registry,
    ) {
        parent::__construct($registry, Recette::class);
    }

    /**
     * Retourne toutes les recettes classées par date de dernière réalisation décroissante
     *
     * @return Recette[]
     */
    public function findAllOrderedByLastRealisation(): array
    {
        $recettes = $this->createQueryBuilder('r')
            ->leftJoin('r.realisations', 'rr')
            ->addSelect('rr')
            ->leftJoin('r.recetteIngredients', 'ri')
            ->addSelect('ri')
            ->leftJoin('ri.ingredient', 'i')
            ->addSelect('i')
            ->getQuery()
            ->getResult();

        usort($recettes, function (Recette $a, Recette $b) {
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

        return $recettes;
    }
}
