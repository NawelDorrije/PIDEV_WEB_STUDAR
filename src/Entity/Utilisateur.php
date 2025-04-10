<?php

namespace App\Entity;

use App\Entity\GestionMeubles\Commande;
use App\Entity\GestionMeubles\Meuble;
use App\Entity\GestionMeubles\Panier;
use App\Enums\RoleUtilisateur;
use App\Repository\UtilisateurRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

#[ORM\Entity(repositoryClass: UtilisateurRepository::class)]
#[UniqueEntity(fields: ['email'], message: 'Cet email est déjà utilisé.')]
#[UniqueEntity(fields: ['cin'], message: 'Ce CIN est déjà enregistré.')]
class Utilisateur implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\Column(length: 8, unique: true)]
    #[Assert\Length(exactly: 8, exactMessage: 'Le CIN doit contenir exactement 8 chiffres.')]
    #[Assert\Regex(pattern: '/^\d+$/', message: 'Le CIN ne doit contenir que des chiffres.')]
    private ?string $cin = null;

    #[ORM\Column(length: 50)]
    private ?string $nom = null;

    #[ORM\Column(length: 50)]
    private ?string $prenom = null;

    #[ORM\Column(length: 100)]
    private ?string $email = null;

    #[ORM\Column(length: 255)]
    private ?string $mdp = null;

    #[ORM\Column(name: 'numTel', length: 15, nullable: true)]
    private ?string $numTel = null;

    #[ORM\Column(type: 'role_enum')]
    private ?RoleUtilisateur $role = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $reset_code = null;

    #[ORM\Column]
    private ?bool $blocked = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $created_at = null;

    #[ORM\OneToMany(mappedBy: 'vendeur', targetEntity: Meuble::class, cascade: ['persist', 'remove'])]
    private Collection $meubles;
    #[ORM\OneToMany(mappedBy: 'acheteur', targetEntity: Panier::class, cascade: ['persist', 'remove'])]
    private Collection $paniers;
    #[ORM\OneToMany(mappedBy: 'acheteur', targetEntity: Commande::class, cascade: ['persist', 'remove'])]
    private Collection $commandes;
    public function __construct()
    {
        $this->meubles = new ArrayCollection();
        $this->paniers = new ArrayCollection(); // Initialisation de la collection des paniers
        $this->commandes = new ArrayCollection(); // Initialisation des commandes
        $this->role = RoleUtilisateur::ADMIN; // Valeur par défaut
        $this->blocked = false; // Valeur par défaut raisonnable
    }

    // Getters et Setters

    public function getCin(): ?string
    {
        return $this->cin;
    }

    public function setCin(string $cin): self
    {
        $this->cin = $cin;
        return $this;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): self
    {
        $this->nom = $nom;
        return $this;
    }

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(string $prenom): self
    {
        $this->prenom = $prenom;
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function getMdp(): ?string
    {
        return $this->mdp;
    }

    public function setMdp(string $mdp): self
    {
        $this->mdp = $mdp;
        return $this;
    }

    public function getNumTel(): ?string
    {
        return $this->numTel;
    }

    public function setNumTel(?string $numTel): self
    {
        $this->numTel = $numTel;
        return $this;
    }

    public function getRole(): ?RoleUtilisateur
    {
        return $this->role;
    }

    public function getRoleValue(): ?string
    {
        return $this->role?->value;
    }

    public function setRole(RoleUtilisateur $role): self
    {
        $this->role = $role;
        return $this;
    }

    public function getResetCode(): ?string
    {
        return $this->reset_code;
    }

    public function setResetCode(?string $reset_code): self
    {
        $this->reset_code = $reset_code;
        return $this;
    }

    public function isBlocked(): ?bool
    {
        return $this->blocked;
    }

    public function setBlocked(bool $blocked): self
    {
        $this->blocked = $blocked;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->created_at;
    }

    public function setCreatedAt(?\DateTimeImmutable $created_at): self
    {
        $this->created_at = $created_at;
        return $this;
    }

    /**
     * @return Collection<int, Meuble>
     */
    public function getMeubles(): Collection
    {
        return $this->meubles;
    }

    public function addMeuble(Meuble $meuble): self
    {
        if (!$this->meubles->contains($meuble)) {
            $this->meubles->add($meuble);
            $meuble->setVendeur($this);
        }
        return $this;
    }

    public function removeMeuble(Meuble $meuble): self
    {
        if ($this->meubles->removeElement($meuble)) {
            if ($meuble->getVendeur() === $this) {
                $meuble->setVendeur(null);
            }
        }
        return $this;
    }
// Getters et setters pour $paniers
public function getPaniers(): Collection
{
    return $this->paniers;
}

public function addPanier(Panier $panier): self
{
    if (!$this->paniers->contains($panier)) {
        $this->paniers->add($panier);
        $panier->setAcheteur($this);
    }
    return $this;
}

public function removePanier(Panier $panier): self
{
    if ($this->paniers->removeElement($panier)) {
        if ($panier->getAcheteur() === $this) {
            $panier->setAcheteur(null);
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
        $commande->setAcheteur($this);
    }
    return $this;
}

public function removeCommande(Commande $commande): self
{
    if ($this->commandes->removeElement($commande)) {
        if ($commande->getAcheteur() === $this) {
            $commande->setAcheteur(null);
        }
    }
    return $this;
}
    // Méthodes de l'interface UserInterface

    public function getRoles(): array
    {
        if (!$this->role) {
            return ['ROLE_USER'];
        }

        $roleMap = [
            'admin' => 'ROLE_ADMIN',
            'propriétaire' => 'ROLE_PROPRIETAIRE',
            'transporteur' => 'ROLE_TRANSPORTEUR',
            'étudiant' => 'ROLE_ETUDIANT'
        ];

        return [$roleMap[$this->role->value] ?? 'ROLE_USER'];
    }

    public function eraseCredentials(): void
    {
        // Pas de données temporaires à effacer ici
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getPassword(): string
    {
        return $this->mdp;
    }

    public function setPassword(string $mdp): self
    {
        $this->mdp = $mdp;
        return $this;
    }
}