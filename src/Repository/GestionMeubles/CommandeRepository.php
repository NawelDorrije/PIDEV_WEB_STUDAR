<?php

namespace App\Repository\GestionMeubles;

use App\Entity\GestionMeubles\Commande;
use App\Entity\GestionMeubles\Meuble;
use App\Entity\GestionMeubles\Panier;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * @extends ServiceEntityRepository<Commande>
 */
class CommandeRepository extends ServiceEntityRepository
{
    private EntityManagerInterface $entityManager;
    private MailerInterface $mailer;

    public function __construct(ManagerRegistry $registry, EntityManagerInterface $entityManager, MailerInterface $mailer)
    {
        parent::__construct($registry, Commande::class);
        $this->entityManager = $entityManager;
        $this->mailer = $mailer;
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
            // 1. Mettre à jour le statut du panier
            $panier = $commande->getPanier();
            if (!$panier) {
                throw new \Exception('Panier non trouvé.');
            }
            $panier->setStatut(Panier::STATUT_VALIDE);
            $panier->setDateValidation(new \DateTime());
            $this->entityManager->persist($panier);

            // 2. Mettre à jour le statut des meubles associés au panier
            $lignesPanier = $panier->getLignesPanier();
            foreach ($lignesPanier as $ligne) {
                $meuble = $ligne->getMeuble();
                $meuble->setStatut('indisponible');
                $this->entityManager->persist($meuble);
            }

            // 3. Ajouter la commande
            $this->entityManager->persist($commande);
            $this->entityManager->flush();

            // 4. Valider la transaction
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
        $this->ajouterCommande($commande); // Réutilise la logique ci-dessus
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
     * Récupère les commandes par CIN acheteur.
     *
     * @param string $cinAcheteur
     * @return Commande[]
     */
    public function findByCinAcheteur(string $cinAcheteur): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.cinAcheteur = :cin')
            ->setParameter('cin', $cinAcheteur)
            ->getQuery()
            ->getResult();
    }

    /**
     * Met à jour la session Stripe et le statut d'une commande.
     *
     * @param int $commandeId
     * @param string $sessionStripeId
     */
    public function updateSessionStripe(int $commandeId, string $sessionStripeId): void
    {
        $commande = $this->find($commandeId);
        if ($commande) {
            $commande->setSessionStripe($sessionStripeId);
            $commande->setStatut(Commande::STATUT_PAYEE);
            $this->entityManager->persist($commande);
            $this->entityManager->flush();
        }
    }

    /**
     * Récupère la session Stripe par ID de commande.
     *
     * @param int $commandeId
     * @return string|null
     */
    public function getSessionStripeById(int $commandeId): ?string
    {
        $commande = $this->find($commandeId);
        return $commande ? $commande->getSessionStripe() : null;
    }

