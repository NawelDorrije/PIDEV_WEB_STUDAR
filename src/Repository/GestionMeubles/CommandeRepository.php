<?php

namespace App\Repository\GestionMeubles;

use App\Entity\GestionMeubles\Commande;
use App\Entity\GestionMeubles\Meuble;
use App\Entity\GestionMeubles\Panier;
use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Psr\Log\LoggerInterface;

/**
 * @extends ServiceEntityRepository<Commande>
 */
class CommandeRepository extends ServiceEntityRepository
{
    private EntityManagerInterface $entityManager;
    private MailerInterface $mailer;
    private LoggerInterface $logger;

    public function __construct(
        ManagerRegistry $registry,
        EntityManagerInterface $entityManager,
        MailerInterface $mailer,
        LoggerInterface $logger
    ) {
        parent::__construct($registry, Commande::class);
        $this->entityManager = $entityManager;
        $this->mailer = $mailer;
        $this->logger = $logger;
    }

    /**
     * Ajoute une commande et met à jour le panier et les meubles associés.
     * Retourne l'ID de la commande créée.
     *
     * @param Commande $commande
     * @return int|null
     */
    public function ajouterCommande(Commande $commande): ?int
    {
        $this->entityManager->beginTransaction();
    
        try {
            // 1. Vérifier le panier
            $panier = $commande->getPanier();
            if (!$panier) {
                throw new \Exception('Panier non trouvé.');
            }
            if ($panier->getStatut() !== Panier::STATUT_EN_COURS) {
                throw new \Exception('Le panier doit être en statut EN_COURS pour créer une commande.');
            }
    
            // 2. Mettre à jour le statut du panier
            $panier->setStatut(Panier::STATUT_VALIDE);
            $panier->setDateValidation(new \DateTime());
            $this->entityManager->persist($panier);
    
            // 3. Mettre à jour le statut des meubles associés au panier
            $lignesPanier = $panier->getLignesPanier();
            foreach ($lignesPanier as $ligne) {
                $meuble = $ligne->getMeuble();
                $meuble->setStatut('indisponible');
                $this->entityManager->persist($meuble);
            }
    
            // 4. Ajouter la commande
            $this->entityManager->persist($commande);
            $this->entityManager->flush();
    
            // 5. Valider la transaction
            $this->entityManager->commit();
    
            return $commande->getId();
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw new \RuntimeException('Erreur lors de la création de la commande : ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Ajoute une commande sans retourner l'ID (équivalent à ajouter(Commande)).
     *
     * @param Commande $commande
     */
    public function add(Commande $commande): void
    {
        $this->ajouterCommande($commande);
    }

    /**
     * Met à jour une commande existante.
     *
     * @param Commande $commande
     */
    public function update(Commande $commande): void
    {
        $this->entityManager->persist($commande);
        $this->entityManager->flush();
    }

    /**
     * Supprime une commande.
     *
     * @param Commande $commande
     */
    public function remove(Commande $commande): void
    {
        $this->entityManager->remove($commande);
        $this->entityManager->flush();
    }

    /**
     * Récupère toutes les commandes.
     *
     * @return Commande[]
     */
    public function findAll(): array
    {
        return $this->createQueryBuilder('c')
            ->orderBy('c.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère une commande par son ID.
     *
     * @param int $id
     * @return Commande|null
     */
    public function findById(int $id): ?Commande
    {
        return $this->find($id);
    }

    /**
     * Récupère les commandes d'un acheteur.
     *
     * @param Utilisateur $acheteur
     * @return Commande[]
     */
    public function findByAcheteur(Utilisateur $acheteur): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.acheteur = :acheteur')
            ->setParameter('acheteur', $acheteur)
            ->orderBy('c.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les commandes contenant des meubles d'un vendeur spécifique.
     *
     * @param Utilisateur $vendeur
     * @return Commande[]
     */
    public function findByVendeur(Utilisateur $vendeur): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.panier', 'p')
            ->leftJoin('p.lignesPanier', 'lp')
            ->leftJoin('lp.meuble', 'm')
            ->leftJoin('m.vendeur', 'v')
            ->andWhere('v = :vendeur')
            ->setParameter('vendeur', $vendeur)
            ->orderBy('c.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Met à jour le statut d'une commande et des meubles associés pour un vendeur.
     *
     * @param int $idCommande
     * @param string $nouveauStatut
     * @param Utilisateur $vendeur
     * @return bool
     */
    public function updateStatutCommande(int $idCommande, string $nouveauStatut, Utilisateur $vendeur): bool
    {
        $this->logger->info('Début de la mise à jour du statut de la commande ID: ' . $idCommande);

        $this->entityManager->beginTransaction();

        try {
            $commande = $this->createQueryBuilder('c')
                ->leftJoin('c.panier', 'p')
                ->leftJoin('p.lignesPanier', 'lp')
                ->leftJoin('lp.meuble', 'm')
                ->leftJoin('m.vendeur', 'v')
                ->andWhere('c.id = :id')
                ->andWhere('v = :vendeur')
                ->setParameter('id', $idCommande)
                ->setParameter('vendeur', $vendeur)
                ->getQuery()
                ->getOneOrNullResult();

            if (!$commande) {
                $this->logger->warning('Commande non trouvée ou non associée au vendeur: ID ' . $idCommande);
                return false;
            }

            // Liste des statuts valides
            $statutsValides = [
                Commande::STATUT_EN_ATTENTE,
                Commande::STATUT_PAYEE,
                Commande::STATUT_LIVREE,
                Commande::STATUT_ANNULEE,
                'CONFIRMÉE'
            ];

            if (!in_array($nouveauStatut, $statutsValides)) {
                $this->logger->warning('Statut invalide pour la commande ID: ' . $idCommande . ' - Statut: ' . $nouveauStatut);
                return false;
            }

            $commande->setStatut($nouveauStatut);
            $this->entityManager->persist($commande);

            // Mettre à jour le statut des meubles associés
            $lignesPanier = $commande->getPanier()->getLignesPanier();
            foreach ($lignesPanier as $ligne) {
                $meuble = $ligne->getMeuble();
                if ($meuble->getVendeur() === $vendeur) {
                    if ($nouveauStatut === Commande::STATUT_ANNULEE) {
                        $meuble->setStatut('disponible');
                    } else {
                        $meuble->setStatut('indisponible');
                    }
                    $this->entityManager->persist($meuble);
                }
            }

            $this->entityManager->flush();
            $this->entityManager->commit();

            $this->logger->info('Statut de la commande mis à jour avec succès: ID ' . $idCommande . ' - Nouveau statut: ' . $nouveauStatut);

            // Envoi d'un email à l'acheteur pour l'informer du changement de statut
            try {
                $this->envoyerEmailChangementStatut($commande, $nouveauStatut);
                $this->logger->info('Email de changement de statut envoyé pour la commande ID: ' . $idCommande);
            } catch (\Exception $e) {
                $this->logger->error('Erreur lors de l\'envoi de l\'email de changement de statut pour la commande ID: ' . $idCommande . ' - ' . $e->getMessage());
            }

            return true;
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            $this->logger->error('Erreur lors de la mise à jour du statut de la commande ID: ' . $idCommande . ' - ' . $e->getMessage());
            throw new \RuntimeException('Erreur lors de la mise à jour du statut : ' . $e->getMessage(), 0, $e);
        }
    }

    public function annulerCommande(int $idCommande, string $raisonAnnulation, Utilisateur $acheteur): bool
    {
        $this->logger->info('Début de l\'annulation de la commande ID: ' . $idCommande);

        $this->entityManager->beginTransaction();

        try {
            $commande = $this->createQueryBuilder('c')
                ->andWhere('c.id = :id')
                ->andWhere('c.statut != :annulee')
                ->andWhere('c.dateCommande >= :dateLimit')
                ->andWhere('c.acheteur = :acheteur')
                ->setParameter('id', $idCommande)
                ->setParameter('annulee', Commande::STATUT_ANNULEE)
                ->setParameter('dateLimit', new \DateTime('-24 hours'))
                ->setParameter('acheteur', $acheteur)
                ->getQuery()
                ->getOneOrNullResult();

            if (!$commande) {
                $this->logger->warning('Commande non trouvée ou non annulable: ID ' . $idCommande);
                return false;
            }

            $commande->setStatut(Commande::STATUT_ANNULEE);
            $commande->setDateAnnulation(new \DateTime());
            $commande->setRaisonAnnulation($raisonAnnulation);
            $this->entityManager->persist($commande);

            $lignesPanier = $commande->getPanier()->getLignesPanier();
            foreach ($lignesPanier as $ligne) {
                $meuble = $ligne->getMeuble();
                $meuble->setStatut('disponible');
                $this->entityManager->persist($meuble);
            }

            $this->entityManager->flush();
            $this->entityManager->commit();

            $this->logger->info('Commande annulée avec succès: ID ' . $idCommande);

            // Envoi des emails après la transaction
            try {
                $this->envoyerEmailsAnnulation($commande, $raisonAnnulation);
                $this->logger->info('Emails d\'annulation envoyés pour la commande ID: ' . $idCommande);
            } catch (\Exception $e) {
                $this->logger->error('Erreur lors de l\'envoi des emails pour la commande ID: ' . $idCommande . ' - ' . $e->getMessage());
            }

            return true;
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            $this->logger->error('Erreur lors de l\'annulation de la commande ID: ' . $idCommande . ' - ' . $e->getMessage());
            throw new \RuntimeException('Erreur lors de l\'annulation : ' . $e->getMessage(), 0, $e);
        }
    }

    private function envoyerEmailsAnnulation(Commande $commande, string $raisonAnnulation): void
    {
        $acheteurEmail = $commande->getAcheteur()->getEmail();
        $messageAcheteur = sprintf(
            "Bonjour,\n\nVotre commande n°%d a été annulée.\nRaison : %s\n\nCordialement,\nL'équipe",
            $commande->getId(),
            $raisonAnnulation
        );

        $emailAcheteur = (new Email())
            ->from('no-reply@votreapp.com')
            ->to($acheteurEmail)
            ->subject('Annulation de votre commande')
            ->text($messageAcheteur);
        $this->mailer->send($emailAcheteur);

        $meublesParVendeur = [];
        foreach ($commande->getPanier()->getLignesPanier() as $ligne) {
            $meuble = $ligne->getMeuble();
            $vendeur = $meuble->getVendeur();
            $meublesParVendeur[$vendeur->getCin()][] = $meuble;
        }

        foreach ($meublesParVendeur as $cinVendeur => $meubles) {
            $vendeur = $meubles[0]->getVendeur();
            $vendeurEmail = $vendeur->getEmail();
            $messageVendeur = "Bonjour,\n\nLa commande n°{$commande->getId()} contenant vos articles a été annulée :\n";
            foreach ($meubles as $meuble) {
                $messageVendeur .= "- {$meuble->getNom()} ({$meuble->getPrix()} TND)\n";
            }
            $messageVendeur .= "\nRaison : {$raisonAnnulation}\n\nCordialement,\nL'équipe";

            $emailVendeur = (new Email())
                ->from('no-reply@votreapp.com')
                ->to($vendeurEmail)
                ->subject('Annulation de commande')
                ->text($messageVendeur);
            $this->mailer->send($emailVendeur);
        }
    }

    /**
     * Envoie un email à l'acheteur pour l'informer du changement de statut de la commande.
     *
     * @param Commande $commande
     * @param string $nouveauStatut
     */
    private function envoyerEmailChangementStatut(Commande $commande, string $nouveauStatut): void
    {
        $acheteurEmail = $commande->getAcheteur()->getEmail();
        $messageAcheteur = sprintf(
            "Bonjour,\n\nLe statut de votre commande n°%d a été mis à jour.\nNouveau statut : %s\n\nCordialement,\nL'équipe",
            $commande->getId(),
            $nouveauStatut
        );

        $emailAcheteur = (new Email())
            ->from('no-reply@votreapp.com')
            ->to($acheteurEmail)
            ->subject('Mise à jour du statut de votre commande')
            ->text($messageAcheteur);
        $this->mailer->send($emailAcheteur);
    }

    public function getChiffreAffairesTotal(string $statut = '', string $periode = 'all'): float
    {
        $qb = $this->createQueryBuilder('c')
            ->select('COALESCE(SUM(c.montantTotal), 0)')
            ->where('c.statut != :annulee')
            ->setParameter('annulee', Commande::STATUT_ANNULEE);

        if ($statut) {
            $qb->andWhere('c.statut = :statut')
               ->setParameter('statut', $statut);
        }

        if ($periode === 'month') {
            $qb->andWhere('c.dateCommande >= :start')
               ->setParameter('start', (new \DateTime())->modify('first day of this month')->setTime(0, 0));
        } elseif ($periode === 'year') {
            $qb->andWhere('c.dateCommande >= :start')
               ->setParameter('start', (new \DateTime())->modify('first day of this year')->setTime(0, 0));
        }

        return (float) $qb->getQuery()->getSingleScalarResult();
    }

    public function getTopVendeur(string $periode = 'all'): ?array
    {
        $qb = $this->createQueryBuilder('c')
            ->select('u.nom, u.prenom, u.cin, COALESCE(SUM(c.montantTotal), 0) as totalVentes')
            ->leftJoin('c.panier', 'p')
            ->leftJoin('p.lignesPanier', 'lp')
            ->leftJoin('lp.meuble', 'm')
            ->leftJoin('m.vendeur', 'u')
            ->where('c.statut != :annulee')
            ->setParameter('annulee', Commande::STATUT_ANNULEE)
            ->groupBy('u.cin, u.nom, u.prenom')
            ->orderBy('totalVentes', 'DESC')
            ->setMaxResults(1);

        if ($periode === 'month') {
            $qb->andWhere('c.dateCommande >= :start')
               ->setParameter('start', (new \DateTime())->modify('first day of this month')->setTime(0, 0));
        } elseif ($periode === 'year') {
            $qb->andWhere('c.dateCommande >= :start')
               ->setParameter('start', (new \DateTime())->modify('first day of this year')->setTime(0, 0));
        }

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function getCommandesParStatut(string $periode = 'all'): array
    {
        $qb = $this->createQueryBuilder('c')
            ->select('c.statut, COUNT(c.id) as nombre')
            ->groupBy('c.statut');
    
        if ($periode === 'month') {
            $qb->andWhere('c.dateCommande >= :start')
               ->setParameter('start', (new \DateTime())->modify('first day of this month')->setTime(0, 0));
        } elseif ($periode === 'year') {
            $qb->andWhere('c.dateCommande >= :start')
               ->setParameter('start', (new \DateTime())->modify('first day of this year')->setTime(0, 0));
        }
    
        $results = $qb->getQuery()->getResult();
        $data = [
            Commande::STATUT_EN_ATTENTE => 0,
            Commande::STATUT_PAYEE => 0,
            Commande::STATUT_LIVREE => 0,
            Commande::STATUT_ANNULEE => 0,
        ];
        foreach ($results as $row) {
            $data[$row['statut']] = (int) $row['nombre'];
        }
        return $data;
    }

    /**
     * Récupère le nombre de commandes par statut pour un vendeur spécifique.
     *
     * @param Utilisateur $vendeur
     * @param string $periode
     * @return array
     */
    public function getCommandesParStatutPourVendeur(Utilisateur $vendeur, string $periode = 'all'): array
    {
        $qb = $this->createQueryBuilder('c')
            ->select('c.statut, COUNT(c.id) as nombre')
            ->leftJoin('c.panier', 'p')
            ->leftJoin('p.lignesPanier', 'lp')
            ->leftJoin('lp.meuble', 'm')
            ->leftJoin('m.vendeur', 'v')
            ->andWhere('v = :vendeur')
            ->setParameter('vendeur', $vendeur)
            ->groupBy('c.statut');

        if ($periode === 'month') {
            $qb->andWhere('c.dateCommande >= :start')
               ->setParameter('start', (new \DateTime())->modify('first day of this month')->setTime(0, 0));
        } elseif ($periode === 'year') {
            $qb->andWhere('c.dateCommande >= :start')
               ->setParameter('start', (new \DateTime())->modify('first day of this year')->setTime(0, 0));
        }

        $results = $qb->getQuery()->getResult();
        $data = [
            Commande::STATUT_EN_ATTENTE => 0,
            Commande::STATUT_PAYEE => 0,
            Commande::STATUT_LIVREE => 0,
            Commande::STATUT_ANNULEE => 0,
            'CONFIRMÉE' => 0,
        ];
        foreach ($results as $row) {
            $data[$row['statut']] = (int) $row['nombre'];
        }
        return $data;
    }

    public function getChiffreAffairesParMois(string $periode = 'all'): array
    {
        $qb = $this->createQueryBuilder('c')
            ->select("DATE_FORMAT(c.dateCommande, '%Y-%m') as mois, COALESCE(SUM(c.montantTotal), 0) as montant")
            ->where('c.statut != :ANNULEE')
            ->setParameter('ANNULEE', Commande::STATUT_ANNULEE)
            ->groupBy('mois')
            ->orderBy('mois', 'ASC');
    
        if ($periode === 'month') {
            $qb->andWhere('c.dateCommande >= :start')
               ->setParameter('start', (new \DateTime())->modify('first day of this month')->setTime(0, 0));
        } elseif ($periode === 'year') {
            $qb->andWhere('c.dateCommande >= :start')
               ->setParameter('start', (new \DateTime())->modify('first day of this year')->setTime(0, 0));
        }
    
        $results = $qb->getQuery()->getResult();
        $data = [];
        foreach ($results as $row) {
            $data[$row['mois']] = (float) $row['montant'];
        }
        return $data;
    }

    /**
     * Récupère le chiffre d'affaires par mois pour un vendeur spécifique.
     *
     * @param Utilisateur $vendeur
     * @param string $periode
     * @return array
     */
    public function getChiffreAffairesParMoisPourVendeur(Utilisateur $vendeur, string $periode = 'all'): array
    {
        $qb = $this->createQueryBuilder('c')
            ->select("DATE_FORMAT(c.dateCommande, '%Y-%m') as mois, COALESCE(SUM(c.montantTotal), 0) as montant")
            ->leftJoin('c.panier', 'p')
            ->leftJoin('p.lignesPanier', 'lp')
            ->leftJoin('lp.meuble', 'm')
            ->leftJoin('m.vendeur', 'v')
            ->andWhere('v = :vendeur')
            ->andWhere('c.statut != :ANNULEE')
            ->setParameter('vendeur', $vendeur)
            ->setParameter('ANNULEE', Commande::STATUT_ANNULEE)
            ->groupBy('mois')
            ->orderBy('mois', 'ASC');

        if ($periode === 'month') {
            $qb->andWhere('c.dateCommande >= :start')
               ->setParameter('start', (new \DateTime())->modify('first day of this month')->setTime(0, 0));
        } elseif ($periode === 'year') {
            $qb->andWhere('c.dateCommande >= :start')
               ->setParameter('start', (new \DateTime())->modify('first day of this year')->setTime(0, 0));
        }

        $results = $qb->getQuery()->getResult();
        $data = [];
        foreach ($results as $row) {
            $data[$row['mois']] = (float) $row['montant'];
        }
        return $data;
    }

    public function getVentesParJour(string $periode = 'all'): array
    {
        $qb = $this->createQueryBuilder('c')
            ->select("DATE_FORMAT(c.dateCommande, '%Y-%m-%d') as date, COALESCE(SUM(c.montantTotal), 0) as montant")
            ->where('c.statut != :annulee')
            ->setParameter('annulee', Commande::STATUT_ANNULEE)
            ->groupBy('date')
            ->orderBy('date', 'ASC');

        if ($periode === 'month') {
            $qb->andWhere('c.dateCommande >= :start')
               ->setParameter('start', (new \DateTime())->modify('first day of this month')->setTime(0, 0));
        } elseif ($periode === 'year') {
            $qb->andWhere('c.dateCommande >= :start')
               ->setParameter('start', (new \DateTime())->modify('first day of this year')->setTime(0, 0));
        }

        $results = $qb->getQuery()->getResult();
        $data = [];
        foreach ($results as $row) {
            $data[$row['date']] = ['montant' => (float) $row['montant']];
        }
        return $data;
    }

    /**
     * Récupère les ventes par jour pour un vendeur spécifique.
     *
     * @param Utilisateur $vendeur
     * @param string $periode
     * @return array
     */
    public function getVentesParJourPourVendeur(Utilisateur $vendeur, string $periode = 'all'): array
    {
        $qb = $this->createQueryBuilder('c')
            ->select("DATE_FORMAT(c.dateCommande, '%Y-%m-%d') as date, COALESCE(SUM(c.montantTotal), 0) as montant")
            ->leftJoin('c.panier', 'p')
            ->leftJoin('p.lignesPanier', 'lp')
            ->leftJoin('lp.meuble', 'm')
            ->leftJoin('m.vendeur', 'v')
            ->andWhere('v = :vendeur')
            ->andWhere('c.statut != :annulee')
            ->setParameter('vendeur', $vendeur)
            ->setParameter('annulee', Commande::STATUT_ANNULEE)
            ->groupBy('date')
            ->orderBy('date', 'ASC');

        if ($periode === 'month') {
            $qb->andWhere('c.dateCommande >= :start')
               ->setParameter('start', (new \DateTime())->modify('first day of this month')->setTime(0, 0));
        } elseif ($periode === 'year') {
            $qb->andWhere('c.dateCommande >= :start')
               ->setParameter('start', (new \DateTime())->modify('first day of this year')->setTime(0, 0));
        }

        $results = $qb->getQuery()->getResult();
        $data = [];
        foreach ($results as $row) {
            $data[$row['date']] = ['montant' => (float) $row['montant']];
        }
        return $data;
    }
}