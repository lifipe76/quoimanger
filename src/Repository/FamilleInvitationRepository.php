<?php

namespace App\Repository;

use App\Entity\FamilleInvitation;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FamilleInvitation>
 */
class FamilleInvitationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FamilleInvitation::class);
    }

    /**
     * @return FamilleInvitation[]
     */
    public function findPendingForUser(User $user): array
    {
        return $this->createQueryBuilder('fi')
            ->leftJoin('fi.famille', 'f')
            ->leftJoin('fi.demandeur', 'd')
            ->addSelect('f', 'd')
            ->where('fi.statut = :statut')
            ->andWhere('(fi.inviteUser = :user OR LOWER(fi.inviteEmail) = :email)')
            ->setParameter('statut', FamilleInvitation::STATUT_EN_ATTENTE)
            ->setParameter('user', $user)
            ->setParameter('email', strtolower($user->getEmail()))
            ->orderBy('fi.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
