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

    public function getMonthlyStatistics(): array
    {
        $results = $this->createQueryBuilder('r')
            ->select([
                'YEAR(r.dateDebut) as year',
                'MONTH(r.dateDebut) as month',
                'COUNT(r.id) as count'
            ])
            ->groupBy('year, month')
            ->orderBy('year, month')
            ->getQuery()
            ->getResult();

        return $this->formatStatistics($results);
    }

    private function formatStatistics(array $results): array
    {
        $monthNames = [
            1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
            5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
            9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
        ];

        return array_map(function($item) use ($monthNames) {
            return [
                'year' => $item['year'],
                'month' => $monthNames[$item['month']],
                'month_num' => $item['month'],
                'count' => $item['count']
            ];
        }, $results);
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

