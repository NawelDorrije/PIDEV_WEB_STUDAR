<?php

// src/Repository/ReservationLogementRepository.php
namespace App\Repository;

use App\Entity\ReservationLogement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ReservationLogementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ReservationLogement::class);
    }

  // src/Repository/ReservationLogementRepository.php

// src/Repository/ReservationLogementRepository.php
// src/Repository/ReservationLogementRepository.php
public function getMonthlyStats(): array
{
    $conn = $this->getEntityManager()->getConnection();
    
    $sql = "
        SELECT 
            DATE_FORMAT(date_debut, '%Y-%m') AS month,
            COUNT(id) AS count
        FROM reservation_logement
        GROUP BY month
        ORDER BY month
    ";
    
    $stmt = $conn->executeQuery($sql);
    return $stmt->fetchAllAssociative();
}
}

    //    /**
    //     * @return ReservationLogement[] Returns an array of ReservationLogement objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('r')
    //            ->andWhere('r.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('r.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?ReservationLogement
    //    {
    //        return $this->createQueryBuilder('r')
    //            ->andWhere('r.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }

