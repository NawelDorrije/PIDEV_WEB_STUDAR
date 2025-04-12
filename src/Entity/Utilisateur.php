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
use App\Entity\Rendezvous;
use App\Entity\ReservationTransport;
use App\Entity\ReservationLogement;









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

    #[ORM\OneToMany(targetEntity: Rendezvous::class, mappedBy: 'proprietaire')]
    private Collection $rendezvousAsProprietaire;

    #[ORM\OneToMany(targetEntity: Rendezvous::class, mappedBy: 'etudiant')]
    private Collection $rendezvousAsEtudiant;

    #[ORM\OneToMany(targetEntity: ReservationLogement::class, mappedBy: 'proprietaire')]
    private Collection $reservationsAsProprietaire;

    #[ORM\OneToMany(targetEntity: ReservationLogement::class, mappedBy: 'etudiant')]
    private Collection $reservationsAsEtudiant;
    #[ORM\OneToMany(targetEntity: ReservationTransport::class, mappedBy: 'transporteur')]
    private Collection $reservationsAsTransporteur;

    #[ORM\OneToMany(targetEntity: ReservationTransport::class, mappedBy: 'etudiant')]
    private Collection $reservationsTransportAsEtudiant;
    /**
     * @var Collection<int, Logement>
     */
    #[ORM\OneToMany(targetEntity: Logement::class, mappedBy: 'utilisateur_cin')]
    private Collection $logements;

   
    


    public function getCin(): ?string
    {
        return $this->cin;
    }
    
    public function setCin(string $cin): static
    {
        $this->cin = $cin;

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
            return ['ROLE_USER']; // Default fallback role
        }
        
        // Map your values to Symfony-compatible roles
        $roleMap = [
            'admin' => 'ROLE_ADMIN',
            'propriétaire' => 'ROLE_PROPRIETAIRE',
            'transporteur' => 'ROLE_TRANSPORTEUR',
            'étudiant' => 'ROLE_ETUDIANT'
        ];
        
        return [$roleMap[$this->role->value] ?? 'ROLE_USER'];
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
    $this->rendezvousAsProprietaire = new ArrayCollection();
    $this->rendezvousAsEtudiant = new ArrayCollection();
    $this->reservationsAsProprietaire = new ArrayCollection();
    $this->reservationsAsEtudiant = new ArrayCollection();
    $this->reservationsAsTransporteur = new ArrayCollection();
    $this->reservationsTransportAsEtudiant = new ArrayCollection();
    $this->logements = new ArrayCollection();


}

public function getRendezvousAsProprietaire(): Collection
{
    return $this->rendezvousAsProprietaire;
}

public function addRendezvousAsProprietaire(Rendezvous $rendezvous): self
{
    if (!$this->rendezvousAsProprietaire->contains($rendezvous)) {
        $this->rendezvousAsProprietaire->add($rendezvous);
        $rendezvous->setProprietaire($this);
    }
    return $this;
}

public function removeRendezvousAsProprietaire(Rendezvous $rendezvous): self
{
    if ($this->rendezvousAsProprietaire->removeElement($rendezvous)) {
        // set the owning side to null (unless already changed)
        if ($rendezvous->getProprietaire() === $this) {
            $rendezvous->setProprietaire(null);
        }
    }
    return $this;
}

/**
 * @return Collection<int, Rendezvous>
 */
public function getRendezvousAsEtudiant(): Collection
{
    return $this->rendezvousAsEtudiant;
}

public function addRendezvousAsEtudiant(Rendezvous $rendezvous): self
{
    if (!$this->rendezvousAsEtudiant->contains($rendezvous)) {
        $this->rendezvousAsEtudiant->add($rendezvous);
        $rendezvous->setEtudiant($this);
    }
    return $this;
}

public function removeRendezvousAsEtudiant(Rendezvous $rendezvous): self
{
    if ($this->rendezvousAsEtudiant->removeElement($rendezvous)) {
        // set the owning side to null (unless already changed)
        if ($rendezvous->getEtudiant() === $this) {
            $rendezvous->setEtudiant(null);
        }
    }
    return $this;
}

public function getReservationsAsProprietaire(): Collection
{
    return $this->reservationsAsProprietaire;
}

public function addReservationsAsProprietaire(ReservationLogement $reservation): self
{
    if (!$this->reservationsAsProprietaire->contains($reservation)) {
        $this->reservationsAsProprietaire->add($reservation);
        $reservation->setProprietaire($this);
    }
    return $this;
}

public function removeReservationsAsProprietaire(ReservationLogement $reservation): self
{
    if ($this->reservationsAsProprietaire->removeElement($reservation)) {
        if ($reservation->getProprietaire() === $this) {
            $reservation->setProprietaire(null);
        }
    }
    return $this;
}

public function getReservationsAsEtudiant(): Collection
{
    return $this->reservationsAsEtudiant;
}

public function addReservationsAsEtudiant(ReservationLogement $reservation): self
{
    if (!$this->reservationsAsEtudiant->contains($reservation)) {
        $this->reservationsAsEtudiant->add($reservation);
        $reservation->setEtudiant($this);
    }
    return $this;
}

public function removeReservationsAsEtudiant(ReservationLogement $reservation): self
{
    if ($this->reservationsAsEtudiant->removeElement($reservation)) {
        if ($reservation->getEtudiant() === $this) {
            $reservation->setEtudiant(null);
        }
    }
    return $this;
}
public function getReservationsAsTransporteur(): Collection
    {
        return $this->reservationsAsTransporteur;
    }

    public function addReservationsAsTransporteur(ReservationTransport $reservation): self
    {
        if (!$this->reservationsAsTransporteur->contains($reservation)) {
            $this->reservationsAsTransporteur->add($reservation);
            $reservation->setTransporteur($this);
        }
        return $this;
    }

    public function removeReservationsAsTransporteur(ReservationTransport $reservation): self
    {
        if ($this->reservationsAsTransporteur->removeElement($reservation)) {
            if ($reservation->getTransporteur() === $this) {
                $reservation->setTransporteur(null);
            }
        }
        return $this;
    }

    public function getReservationsTransportAsEtudiant(): Collection
    {
        return $this->reservationsTransportAsEtudiant;
    }

    public function addReservationsTransportAsEtudiant(ReservationTransport $reservation): self
    {
        if (!$this->reservationsTransportAsEtudiant->contains($reservation)) {
            $this->reservationsTransportAsEtudiant->add($reservation);
            $reservation->setEtudiant($this);
        }
        return $this;
    }

    public function removeReservationsTransportAsEtudiant(ReservationTransport $reservation): self
    {
        if ($this->reservationsTransportAsEtudiant->removeElement($reservation)) {
            if ($reservation->getEtudiant() === $this) {
                $reservation->setEtudiant(null);
            }
        }
        return $this;
    }
      /**
     * @return Collection<int, Logement>
     */
    public function getLogements(): Collection
    {
        return $this->logements;
    }

    public function addLogement(Logement $logement): static
    {
        if (!$this->logements->contains($logement)) {
            $this->logements->add($logement);
            $logement->setUtilisateurCin($this);
        }

        return $this;
    }

    public function removeLogement(Logement $logement): static
    {
        if ($this->logements->removeElement($logement)) {
            // set the owning side to null (unless already changed)
            if ($logement->getUtilisateurCin() === $this) {
                $logement->setUtilisateurCin(null);
            }
        }

        return $this;
    }


}

  
