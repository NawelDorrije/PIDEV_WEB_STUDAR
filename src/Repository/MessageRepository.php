<?php

namespace App\Repository;

use App\Entity\Message;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Message>
 *
 * @method Message|null find($id, $lockMode = null, $lockVersion = null)
 * @method Message|null findOneBy(array $criteria, array $orderBy = null)
 * @method Message[]    findAll()
 * @method Message[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Message::class);
    }

    /**
     * Find messages between two users (a conversation), with pagination.
     *
     * @param string $senderCin
     * @param string $receiverCin
     * @param int $limit
     * @param int $offset
     * @return Message[]
     */
    public function findConversationPaginated(string $senderCin, string $receiverCin, int $limit = 20, int $offset = 0): array
    {
        return $this->createQueryBuilder('m')
            ->where('(m.senderCin = :sender AND m.receiverCin = :receiver) OR (m.senderCin = :receiver AND m.receiverCin = :sender)')
            ->setParameter('sender', $senderCin)
            ->setParameter('receiver', $receiverCin)
            ->orderBy('m.timestamp', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Count the total number of messages in a conversation.
     *
     * @param string $senderCin
     * @param string $receiverCin
     * @return int
     */
    public function countConversationMessages(string $senderCin, string $receiverCin): int
    {
        return $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->where('(m.senderCin = :sender AND m.receiverCin = :receiver) OR (m.senderCin = :receiver AND m.receiverCin = :sender)')
            ->setParameter('sender', $senderCin)
            ->setParameter('receiver', $receiverCin)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Find messages between two users (a conversation).
     *
     * @param string $senderCin
     * @param string $receiverCin
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