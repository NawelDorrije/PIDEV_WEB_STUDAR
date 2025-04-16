<?php

namespace App\Repository\GestionTransport;

use App\Entity\GestionTransport\Voiture;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Voiture>
 */
class VoitureRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Voiture::class);
    }
    public function countByMonth(int $year): array
    {
        // Initialize all months with 0
        $months = array_fill_keys(['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'], 0);

        try {
            $qb = $this->createQueryBuilder('v');
            $results = $qb
                ->select("DATE_FORMAT(v.timestamp, '%m') as month_num")
                ->addSelect('COUNT(v.idVoiture) as count')
                ->where("DATE_FORMAT(v.timestamp, '%Y') = :year")
                ->setParameter('year', $year)
                ->groupBy('month_num')
                ->orderBy('month_num', 'ASC')
                ->getQuery()
                ->getResult();

            foreach ($results as $result) {
                $monthName = \DateTime::createFromFormat('!m', $result['month_num'])->format('M');
                $months[$monthName] = (int)$result['count'];
            }

        } catch (\Exception $e) {
            dump("Vehicle query error: ".$e->getMessage());
        }

        return $months;
    }
    //    /**
    //     * @return Voiture[] Returns an array of Voiture objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('v')
    //            ->andWhere('v.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('v.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Voiture
    //    {
    //        return $this->createQueryBuilder('v')
    //            ->andWhere('v.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
