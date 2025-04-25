<?php

namespace App\Repository\GestionMeubles;

use App\Entity\GestionMeubles\Commande;
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
    // public function edit(Meuble $meuble, bool $flush = true): void
    // {
    //     // Vérifier si l'entité est gérée par Doctrine
    //     if (!$this->getEntityManager()->contains($meuble)) {
    //         throw new \LogicException('Le meuble n\'est pas géré par Doctrine. Assurez-vous qu\'il a été récupéré via le repository.');
    //     }

    //     // Pas besoin de persist ici, car l'entité est déjà gérée
    //     if ($flush) {
    //         $this->getEntityManager()->flush();
    //     }
    // }

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
    public function edit(Meuble $meuble, bool $flush = true): void
    {
        if (!$this->getEntityManager()->contains($meuble)) {
            throw new \LogicException('Le meuble n\'est pas géré par Doctrine.');
        }
        $this->getEntityManager()->persist($meuble); // Ajout pour garantir le suivi
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

     /**
     * Compte le nombre de meubles vendus et indisponibles pour un vendeur.
     */
    public function countMeublesIndisponibles(string $cinVendeur): int
    {
        return $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->where('m.vendeur = :cinVendeur')
            ->andWhere('m.statut = :statut')
            ->setParameter('cinVendeur', $cinVendeur)
            ->setParameter('statut', 'indisponible')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Compte le nombre de meubles disponibles à vendre pour un vendeur.
     */
    public function countMeublesDisponibles(string $cinVendeur): int
    {
        return $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->where('m.vendeur = :cinVendeur')
            ->andWhere('m.statut = :statut')
            ->setParameter('cinVendeur', $cinVendeur)
            ->setParameter('statut', 'disponible')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Compte le nombre total de meubles pour un vendeur.
     */
    public function countTotalMeubles(string $cinVendeur): int
    {
        return $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->where('m.vendeur = :cinVendeur')
            ->setParameter('cinVendeur', $cinVendeur)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Compte le nombre de commandes payées contenant des meubles du vendeur.
     */
    public function countCommandesPayees(string $cinVendeur): int
    {
        return $this->getEntityManager()->createQueryBuilder()
            ->select('COUNT(DISTINCT c.id)')
            ->from(Commande::class, 'c')
            ->join('c.panier', 'p')
            ->join('p.lignesPanier', 'lp')
            ->join('lp.meuble', 'm')
            ->where('m.vendeur = :cinVendeur')
            ->andWhere('c.statut = :statut')
            ->setParameter('cinVendeur', $cinVendeur)
            ->setParameter('statut', Commande::STATUT_PAYEE)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Compte le nombre de commandes en attente contenant des meubles du vendeur.
     */
    public function countCommandesEnAttente(string $cinVendeur): int
    {
        return $this->getEntityManager()->createQueryBuilder()
            ->select('COUNT(DISTINCT c.id)')
            ->from(Commande::class, 'c')
            ->join('c.panier', 'p')
            ->join('p.lignesPanier', 'lp')
            ->join('lp.meuble', 'm')
            ->where('m.vendeur = :cinVendeur')
            ->andWhere('c.statut = :statut')
            ->setParameter('cinVendeur', $cinVendeur)
            ->setParameter('statut', Commande::STATUT_EN_ATTENTE)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Compte le nombre de commandes livrées contenant des meubles du vendeur.
     */
    public function countCommandesLivrees(string $cinVendeur): int
    {
        return $this->getEntityManager()->createQueryBuilder()
            ->select('COUNT(DISTINCT c.id)')
            ->from(Commande::class, 'c')
            ->join('c.panier', 'p')
            ->join('p.lignesPanier', 'lp')
            ->join('lp.meuble', 'm')
            ->where('m.vendeur = :cinVendeur')
            ->andWhere('c.statut = :statut')
            ->setParameter('cinVendeur', $cinVendeur)
            ->setParameter('statut', Commande::STATUT_LIVREE)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Compte le nombre de commandes annulées contenant des meubles du vendeur.
     */
    public function countCommandesAnnulees(string $cinVendeur): int
    {
        return $this->getEntityManager()->createQueryBuilder()
            ->select('COUNT(DISTINCT c.id)')
            ->from(Commande::class, 'c')
            ->join('c.panier', 'p')
            ->join('p.lignesPanier', 'lp')
            ->join('lp.meuble', 'm')
            ->where('m.vendeur = :cinVendeur')
            ->andWhere('c.statut = :statut')
            ->setParameter('cinVendeur', $cinVendeur)
            ->setParameter('statut', Commande::STATUT_ANNULEE)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Calcule le taux de commandes annulées pour les meubles du vendeur.
     */
    public function getTauxCommandesAnnulees(string $cinVendeur): float
    {
        $totalCommandes = $this->getEntityManager()->createQueryBuilder()
            ->select('COUNT(DISTINCT c.id)')
            ->from(Commande::class, 'c')
            ->join('c.panier', 'p')
            ->join('p.lignesPanier', 'lp')
            ->join('lp.meuble', 'm')
            ->where('m.vendeur = :cinVendeur')
            ->setParameter('cinVendeur', $cinVendeur)
            ->getQuery()
            ->getSingleScalarResult();

        $commandesAnnulees = $this->countCommandesAnnulees($cinVendeur);

        return $totalCommandes > 0 ? ($commandesAnnulees / $totalCommandes) * 100 : 0;
    }

    /**
     * Calcule le revenu total généré par les commandes payées des meubles du vendeur.
     */
    public function getRevenuTotal(string $cinVendeur): float
    {
        return $this->getEntityManager()->createQueryBuilder()
            ->select('SUM(c.montantTotal)')
            ->from(Commande::class, 'c')
            ->join('c.panier', 'p')
            ->join('p.lignesPanier', 'lp')
            ->join('lp.meuble', 'm')
            ->where('m.vendeur = :cinVendeur')
            ->andWhere('c.statut = :statut')
            ->setParameter('cinVendeur', $cinVendeur)
            ->setParameter('statut', Commande::STATUT_PAYEE)
            ->getQuery()
            ->getSingleScalarResult() ?? 0;
    }

    /**
     * Calcule le taux de retour des clients pour les meubles du vendeur.
     */
    public function getTauxRetourClients(string $cinVendeur): float
    {
        $qb = $this->getEntityManager()->createQueryBuilder();
        $qb->select('COUNT(DISTINCT c.acheteur)')
            ->from(Commande::class, 'c')
            ->join('c.panier', 'p')
            ->join('p.lignesPanier', 'lp')
            ->join('lp.meuble', 'm')
            ->where('m.vendeur = :cinVendeur')
            ->groupBy('c.acheteur')
            ->having('COUNT(c.id) > 1')
            ->setParameter('cinVendeur', $cinVendeur);

        $clientsRetours = $qb->getQuery()->getSingleScalarResult();

        $totalClients = $this->getEntityManager()->createQueryBuilder()
            ->select('COUNT(DISTINCT c.acheteur)')
            ->from(Commande::class, 'c')
            ->join('c.panier', 'p')
            ->join('p.lignesPanier', 'lp')
            ->join('lp.meuble', 'm')
            ->where('m.vendeur = :cinVendeur')
            ->setParameter('cinVendeur', $cinVendeur)
            ->getQuery()
            ->getSingleScalarResult();

        return $totalClients > 0 ? ($clientsRetours / $totalClients) * 100 : 0;
    }

    /**
     * Compte le nombre de meubles ajoutés récemment (derniers 30 jours) par le vendeur.
     */
    public function countMeublesAjoutesRecemment(string $cinVendeur): int
    {
        $dateLimite = new \DateTime('-30 days');
        return $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->where('m.vendeur = :cinVendeur')
            ->andWhere('m.dateEnregistrement >= :dateLimite')
            ->setParameter('cinVendeur', $cinVendeur)
            ->setParameter('dateLimite', $dateLimite)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getMonthlyRevenue(string $cinVendeur): ?array
{
    $qb = $this->createQueryBuilder('m')
        ->select('MONTH(m.dateVente) as month, SUM(m.prix) as revenue')
        ->where('m.vendeurCin = :cin')
        ->andWhere('m.dateVente IS NOT NULL')
        ->setParameter('cin', $cinVendeur)
        ->groupBy('month')
        ->orderBy('month', 'ASC');

    $result = $qb->getQuery()->getResult();
    if (empty($result)) {
        return ['labels' => [], 'data' => []];
    }

    $labels = [];
    $data = [];
    foreach ($result as $row) {
        $labels[] = date('M', mktime(0, 0, 0, $row['month'], 1));
        $data[] = $row['revenue'];
    }

    return ['labels' => $labels, 'data' => $data];
}

public function getFurnitureAddedOverTime(string $cinVendeur): ?array
{
    $qb = $this->createQueryBuilder('m')
        ->select('MONTH(m.dateAjout) as month, COUNT(m.id) as count')
        ->where('m.vendeurCin = :cin')
        ->setParameter('cin', $cinVendeur)
        ->groupBy('month')
        ->orderBy('month', 'ASC');

    $result = $qb->getQuery()->getResult();
      if (empty($result)) {
        return ['labels' => [], 'data' => []];
    }

    $labels = [];
    $data = [];
    foreach ($result as $row) {
        $labels[] = date('M', mktime(0, 0, 0, $row['month'], 1));
        $data[] = $row['count'];
    }

    return ['labels' => $labels, 'data' => $data];
}
}