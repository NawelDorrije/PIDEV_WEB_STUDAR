<?php

namespace App\Repository\GestionMeubles;

use App\Entity\GestionMeubles\Meuble;
use App\Entity\Utilisateur;
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
     * Sauvegarde un nouveau meuble dans la base de données
     */
    public function save(Meuble $meuble, bool $flush = true): void
    {
        $this->getEntityManager()->persist($meuble);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Modifie un meuble existant dans la base de données
     */
    public function edit(Meuble $meuble, bool $flush = true): void
    {
        // Vérifier si l'entité est gérée par Doctrine
        if (!$this->getEntityManager()->contains($meuble)) {
            throw new \LogicException('Le meuble n\'est pas géré par Doctrine. Assurez-vous qu\'il a été récupéré via le repository.');
        }

        // Pas besoin de persist ici, car l'entité est déjà gérée
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
     * Récupère les meubles d'un vendeur spécifique (par Utilisateur)
     * @return Meuble[]
     */
    public function findByVendeur(Utilisateur $vendeur): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.vendeur = :vendeur')
            ->setParameter('vendeur', $vendeur)
            ->orderBy('m.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les meubles disponibles pour un acheteur spécifique (par CIN)
     * @return Meuble[]
     */
    public function findMeublesDisponiblesPourAcheteur(string $cinAcheteur): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.vendeur != :vendeur') // Exclure les meubles du vendeur ayant ce CIN
            ->andWhere('m.statut = :statut') // Seuls les meubles disponibles
            ->andWhere('m NOT IN (
                SELECT m2.id 
                FROM App\Entity\GestionMeubles\LignePanier lp
                JOIN lp.meuble m2
                JOIN lp.panier p
                WHERE p.cinAcheteur = :cinAcheteur 
                AND p.statut = :statutPanier
            )') // Exclure les meubles dans le panier en cours de l'acheteur
            ->setParameter('vendeur', $this->getEntityManager()->getReference(Utilisateur::class, $cinAcheteur))
            ->setParameter('cinAcheteur', $cinAcheteur)
            ->setParameter('statut', 'disponible')
            ->setParameter('statutPanier', 'EN_COURS')
            ->orderBy('m.id', 'DESC')
            ->getQuery()
            ->getResult();
    }
}