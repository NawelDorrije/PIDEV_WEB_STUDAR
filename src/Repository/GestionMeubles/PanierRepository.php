<?php

namespace App\Repository\GestionMeubles;

use App\Entity\GestionMeubles\Panier;
use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Panier>
 */
class PanierRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Panier::class);
    }

    public function save(Panier $panier, bool $flush = false): void
    {
        // Vérification du rôle étudiant (commentée comme dans l'original)
        // $utilisateur = $this->getEntityManager()
        //     ->getRepository(Utilisateur::class)
        //     ->findOneBy(['cin' => $panier->getCinAcheteur()]);
        
        // if (!$utilisateur || $utilisateur->getRole() !== 'étudiant') {
        //     throw new \LogicException('L\'acheteur doit être un étudiant.');
        // }

        // Vérification panier existant
        $existingPanier = $this->findPanierEnCours($panier->getAcheteur());
        if ($existingPanier && $existingPanier->getId() !== $panier->getId()) {
            throw new \LogicException('L\'acheteur a déjà un panier en cours.');
        }

        $this->getEntityManager()->persist($panier);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function update(Panier $panier, bool $flush = false): void
    {
        // Même vérification que pour save (commentée comme dans l'original)
        // $utilisateur = $this->getEntityManager()
        //     ->getRepository(Utilisateur::class)
        //     ->findOneBy(['cin' => $panier->getCinAcheteur()]);
        
        // if (!$utilisateur || $utilisateur->getRole() !== 'étudiant') {
        //     throw new \LogicException('L\'acheteur doit être un étudiant.');
        // }

        $this->getEntityManager()->persist($panier);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Panier $panier, bool $flush = false): void
    {
        $this->getEntityManager()->remove($panier);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    // public function findPanierEnCours(string $cinAcheteur): ?Panier
    // {
    //     return $this->createQueryBuilder('p')
    //         ->where('p.cinAcheteur = :cin')
    //         ->andWhere('p.statut = :statut')
    //         ->setParameter('cin', $cinAcheteur)
    //         ->setParameter('statut', Panier::STATUT_EN_COURS)
    //         ->getQuery()
    //         ->getOneOrNullResult();
    // }
    public function findPanierEnCours(Utilisateur $acheteur): ?Panier
{
    return $this->createQueryBuilder('p')
        ->where('p.acheteur = :acheteur')
        ->andWhere('p.statut = :statut')
        ->setParameter('acheteur', $acheteur)
        ->setParameter('statut', Panier::STATUT_EN_COURS)
        ->getQuery()
        ->getOneOrNullResult();
}

    public function getPanierIdByCinAcheteur(Utilisateur $acheteur): ?int
    {
        $panier = $this->findPanierEnCours($acheteur);
        return $panier?->getId() ?? null;
    }

    // Nouvelle méthode traduite de calculerSommePanier
    public function calculerSommePanier(int $idPanier): float
    {
        try {
            $result = $this->createQueryBuilder('p')
                ->select('COALESCE(SUM(m.prix), 0) as total')
                ->leftJoin('p.lignesPanier', 'lp')
                ->leftJoin('lp.meuble', 'm')
                ->where('p.id = :idPanier')
                ->setParameter('idPanier', $idPanier)
                ->getQuery()
                ->getSingleScalarResult();

            return (float) $result;
        } catch (\Exception $e) {
            // Gestion de l'erreur (similaire au System.out.println original)
            error_log("Erreur lors du calcul de la somme du panier : " . $e->getMessage());
            return 0.0; // Retourne 0 en cas d'erreur, comme dans l'original
        }
    }
}