<?php

namespace App\Entity;

use App\Enums\RoleUtilisateur;
use App\Repository\UtilisateurRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;


/*#[UniqueEntity(fields: ['email'], message: 'Cet email est déjà utilisé.')]
#[UniqueEntity(fields: ['cin'], message: 'Ce CIN est déjà enregistré.')]*/
#[ORM\Entity(repositoryClass: UtilisateurRepository::class)]
class Utilisateur implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\Column(length: 8, unique: true)]
    #[Assert\Length(
        exactly: 8,
        exactMessage: 'Le CIN doit contenir exactement 8 chiffres.',
    )]
    #[Assert\Regex(
        pattern: '/^\d+$/',
        message: 'Le CIN ne doit contenir que des chiffres.'
    )]
    private ?string $cin = null;

    #[ORM\Column(length: 50)]
    private ?string $nom = null;

    #[ORM\Column(length: 255)]
    private ?string $image = null;

    #[ORM\Column(length: 50)]
    private ?string $prenom = null;

    #[ORM\Column(length: 100)]
    private ?string $email = null;

    #[ORM\Column(length: 255)]
    private ?string $mdp = null;

    #[ORM\Column(name: 'numTel',length: 15, nullable: true)]

    private ?string $numTel = null;

    #[ORM\Column(type: 'role_enum')]
    private ?RoleUtilisateur $role = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $reset_code = null;

    #[ORM\Column]
    private ?bool $blocked = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $created_at = null;

    /**
     * @var Collection<int, Reclamation>
     */
    #[ORM\OneToMany(targetEntity: Reclamation::class, mappedBy: 'cin')]
    private Collection $reclamations;

   
    
    public function getCin(): ?string
    {
        return $this->cin;
    }
    
    public function setCin(string $cin): static
    {
        $this->cin = $cin;

        return $this;
    }
    public function getImage(): ?string
    {
        return $this->image;
    }
    public function setImage(string $image): static
    {
        $this->image = $image;

        return $this;
    }
    // public function getUserIdentifier(): string
    // {
    //     return $this->email;
    // }
    
    // public function getRoles(): array
    // {
    //     return [$this->role->value];
    // }
    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(string $prenom): static
    {
        $this->prenom = $prenom;

        return $this;
    }
    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getMdp(): ?string
    {
        return $this->mdp;
    }

    public function setMdp(string $mdp): static
    {
        $this->mdp = $mdp;

        return $this;
    }

    public function getNumTel(): ?string
    {
        return $this->numTel;
    }

    public function setNumTel(?string $numTel): static
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

public function setRole(RoleUtilisateur $role): static
{
    $this->role = $role;
    return $this;
}

    public function getResetCode(): ?string
    {
        return $this->reset_code;
    }
    public function getRoles(): array
    {
        if (!$this->role) {
            return ['ROLE_ETUDIANT']; // Default role
        }
        
        // Map enum values to Symfony roles
        return match($this->role) {
            RoleUtilisateur::ADMIN => ['ROLE_ADMIN'],
            RoleUtilisateur::PROPRIETAIRE => ['ROLE_PROPRIETAIRE'],
            RoleUtilisateur::TRANSPORTEUR => ['ROLE_TRANSPORTEUR'],
            RoleUtilisateur::ETUDIANT => ['ROLE_ETUDIANT'],
            default => ['ROLE_ETUDIANT']
        };
    }
    // public function setRoles(RoleUtilisateur $role): static
    // {
    //     $this->role = $role;

    //     return $this;
    // }
    public function setResetCode(?string $reset_code): static
    {
        $this->reset_code = $reset_code;

        return $this;
    }
    

    public function isBlocked(): ?bool
    {
        return $this->blocked;
    }

    public function setBlocked(bool $blocked): static
    {
        $this->blocked = $blocked;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->created_at;
    }

    public function setCreatedAt(?\DateTimeImmutable $created_at): static
    {
        $this->created_at = $created_at;

        return $this;
    }
   

    public function eraseCredentials()
    {
        // If you store any temporary, sensitive data on the user, clear it here
    }

    public function getUserIdentifier(): string
    {
        // Return the unique identifier for the user (e.g., email)
        return $this->email;
    }
    public function getPassword(): string
{
    return $this->mdp; // assuming your password field is called 'mdp'
}
    public function setPassword(string $mdp) : self
    {
        $this->mdp = $mdp;

        return $this;
    }
    public function __construct()
{
    $this->role = RoleUtilisateur::ADMIN; // Using the most basic role as default
    $this->reclamations = new ArrayCollection();
}

    /**
     * @return Collection<int, Reclamation>
     */
    public function getReclamations(): Collection
    {
        return $this->reclamations;
    }

    public function addReclamation(Reclamation $reclamation): static
    {
        if (!$this->reclamations->contains($reclamation)) {
            $this->reclamations->add($reclamation);
            $reclamation->setCin($this);
        }

        return $this;
    }

    public function removeReclamation(Reclamation $reclamation): static
    {
        if ($this->reclamations->removeElement($reclamation)) {
            // set the owning side to null (unless already changed)
            if ($reclamation->getCin() === $this) {
                $reclamation->setCin(null);
            }
        }

        return $this;
    }
}
