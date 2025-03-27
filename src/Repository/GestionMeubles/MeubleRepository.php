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
     * Supprime un meuble de la base de données
     */
    public function delete(Meuble $meuble, bool $flush = true): void
    {
        $this->getEntityManager()->remove($meuble);
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

    /**
     * Récupère les meubles d'un vendeur spécifique (par cinVendeur)
     * @return Meuble[]
     */
    public function findByCinVendeur(string $cinVendeur): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.cinVendeur = :cinVendeur')
            ->setParameter('cinVendeur', $cinVendeur)
            ->orderBy('m.statut', 'ASC') // Les meubles disponibles (DISPONIBLE) avant les vendus (INDISPONIBLE)
            ->getQuery()
            ->getResult();
    }
    public function findMeublesDisponiblesPourAcheteur(string $cinAcheteur): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.cinVendeur != :cinAcheteur')
            ->andWhere('m.statut = :statut')
            ->setParameter('cinAcheteur', $cinAcheteur)
            ->setParameter('statut', 'disponible')
            ->getQuery()
            ->getResult();
    }
}