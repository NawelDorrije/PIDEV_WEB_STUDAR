<?php

namespace App\Repository;

use App\Entity\ReservationTransport;
use App\Entity\GestionTransport\Route;
use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ReservationTransport>
 */
class ReservationTransportRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ReservationTransport::class);
    }
  // src/Repository/ReservationTransportRepository.php

public function findByTransporteurAndStatus(Utilisateur $transporteur, ?string $status = null): array
{
    return $this->createTransporteurQueryBuilder($transporteur, $status)
        ->getQuery()
        ->getResult();
}

public function findByEtudiant(Utilisateur $etudiant): array
{
    return $this->createQueryBuilder('r')
        ->where('r.etudiant = :etudiant')
        ->setParameter('etudiant', $etudiant)
        ->getQuery()
        ->getResult();
}

public function findByEtudiantAndStatus(Utilisateur $etudiant, ?string $status = null): array
{
    return $this->createEtudiantQueryBuilder($etudiant, $status)
        ->getQuery()
        ->getResult();
}

private function createTransporteurQueryBuilder(Utilisateur $transporteur, ?string $status = null)
{
    $qb = $this->createQueryBuilder('r')
        ->where('r.transporteur = :transporteur')
        ->setParameter('transporteur', $transporteur);

    if ($status) {
        $qb->andWhere('r.status = :status')
           ->setParameter('status', $status);
    }

    return $qb;
}

private function createEtudiantQueryBuilder(Utilisateur $etudiant, ?string $status = null)
{
    $qb = $this->createQueryBuilder('r')
        ->where('r.etudiant = :etudiant')
        ->setParameter('etudiant', $etudiant);

    if ($status) {
        $qb->andWhere('r.status = :status')
           ->setParameter('status', $status);
    }

    return $qb;
}

    //    /**
    //     * @return ReservationTransport[] Returns an array of ReservationTransport objects
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

    //    public function findOneBySomeField($value): ?ReservationTransport
    //    {
    //        return $this->createQueryBuilder('r')
    //            ->andWhere('r.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
