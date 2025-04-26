<?php

namespace App\Entity\GestionMeubles;

use App\Entity\Utilisateur;
use App\Repository\GestionMeubles\CommandeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CommandeRepository::class)]
#[ORM\Table(name: 'commandes')]
class Commande
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Panier::class, inversedBy: 'commandes')]
    #[ORM\JoinColumn(name: 'id_panier', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Panier $panier = null;

    #[ORM\Column(type: 'string', length: 8)]
    private ?string $cinAcheteur = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class, inversedBy: 'commandes')]
    #[ORM\JoinColumn(name: 'cin_acheteur', referencedColumnName: 'cin', nullable: false, onDelete: 'CASCADE')]
    private ?Utilisateur $acheteur = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $dateCommande = null;

    #[ORM\Column(type: 'string', length: 20, options: ['default' => 'EN_ATTENTE'])]
    private ?string $statut = 'EN_ATTENTE';

    #[ORM\Column(type: 'string', length: 50)]
    private ?string $methodePaiement = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private ?float $montantTotal = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $adresseLivraison = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $sessionStripe = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $dateAnnulation = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $raisonAnnulation = null;
    #[ORM\Column(type: 'decimal', precision: 10, scale: 6, nullable: true)]
    private ?float $exchangeRate = null;

    public const STATUT_EN_ATTENTE = 'EN_ATTENTE';
    public const STATUT_PAYEE = 'PAYÉE';
    public const STATUT_LIVREE = 'LIVRÉE';
    public const STATUT_ANNULEE = 'ANNULEE';

    public const METHODE_STRIPE = 'card';
    public const METHODE_PAIEMENT_A_LA_LIVRAISON = 'Paiement a la livraison';

    public function __construct()
    {
        $this->dateCommande = new \DateTime();
    }

    // Getters et setters pour $acheteur
    public function getAcheteur(): ?Utilisateur
    {
        return $this->acheteur;
    }

    public function setAcheteur(?Utilisateur $acheteur): self
    {
        $this->acheteur = $acheteur;
        $this->cinAcheteur = $acheteur?->getCin();
        return $this;
    }

    // Autres getters et setters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPanier(): ?Panier
    {
        return $this->panier;
    }

    public function setPanier(?Panier $panier): self
    {
        $this->panier = $panier;
        return $this;
    }

    public function getCinAcheteur(): ?string
    {
        return $this->cinAcheteur;
    }

    public function setCinAcheteur(string $cinAcheteur): self
    {
        $this->cinAcheteur = $cinAcheteur;
        return $this;
    }

    public function getDateCommande(): ?\DateTimeInterface
    {
        return $this->dateCommande;
    }

    public function setDateCommande(\DateTimeInterface $dateCommande): self
    {
        $this->dateCommande = $dateCommande;
        return $this;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): self
    {
        $this->statut = $statut;
        return $this;
    }

    public function getMethodePaiement(): ?string
    {
        return $this->methodePaiement;
    }

    public function setMethodePaiement(string $methodePaiement): self
    {
        $this->methodePaiement = $methodePaiement;
        return $this;
    }

    public function getMontantTotal(): ?float
    {
        return $this->montantTotal;
    }

    public function setMontantTotal(float $montantTotal): self
    {
        $this->montantTotal = $montantTotal;
        return $this;
    }

    public function getAdresseLivraison(): ?string
    {
        return $this->adresseLivraison;
    }

    public function setAdresseLivraison(?string $adresseLivraison): self
    {
        $this->adresseLivraison = $adresseLivraison;
        return $this;
    }

    public function getSessionStripe(): ?string
    {
        return $this->sessionStripe;
    }

    public function setSessionStripe(?string $sessionStripe): self
    {
        $this->sessionStripe = $sessionStripe;
        return $this;
    }

    public function getDateAnnulation(): ?\DateTimeInterface
    {
        return $this->dateAnnulation;
    }

    public function setDateAnnulation(?\DateTimeInterface $dateAnnulation): self
    {
        $this->dateAnnulation = $dateAnnulation;
        return $this;
    }

    public function getRaisonAnnulation(): ?string
    {
        return $this->raisonAnnulation;
    }

    public function setRaisonAnnulation(?string $raisonAnnulation): self
    {
        $this->raisonAnnulation = $raisonAnnulation;
        return $this;
    }
    public function getExchangeRate(): ?float
    {
        return $this->exchangeRate;
    }

    public function setExchangeRate(?float $exchangeRate): self
    {
        $this->exchangeRate = $exchangeRate;
        return $this;
    }
    public function __toString(): string
    {
        return sprintf(
            'Commande{id: %d, idPanier: %d, cinAcheteur: %s, dateCommande: %s, statut: %s, methodePaiement: %s, montantTotal: %.2f}',
            $this->id ?? 0,
            $this->panier?->getId() ?? 0,
            $this->cinAcheteur ?? '',
            $this->dateCommande?->format('Y-m-d H:i:s') ?? '',
            $this->statut ?? '',
            $this->methodePaiement ?? '',
            $this->montantTotal ?? 0.00
        );
    }
}
?>