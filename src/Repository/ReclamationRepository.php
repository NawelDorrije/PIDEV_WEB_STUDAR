<?php

namespace App\Repository;

use App\Entity\Reclamation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\ResultSetMapping;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Reclamation>
 */
class ReclamationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reclamation::class);
    }

    public function getStatsByMonth(int $year): array
    {
        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('month', 'month', 'string');
        $rsm->addScalarResult('count', 'count', 'integer');

        $sql = 'SELECT DATE_FORMAT(timestamp, :format) as month, COUNT(idReclamation) as count 
                FROM reclamation 
                WHERE YEAR(timestamp) = :year 
                GROUP BY month';

        $query = $this->getEntityManager()->createNativeQuery($sql, $rsm);
        $query->setParameter('year', $year);
        $query->setParameter('format', '%Y-%m');

        $results = $query->getArrayResult();
        $stats = [];
        foreach ($results as $result) {
            $stats[$result['month']] = (int) $result['count'];
        }

        return $stats;
    }

    public function getStatsByStatus(): array
    {
        $qb = $this->createQueryBuilder('r')
            ->select('r.statut as status, COUNT(r.id) as count')
            ->groupBy('r.statut');

        $results = $qb->getQuery()->getArrayResult();
        $stats = [];
        foreach ($results as $result) {
            $stats[$result['status']] = (int) $result['count'];
        }

        return $stats;
    }

    public function getResponseRate(): float
    {
        $total = $this->createQueryBuilder('r')
            ->select('COUNT(r.id) as total')
            ->getQuery()
            ->getSingleScalarResult();

        if ($total == 0) {
            return 0.0;
        }

        $responded = $this->createQueryBuilder('r')
            ->select('COUNT(DISTINCT r.id) as responded')
            ->join('r.reponses', 'rep')
            ->getQuery()
            ->getSingleScalarResult();

        return ($responded / $total) * 100;
    }

    public function getTotalReclamations(): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id) as total')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getDailyReclamations(int $year, int $month): array
    {
        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('day', 'day', 'string');
        $rsm->addScalarResult('count', 'count', 'integer');

        $sql = 'SELECT DATE_FORMAT(timestamp, :format) as day, COUNT(idReclamation) as count 
                FROM reclamation 
                WHERE YEAR(timestamp) = :year AND MONTH(timestamp) = :month 
                GROUP BY day';

        $query = $this->getEntityManager()->createNativeQuery($sql, $rsm);
        $query->setParameter('year', $year);
        $query->setParameter('month', $month);
        $query->setParameter('format', '%Y-%m-%d');

        $results = $query->getArrayResult();
        $stats = [];
        foreach ($results as $result) {
            $stats[$result['day']] = (int) $result['count'];
        }

        return $stats;
    }

    public function getResolutionTimeDistribution(): array
    {
        // Step 1: Native SQL query to calculate resolution time for treated/refused reclamations
        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('resolution_speed', 'resolution_speed', 'string');
        $rsm->addScalarResult('count', 'count', 'integer');

        // Subquery to get the earliest response timestamp per reclamation
        $sql = 'SELECT 
                    CASE 
                        WHEN r.statut = :treated 
                             AND DATEDIFF(
                                 (SELECT MIN(rep.timestamp) 
                                  FROM reponse rep 
                                  WHERE rep.id_reclamation = r.idReclamation), 
                                 r.timestamp
                             ) <= 7 THEN :fast
                        ELSE :slow
                    END as resolution_speed,
                    COUNT(r.idReclamation) as count
                FROM reclamation r
                WHERE r.statut IN (:treated, :refused)
                GROUP BY resolution_speed';

        $query = $this->getEntityManager()->createNativeQuery($sql, $rsm);
        $query->setParameter('treated', 'traité');
        $query->setParameter('refused', 'refusé');
        $query->setParameter('fast', 'Fast (≤7 days)');
        $query->setParameter('slow', 'Slow (>7 days or unresolved)');

        $results = $query->getArrayResult();
        $stats = [];
        foreach ($results as $result) {
            $stats[$result['resolution_speed']] = (int) $result['count'];
        }

        // Step 2: Add unresolved reclamations (en cours) using DQL
        $unresolved = $this->createQueryBuilder('r')
            ->select('COUNT(r.id) as count')
            ->where('r.statut = :en_cours')
            ->setParameter('en_cours', 'en cours')
            ->getQuery()
            ->getSingleScalarResult();

        if ($unresolved > 0) {
            $stats['Slow (>7 days or unresolved)'] = ($stats['Slow (>7 days or unresolved)'] ?? 0) + (int) $unresolved;
        }

        return $stats;
    }
}