<?php

namespace App\Entity;

use Symfony\Component\Validator\Constraints as Assert;
use App\Enums\GestionReservation\StatusReservation;
use App\Repository\RendezvousRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Utilisateur;
use App\Repository\LogementRepository;







#[ORM\Entity(repositoryClass: RendezvousRepository::class)]
#[ORM\Table(name: 'rendez_vous')]  // Matches your actual table name
class Rendezvous
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    #[Assert\NotBlank(message: "La date est obligatoire")]
    #[Assert\GreaterThanOrEqual(
        "today", 
        message: "La date doit être aujourd'hui ou dans le futur"
    )]
    private ?\DateTimeInterface $date = null;

    #[ORM\Column(length: 50, nullable: true)]
#[Assert\NotBlank(message: "L'heure est obligatoire")]
private ?string $heure = null; // Keep as string

    // #[ORM\Column(name: 'cinEtudiant', length: 20, nullable: true)]
    // #[Assert\NotBlank(message: "Le CIN étudiant est obligatoire")]
    // #[Assert\Length(
    //     min: 8,
    //     max: 20,
    //     minMessage: "Le CIN doit contenir au moins {{ limit }} caractères",
    //     maxMessage: "Le CIN ne peut pas dépasser {{ limit }} caractères"
    // )]
    // #[Assert\Regex(
    //     pattern: "/^[0-9]+$/",
    //     message: "Le CIN doit contenir uniquement des chiffres"
    // )]
    // private ?string $cinEtudiant = null;

    // #[ORM\Column(name: 'cinProprietaire', length: 8, nullable: true)]
    // #[Assert\NotBlank(message: "Le CIN propriétaire est obligatoire")]
    // #[Assert\Length(
    //     exactly: 8,
    //     exactMessage: "Le CIN propriétaire doit contenir exactement 8 caractères"
    // )]
    // #[Assert\Regex(
    //     pattern: "/^[0-9]+$/",
    //     message: "Le CIN doit contenir uniquement des chiffres"
    // )]
    // private ?string $cinProprietaire = null;

  
    #[ORM\Column(
      type: 'string', 
      columnDefinition: "ENUM('confirmée', 'en_attente', 'refusée') DEFAULT 'en_attente'", 
      nullable: false
  )]
  private string $status = 'en_attente';


  #[ORM\ManyToOne(targetEntity: Utilisateur::class, inversedBy: 'rendezvousAsProprietaire')]
    #[ORM\JoinColumn(name: 'cinProprietaire', referencedColumnName: 'cin')]
    private ?Utilisateur $proprietaire = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class, inversedBy: 'rendezvousAsEtudiant')]
    #[ORM\JoinColumn(name: 'cinEtudiant', referencedColumnName: 'cin')]
    private ?Utilisateur $etudiant = null;
  
    #[ORM\Column(name: 'idLogement', length: 255, nullable: true)]
    #[Assert\NotBlank(message: "L'identifiant du logement est obligatoire")]
    private ?string $idLogement = null;


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

    public function setHeure($heure): static
{
    if ($heure instanceof \DateTimeInterface) {
        $this->heure = $heure->format('H:i');
    } else {
        $this->heure = $heure;
    }
    
    return $this;
}

    // public function getcinProprietaire(): ?string
    // {
    //     return $this->cinProprietaire;
    // }

    // public function setCinProprietaire(string $cinProprietaire): static
    // {
    //     $this->cinProprietaire = $cinProprietaire;

    //     return $this;
    // }

    // public function getCinEtudiant(): ?string
    // {
    //     return $this->cinEtudiant;
    // }

    // public function setCinEtudiant(string $cinEtudiant): static
    // {
    //     $this->cinEtudiant = $cinEtudiant;

    //     return $this;
    // }

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
public function getProprietaire(): ?Utilisateur
{
    return $this->proprietaire;
}

public function setProprietaire(?Utilisateur $proprietaire): self
{
    $this->proprietaire = $proprietaire;
    return $this;
}

public function getEtudiant(): ?Utilisateur
{
    return $this->etudiant;
}

public function setEtudiant(?Utilisateur $etudiant): self
{
    $this->etudiant = $etudiant;
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
public function getLogementAddress(LogementRepository $logementRepository): string
{
    if (is_numeric($this->idLogement)) {
        $logement = $logementRepository->find($this->idLogement);
        return $logement ? $logement->getAdresse() : 'Logement #'.$this->idLogement;
    }
    
    return $this->idLogement; // fallback if it's already a string
}

public function getProprietaireName(): string
{
    return $this->proprietaire ? $this->proprietaire->getNom().' '.$this->proprietaire->getPrenom() : 'Non défini';
}

public function getEtudiantName(): string
{
    return $this->etudiant ? $this->etudiant->getNom().' '.$this->etudiant->getPrenom() : 'Non défini';
}
}
