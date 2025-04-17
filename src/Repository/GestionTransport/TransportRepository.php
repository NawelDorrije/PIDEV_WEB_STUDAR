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
    public function findByStatusAndUser(string $status, $user)
    {
        return $this->createQueryBuilder('t')
            ->join('t.voiture', 'v')
            ->where('t.status = :status')
            ->andWhere('v.utilisateur = :user')
            ->setParameter('status', $status)
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();
    }
    
    public function findByUser($user)
    {
        return $this->createQueryBuilder('t')
            ->join('t.voiture', 'v')
            ->where('v.utilisateur = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();
    }

    public function countByMonthAndStatus(int $year): array
    {
        // Initialize all months with 0 counts
        $months = array_fill_keys(['Janv','Févr','Mars','Avr','Mai','Juin','Juil','Août','Sept','Oct','Nov','Déc'], [
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
                $monthName = match($result['month_num']) {
                    '01' => 'Janv', '02' => 'Févr', '03' => 'Mars',
                    '04' => 'Avr',  '05' => 'Mai',  '06' => 'Juin',
                    '07' => 'Juil', '08' => 'Août', '09' => 'Sept',
                    '10' => 'Oct',  '11' => 'Nov',  '12' => 'Déc'
                };
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
        $results = $this->createQueryBuilder('t')
            ->select("DATE_FORMAT(t.timestamp, '%m') AS month_num")
            ->addSelect("DATE_FORMAT(t.timestamp, '%b') AS month_label")
            ->addSelect('SUM(t.tarif) AS revenue')
            ->where("DATE_FORMAT(t.timestamp, '%Y') = :year")
            ->andWhere('t.status = :status')
            ->setParameter('year', $year)
            ->setParameter('status', TransportStatus::COMPLETE)
            ->groupBy('month_num')
            ->addGroupBy('month_label')
            ->orderBy('month_num', 'ASC')
            ->getQuery()
            ->getResult();

            $months = ['Janv', 'Févr', 'Mars', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sept', 'Oct', 'Nov', 'Déc'];
            $revenueByMonth = array_fill_keys($months, 0);

        foreach ($results as $row) {
            $monthLabel = $row['month_label'];
            $revenueByMonth[$monthLabel] = (float) $row['revenue'];
        }

        return [
            'labels' => array_keys($revenueByMonth),
            'values' => array_values($revenueByMonth)
        ];
    }
}
