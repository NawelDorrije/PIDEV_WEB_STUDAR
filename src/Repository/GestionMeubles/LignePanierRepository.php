<?php

namespace App\Repository\GestionMeubles;

use App\Entity\GestionMeubles\LignePanier;
use App\Entity\GestionMeubles\Meuble;
use App\Entity\GestionMeubles\Panier;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LignePanier>
 */
class LignePanierRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LignePanier::class);
    }

    public function save(LignePanier $lignePanier, bool $flush = false): void
    {
        // Vérifier que le panier est en statut EN_COURS
        if (!$this->estPanierEnCours($lignePanier->getPanier()->getId())) {
            throw new \LogicException('Le panier doit être en statut EN_COURS pour ajouter une ligne.');
        }

        // Vérifier que le meuble n'est pas déjà dans le panier
        if ($this->existeDejaDansPanier($lignePanier->getPanier()->getId(), $lignePanier->getMeuble()->getId())) {
            throw new \LogicException('Ce meuble est déjà dans le panier.');
        }

        // Vérifier que le meuble est disponible
        if (!$this->estMeubleDisponible($lignePanier->getMeuble()->getId())) {
            throw new \LogicException('Le meuble n\'est pas disponible.');
        }

        $this->getEntityManager()->persist($lignePanier);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function update(LignePanier $lignePanier, bool $flush = false): void
    {
        $this->getEntityManager()->persist($lignePanier);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(LignePanier $lignePanier, bool $flush = false): void
    {
        $this->getEntityManager()->remove($lignePanier);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findAllLignesPanier(): array
    {
        return $this->createQueryBuilder('lp')
            ->getQuery()
            ->getResult();
    }

    public function existeDejaDansPanier(int $idPanier, int $idMeuble): bool
    {
        $count = $this->createQueryBuilder('lp')
            ->select('COUNT(lp.id)')
            ->where('lp.panier = :idPanier')
            ->andWhere('lp.meuble = :idMeuble')
            ->setParameter('idPanier', $idPanier)
            ->setParameter('idMeuble', $idMeuble)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    private function estMeubleDisponible(int $idMeuble): bool
    {
        try {
            $meuble = $this->getEntityManager()
                ->getRepository(Meuble::class)
                ->find($idMeuble);

            if (!$meuble) {
                error_log("Aucun meuble trouvé avec l'ID : " . $idMeuble);
                return false;
            }

            return strtoupper($meuble->getStatut()) === 'DISPONIBLE';
        } catch (\Exception $e) {
            error_log("Erreur lors de la vérification du statut du meuble : " . $e->getMessage());
            return false;
        }
    }

    private function estPanierEnCours(int $idPanier): bool
    {
        try {
            $panier = $this->getEntityManager()
                ->getRepository(Panier::class)
                ->find($idPanier);

            if (!$panier) {
                return false;
            }

            return $panier->getStatut() === Panier::STATUT_EN_COURS;
        } catch (\Exception $e) {
            error_log("Erreur lors de la vérification du statut du panier : " . $e->getMessage());
            return false;
        }
    }

    public function findByPanierId(int $idPanier): array
    {
        return $this->createQueryBuilder('lp')
            ->where('lp.panier = :idPanier')
            ->setParameter('idPanier', $idPanier)
            ->getQuery()
            ->getResult();
    }

    public function findIdByMeubleId(int $meubleId): ?int
    {
        try {
            $lignePanier = $this->createQueryBuilder('lp')
                ->where('lp.meuble = :meubleId')
                ->setParameter('meubleId', $meubleId)
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            return $lignePanier?->getId();
        } catch (\Exception $e) {
            error_log("Erreur lors de la récupération de l'ID de la ligne du panier : " . $e->getMessage());
            return null;
        }
    }

    public function verifierProduitDansPanier(int $idPanier, int $idMeuble): bool
    {
        $count = $this->createQueryBuilder('lp')
            ->select('COUNT(lp.id)')
            ->where('lp.panier = :idPanier')
            ->andWhere('lp.meuble = :idMeuble')
            ->setParameter('idPanier', $idPanier)
            ->setParameter('idMeuble', $idMeuble)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }
    
}