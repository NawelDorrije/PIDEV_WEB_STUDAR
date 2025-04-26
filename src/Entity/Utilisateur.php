<?php

namespace App\Entity;

use App\Enums\RoleUtilisateur;
use App\Repository\UtilisateurRepository;
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
    #[Assert\Length(
        exactly: 8,
        exactMessage: 'Le CIN doit contenir exactement 8 chiffres.'
    )]
    #[Assert\Regex(
        pattern: '/^\d+$/',
        message: 'Le CIN ne doit contenir que des chiffres.'
    )]
    private ?string $cin = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: 'Le nom ne peut pas être vide.')]
    private ?string $nom = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: 'Le prénom ne peut pas être vide.')]
    private ?string $prenom = null;

    #[ORM\Column(length: 100, unique: true)]
    #[Assert\NotBlank(message: 'L\'email ne peut pas être vide.')]
    #[Assert\Email(message: 'L\'email n\'est pas valide.')]
    private ?string $email = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le mot de passe ne peut pas être vide.')]
    private ?string $mdp = null;

    #[ORM\Column(name: 'numTel', length: 15, nullable: true)]
    #[Assert\Regex(
        pattern: '/^\+?\d{10,15}$/',
        message: 'Le numéro de téléphone n\'est pas valide.',
        match: true
    )]
    private ?string $numTel = null;

    #[ORM\Column(type: 'string', length: 20)]
    private string $theme = 'light';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

    #[ORM\Column(type: 'role_enum')]
    private ?RoleUtilisateur $role = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $reset_code = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $reset_code_expires_at = null;

    #[ORM\Column]
    private ?bool $blocked = false;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $created_at = null;



    public function __construct()
    {
        $this->userHandle = bin2hex(random_bytes(32));
        $this->blocked = false;
        $this->created_at = new \DateTimeImmutable();
    }

    public function getCin(): ?string
    {
        return $this->cin;
    }

    public function setCin(string $cin): static
    {
        $this->cin = $cin;
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

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(string $prenom): static
    {
        $this->prenom = $prenom;
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

    public function getTheme(): string
    {
        return $this->theme;
    }

    public function setTheme(string $theme): static
    {
        $this->theme = $theme;
        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): static
    {
        $this->image = $image;
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

    public function setResetCode(?string $reset_code): static
    {
        $this->reset_code = $reset_code;
        return $this;
    }

    public function getResetCodeExpiresAt(): ?\DateTimeImmutable
    {
        return $this->reset_code_expires_at;
    }

    public function setResetCodeExpiresAt(?\DateTimeImmutable $reset_code_expires_at): static
    {
        $this->reset_code_expires_at = $reset_code_expires_at;
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

    public function getRoles(): array
    {
        if (!$this->role) {
            return ['ROLE_ETUDIANT'];
        }

        return match ($this->role) {
            RoleUtilisateur::ADMIN => ['ROLE_ADMIN'],
            RoleUtilisateur::PROPRIETAIRE => ['ROLE_PROPRIETAIRE'],
            RoleUtilisateur::TRANSPORTEUR => ['ROLE_TRANSPORTEUR'],
            RoleUtilisateur::ETUDIANT => ['ROLE_ETUDIANT'],
            default => ['ROLE_ETUDIANT']
        };
    }

    public function eraseCredentials(): void
    {
        // Clear any temporary sensitive data if needed
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

    public function getUserHandle(): string
    {
        return $this->userHandle;
    }

    public function getUserName(): string
    {
        return $this->email;
    }

    public function getDisplayName(): string
    {
        return $this->prenom . ' ' . $this->nom;
    }

    public function getAvatarUrl(int $size = 100): string
    {
        if ($this->image) {
            return '/Uploads/images/' . $this->image;
        }

        $initials = $this->getInitials();
        $bgColor = $this->generateBackgroundColor();

        return sprintf(
            'https://ui-avatars.com/api/?name=%s&background=%s&color=fff&size=%d',
            urlencode($initials),
            substr($bgColor, 1),
            $size
        );
    }

    private function getInitials(): string
    {
        $firstNameInitial = $this->prenom ? mb_substr($this->prenom, 0, 1) : '';
        $lastNameInitial = $this->nom ? mb_substr($this->nom, 0, 1) : '';

        return mb_strtoupper($firstNameInitial . $lastNameInitial);
    }

    private function generateBackgroundColor(): string
    {
        $hash = md5($this->cin ?? $this->email);
        return sprintf('#%s%s%s',
            substr($hash, 0, 2),
            substr($hash, 4, 2),
            substr($hash, 8, 2)
        );
    }
}