    /**
     * Annule une commande et remet les meubles à "disponible".
     *
     * @param int $idCommande
     * @param string $raisonAnnulation
     * @param string $cinAcheteur
     * @return bool
     */
    public function annulerCommande(int $idCommande, string $raisonAnnulation, string $cinAcheteur): bool
    {
        $this->entityManager->beginTransaction();

        try {
            $commande = $this->createQueryBuilder('c')
                ->andWhere('c.id = :id')
                ->andWhere('c.statut != :annulee')
                ->andWhere('c.dateCommande >= :dateLimit')
                ->andWhere('c.cinAcheteur = :cin')
                ->setParameter('id', $idCommande)
                ->setParameter('annulee', Commande::STATUT_ANNULEE)
                ->setParameter('dateLimit', new \DateTime('-24 hours'))
                ->setParameter('cin', $cinAcheteur)
                ->getQuery()
                ->getOneOrNullResult();

            if (!$commande) {
                return false;
            }

            // Mettre à jour la commande
            $commande->setStatut(Commande::STATUT_ANNULEE);
            $commande->setDateAnnulation(new \DateTime());
            $commande->setRaisonAnnulation($raisonAnnulation);
            $this->entityManager->persist($commande);

            // Rendre les meubles disponibles
            $lignesPanier = $commande->getPanier()->getLignesPanier();
            foreach ($lignesPanier as $ligne) {
                $meuble = $ligne->getMeuble();
                $meuble->setStatut('disponible');
                $this->entityManager->persist($meuble);
            }

            $this->entityManager->flush();
            $this->entityManager->commit();

            // Envoyer les emails
            $this->envoyerEmailsAnnulation($commande, $raisonAnnulation);

            return true;
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw new \RuntimeException('Erreur lors de l\'annulation de la commande : ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Envoie les emails d'annulation à l'acheteur et aux vendeurs.
     *
     * @param Commande $commande
     * @param string $raisonAnnulation
     */
    private function envoyerEmailsAnnulation(Commande $commande, string $raisonAnnulation): void
    {
        // Note : Sans entité Utilisateur, on ne peut pas récupérer les emails.
        // Simulons un email fictif pour l'acheteur et les vendeurs.
        $acheteurEmail = 'acheteur@example.com'; // À remplacer par une vraie logique
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

        // Regrouper les meubles par vendeur
        $meublesParVendeur = [];
        foreach ($commande->getPanier()->getLignesPanier() as $ligne) {
            $meuble = $ligne->getMeuble();
            $cinVendeur = $meuble->getCinVendeur();
            $meublesParVendeur[$cinVendeur][] = $meuble;
        }

        foreach ($meublesParVendeur as $cinVendeur => $meubles) {
            $vendeurEmail = 'vendeur_' . $cinVendeur . '@example.com'; // À remplacer
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
     * Filtre les commandes par statut pour un acheteur.
     *
     * @param string $statut
     * @param string $cinAcheteur
     * @return Commande[]
     */
    public function filterByStatut(string $statut, string $cinAcheteur): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.statut = :statut')
            ->andWhere('c.cinAcheteur = :cin')
            ->setParameter('statut', $statut)
            ->setParameter('cin', $cinAcheteur)
            ->getQuery()
            ->getResult();
    }

    /**
     * Filtre les commandes par méthode de paiement pour un acheteur.
     *
     * @param string $methodePaiement
     * @param string $cinAcheteur
     * @return Commande[]
     */
    public function filterByMethodePaiement(string $methodePaiement, string $cinAcheteur): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.methodePaiement = :methode')
            ->andWhere('c.cinAcheteur = :cin')
            ->setParameter('methode', $methodePaiement)
            ->setParameter('cin', $cinAcheteur)
            ->getQuery()
            ->getResult();
    }

    /**
     * Calcule le chiffre d'affaires pour un vendeur.
     *
     * @param string $cinVendeur
     * @return float
     */
    public function getChiffreAffairesPourVendeur(string $cinVendeur): float
    {
        $qb = $this->createQueryBuilder('c')
            ->select('SUM(m.prix) as chiffreAffaires')
            ->join('c.panier', 'p')
            ->join('p.lignesPanier', 'lp')
            ->join('lp.meuble', 'm')
            ->andWhere('m.cinVendeur = :cin')
            ->andWhere('c.statut IN (:payee, :enAttente)')
            ->setParameter('cin', $cinVendeur)
            ->setParameter('payee', Commande::STATUT_PAYEE)
            ->setParameter('enAttente', Commande::STATUT_EN_ATTENTE);

        $result = $qb->getQuery()->getSingleScalarResult();
        return $result ?? 0.0;
    }

    /**
     * Retourne le nombre de commandes par statut pour un acheteur.
     *
     * @param string $cinAcheteur
     * @return array<StatutCommande, int>
     */
    public function getNombreCommandesParStatut(string $cinAcheteur): array
    {
        $qb = $this->createQueryBuilder('c')
            ->select('c.statut, COUNT(c.id) as count')
            ->join('c.panier', 'p')
            ->join('p.lignesPanier', 'lp')
            ->join('lp.meuble', 'm')
            ->andWhere('c.cinAcheteur = :cin')
            ->andWhere('m.statut = :indisponible')
            ->setParameter('cin', $cinAcheteur)
            ->setParameter('indisponible', 'indisponible')
            ->groupBy('c.statut');

        $results = $qb->getQuery()->getResult();

        $commandesParStatut = [
            Commande::STATUT_EN_ATTENTE => 0,
            Commande::STATUT_PAYEE => 0,
            Commande::STATUT_LIVREE => 0,
            Commande::STATUT_ANNULEE => 0,
        ];

        foreach ($results as $result) {
            $commandesParStatut[$result['statut']] = (int)$result['count'];
        }

        return $commandesParStatut;
    }
}