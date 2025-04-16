<?php

namespace App\Repository\GestionTransport;

use App\Entity\GestionTransport\Transport;
use App\Enums\GestionTransport\TransportStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Transport>
 */
class TransportRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Transport::class);
    }
    public function countByMonthAndStatus(int $year): array
    {
        // Initialize all months with 0 counts
        $months = array_fill_keys(['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'], [
            'complete' => 0,
            'actif' => 0
        ]);

        try {
            $results = $this->createQueryBuilder('t')
                ->select("DATE_FORMAT(t.timestamp, '%m') as month_num")
                ->addSelect('SUM(CASE WHEN t.status = :complete THEN 1 ELSE 0 END) as complete')
                ->addSelect('SUM(CASE WHEN t.status = :actif THEN 1 ELSE 0 END) as actif')
                ->where("DATE_FORMAT(t.timestamp, '%Y') = :year")
                ->setParameter('year', $year)
                ->setParameter('complete', TransportStatus::COMPLETE)
                ->setParameter('actif', TransportStatus::ACTIF)
                ->groupBy('month_num')
                ->orderBy('month_num', 'ASC')
                ->getQuery()
                ->getResult();

            foreach ($results as $result) {
                $monthName = \DateTime::createFromFormat('!m', $result['month_num'])->format('M');
                $months[$monthName] = [
                    'complete' => (int)$result['complete'],
                    'actif' => (int)$result['actif']
                ];
            }
        } catch (\Exception $e) {
            dump("Transport status query error: ".$e->getMessage());
        }

        return $months;
    }
    
    public function getRevenueByMonth(int $year): array
{
    $months = array_fill_keys(['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'], [
        'revenue' => 0,
        'count' => 0
    ]);

    $results = $this->createQueryBuilder('t')
        ->select("DATE_FORMAT(t.timestamp, '%m') as month_num")
        ->addSelect('COALESCE(SUM(t.tarif), 0) as revenue')
        ->addSelect('COUNT(t.id) as count')
        ->where("DATE_FORMAT(t.timestamp, '%Y') = :year")
        ->andWhere('t.status = :status')
        ->setParameter('year', $year)
        ->setParameter('status', TransportStatus::COMPLETE) // Make sure this matches your enum
        ->groupBy('month_num')
        ->getQuery()
        ->getResult();

    foreach ($results as $result) {
        $monthName = \DateTime::createFromFormat('!m', $result['month_num'])->format('M');
        $months[$monthName] = [
            'revenue' => (float)$result['revenue'],
            'count' => (int)$result['count']
        ];
    }

    return $months;
}
    //    /**
    //     * @return Transport[] Returns an array of Transport objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('t')
    //            ->andWhere('t.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('t.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Transport
    //    {
    //        return $this->createQueryBuilder('t')
    //            ->andWhere('t.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
