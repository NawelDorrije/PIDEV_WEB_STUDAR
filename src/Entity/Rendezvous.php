<?php

namespace App\Entity;

use Symfony\Component\Validator\Constraints as Assert;
use App\Enums\GestionReservation\StatusReservation;
use App\Repository\RendezvousRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RendezvousRepository::class)]
#[ORM\Table(name: 'rendez_vous')]  // Matches your actual table name
class Rendezvous
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $date = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $heure = null;

    #[ORM\Column(name: 'cinEtudiant', length: 20, nullable: true)]
    private ?string $cinEtudiant = null;

    #[ORM\Column(name: 'cinProprietaire', length: 8, nullable: true)]
    private ?string $cinProprietaire = null;

    #[ORM\Column(name: 'idLogement', length: 255, nullable: true)]
    private ?string $idLogement = null;
    #[ORM\Column(
      type: 'string', 
      columnDefinition: "ENUM('confirmée', 'en_attente', 'refusée') DEFAULT 'en_attente'", 
      nullable: false
  )]
  private string $status = 'en_attente';

  public function __construct()
  {
      $this->status = 'en_attente';
  }

    

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(?\DateTimeInterface $date): static
    {
        $this->date = $date;

        return $this;
    }

    public function getHeure(): ?string
    {
        return $this->heure;
    }

    public function setHeure(?string $heure): static
    {
        $this->heure = $heure;

        return $this;
    }

    public function getcinProprietaire(): ?string
    {
        return $this->cinProprietaire;
    }

    public function setCinProprietaire(string $cinProprietaire): static
    {
        $this->cinProprietaire = $cinProprietaire;

        return $this;
    }

    public function getCinEtudiant(): ?string
    {
        return $this->cinEtudiant;
    }

    public function setCinEtudiant(string $cinEtudiant): static
    {
        $this->cinEtudiant = $cinEtudiant;

        return $this;
    }

    public function getIdLogement(): ?string
    {
        return $this->idLogement;
    }

    public function setIdLogement(string $idLogement): static
    {
        $this->idLogement = $idLogement;

        return $this;
    }

    /**
     * @return StatusReservation[]
     */
    public function getStatus(): ?string
{
    return $this->status;
}

public function setStatus(?string $status): static
{
    $this->status = $status;
    return $this;
}

// In App\Entity\Rendezvous.php

public function isModifiable(): bool
{
    return $this->status === 'en_attente';
}

public function isDeletable(): bool
{
    // Cannot delete refused appointments
    if ($this->status === 'refusée') {
        return false;
    }

    // If date or time is missing, allow deletion (or return false if you prefer)
    if (!$this->date || !$this->heure) {
        return true;
    }

    try {
        $now = new \DateTime();
        $rendezvousTime = \DateTime::createFromFormat(
            'Y-m-d H:i', 
            $this->date->format('Y-m-d') . ' ' . $this->heure
        );

        // Check if createFromFormat failed
        if ($rendezvousTime === false) {
            return true;
        }

        $diff = $rendezvousTime->diff($now);
        return ($diff->days * 24 + $diff->h) >= 12;
    } catch (\Exception $e) {
        return true; // Fallback if datetime calculation fails
    }
}
}
