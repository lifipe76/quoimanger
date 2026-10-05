<?php

namespace App\Repository;

use App\Entity\Message;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Message>
 */
class MessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Message::class);
    }

    /**
     * @return array<Message>
     */
    public function findMessagesAfter(int $conversationId, int $sinceId): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.conversation = :convId')
            ->andWhere('m.id > :sinceId')
            ->setParameter('convId', $conversationId)
            ->setParameter('sinceId', $sinceId)
            ->orderBy('m.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
