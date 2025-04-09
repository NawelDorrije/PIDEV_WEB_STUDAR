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
     * Récupère les messages entre deux utilisateurs.
     *
     * @param string $userCin1 CIN de l'utilisateur 1
     * @param string $userCin2 CIN de l'utilisateur 2
     * @return Message[]
     */
    public function findConversation(string $senderCin, string $receiverCin): array
    {
        return $this->createQueryBuilder('m')
            ->where('(m.senderCin = :sender AND m.receiverCin = :receiver) OR (m.senderCin = :receiver AND m.receiverCin = :sender)')
            ->setParameter('sender', $senderCin)
            ->setParameter('receiver', $receiverCin)
            ->orderBy('m.timestamp', 'ASC')
            ->getQuery()
            ->getResult();
    }
}