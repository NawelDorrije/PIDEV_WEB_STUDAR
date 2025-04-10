<?php

namespace App\Entity\GestionMeubles;

use App\Entity\Utilisateur;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'paniers')]
class Panier
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 20)]
    private ?string $cinAcheteur = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(name: 'cin_acheteur', referencedColumnName: 'cin', onDelete: 'CASCADE')]
    private ?Utilisateur $acheteur = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $dateAjout = null;

    #[ORM\Column(type: 'string', length: 20, options: ['default' => 'EN_COURS'])]
    private ?string $statut = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $dateValidation = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $dateAnnulation = null;

    #[ORM\OneToMany(mappedBy: 'panier', targetEntity: LignePanier::class, cascade: ['persist', 'remove'])]
    private Collection $lignesPanier;
    #[ORM\OneToMany(mappedBy: 'panier', targetEntity: Commande::class, cascade: ['persist', 'remove'])]
    private Collection $commandes;
    public function __construct()
    {
        $this->dateAjout = new \DateTime();
        $this->lignesPanier = new ArrayCollection();
        $this->commandes = new ArrayCollection(); // Initialisation des commandes
    }

    public const STATUT_EN_COURS = 'EN_COURS';
    public const STATUT_VALIDE = 'VALIDE';
    public const STATUT_ANNULE = 'ANNULE';

    // Getters et setters pour $acheteur
    public function getAcheteur(): ?Utilisateur
    {
        return $this->acheteur;
    }

    public function setAcheteur(?Utilisateur $acheteur): self
    {
        $this->acheteur = $acheteur;
        // Synchronise cinAcheteur avec l'utilisateur
        $this->cinAcheteur = $acheteur?->getCin();
        return $this;
    }

    // Garde les autres getters/setters existants
    public function getId(): ?int
    {
        return $this->id;
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

    public function getDateAjout(): ?\DateTimeInterface
    {
        return $this->dateAjout;
    }

    public function setDateAjout(?\DateTimeInterface $dateAjout): self
    {
        $this->dateAjout = $dateAjout;
        return $this;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(?string $statut): self
    {
        $this->statut = $statut;
        return $this;
    }

    public function getDateValidation(): ?\DateTimeInterface
    {
        return $this->dateValidation;
    }

    public function setDateValidation(?\DateTimeInterface $dateValidation): self
    {
        $this->dateValidation = $dateValidation;
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

    public function getLignesPanier(): Collection
    {
        return $this->lignesPanier;
    }

    public function addLignePanier(LignePanier $lignePanier): self
    {
        if (!$this->lignesPanier->contains($lignePanier)) {
            $this->lignesPanier->add($lignePanier);
            $lignePanier->setPanier($this);
        }
        return $this;
    }

    public function removeLignePanier(LignePanier $lignePanier): self
    {
        if ($this->lignesPanier->removeElement($lignePanier)) {
            if ($lignePanier->getPanier() === $this) {
                $lignePanier->setPanier(null);
            }
        }
        return $this;
    }
// Getters et setters pour $commandes
public function getCommandes(): Collection
{
    return $this->commandes;
}

public function addCommande(Commande $commande): self
{
    if (!$this->commandes->contains($commande)) {
        $this->commandes->add($commande);
        $commande->setPanier($this);
    }
    return $this;
}

public function removeCommande(Commande $commande): self
{
    if ($this->commandes->removeElement($commande)) {
        if ($commande->getPanier() === $this) {
            $commande->setPanier(null);
        }
    }
    return $this;
}
    public function __toString(): string
    {
        return sprintf(
            'Panier{id: %d, cinAcheteur: %s, dateAjout: %s, statut: %s, dateValidation: %s, dateAnnulation: %s}',
            $this->id ?? 0,
            $this->cinAcheteur ?? '',
            $this->dateAjout?->format('Y-m-d H:i:s') ?? '',
            $this->statut ?? '',
            $this->dateValidation?->format('Y-m-d H:i:s') ?? '',
            $this->dateAnnulation?->format('Y-m-d H:i:s') ?? ''
        );
    }
}