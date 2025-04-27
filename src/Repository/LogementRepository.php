<?php

namespace App\Repository;

use App\Entity\Logement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Logement>
 */
class LogementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Logement::class);
    }

    public function findNearby(
        ?string $type,
        ?float $maxPrice,
        ?int $rooms,
        ?float $lat,
        ?float $lng,
        int $radius
    ): array {
        if ($type === null && $maxPrice === null && $rooms === null) {
            // No filters applied, return all results
            return $this->findAll();
        }

        $qb = $this->createQueryBuilder('l')
            ->andWhere('l.type = COALESCE(:type, l.type)')
            ->andWhere('l.prix <= COALESCE(:maxPrice, l.prix)')
            ->andWhere('l.nbrChambre = COALESCE(:rooms, l.nbrChambre)')
            ->setParameter('type', $type)
            ->setParameter('maxPrice', $maxPrice)
            ->setParameter('rooms', $rooms);

        return $qb->getQuery()->getResult();
    }
    //    /**
//     * @return Logement[] Returns an array of Logement objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('l')
//            ->andWhere('l.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('l.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Logement
//    {
//        return $this->createQueryBuilder('l')
//            ->andWhere('l.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
