<?php

namespace App\Repository\GestionMeubles;

use App\Entity\GestionMeubles\Meuble;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Meuble>
 */
class MeubleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Meuble::class);
    }

    /**
     * Sauvegarde un meuble dans la base de données
     */
    public function save(Meuble $meuble, bool $flush = true): void
    {
        $this->getEntityManager()->persist($meuble);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Récupère tous les meubles
     * @return Meuble[]
     */
    public function findAllMeubles(): array
    {
        return $this->createQueryBuilder('m')
            ->orderBy('m.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère un meuble par son ID
     */
    public function findOneById(int $id): ?Meuble
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }
}