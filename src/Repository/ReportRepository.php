<?php

namespace App\Repository;

use App\Entity\Report;
use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Report>
 *
 * @method Report|null find($id, $lockMode = null, $lockVersion = null)
 * @method Report|null findOneBy(array $criteria, array $orderBy = null)
 * @method Report[]    findAll()
 * @method Report[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ReportRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Report::class);
    }

    /**
     * Find reports with optional filters for search, user, status, and date.
     *
     * @param string|null $search Search term for reason, ID, message content, or user details
     * @param string|null $userCin CIN of the reporting user
     * @param string|null $statut 'resolu' or 'en attente'
     * @param string|null $date Date in YYYY-MM-DD format
     * @return Report[]
     */
    public function findByFilters(?string $search = null, ?string $userCin = null, ?string $statut = null, ?string $date = null): array
    {
        $qb = $this->createQueryBuilder('r')
            ->leftJoin('r.reportedBy', 'u')
            ->leftJoin('r.message', 'm');

        if ($search) {
            $qb->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->like('r.reason', ':search'),
                    $qb->expr()->like('CAST(r.id AS string)', ':search'),
                    $qb->expr()->like('m.content', ':search'),
                    $qb->expr()->like('u.nom', ':search'),
                    $qb->expr()->like('u.prenom', ':search'),
                    $qb->expr()->like('u.cin', ':search')
                )
            )
            ->setParameter('search', '%' . $search . '%');
        }

        if ($userCin) {
            $qb->andWhere('u.cin = :userCin')
               ->setParameter('userCin', $userCin);
        }

        if ($statut) {
            $isResolved = $statut === 'resolu';
            $qb->andWhere('r.isResolved = :isResolved')
               ->setParameter('isResolved', $isResolved);
        }

        if ($date) {
            $qb->andWhere('DATE(r.createdAt) = :date')
               ->setParameter('date', $date);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Count legitimate reports for a given user (based on their sent messages).
     *
     * @param Utilisateur $user The user whose sent messages are checked
     * @return int Number of legitimate reports
     */
    public function countLegitimateReportsByUser(Utilisateur $user): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->leftJoin('r.message', 'm')
            ->where('m.senderCin = :sender')
            ->andWhere('r.isLegitimate = :isLegitimate')
            ->setParameter('sender', $user)
            ->setParameter('isLegitimate', true)
            ->getQuery()
            ->getSingleScalarResult();
    }
}