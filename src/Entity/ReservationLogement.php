<?php

namespace App\Entity;

use Symfony\Component\Validator\Constraints as Assert;
use App\Repository\ReservationLogementRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Utilisateur;

#[ORM\Entity(repositoryClass: ReservationLogementRepository::class)]
#[ORM\Table(name: 'reservation_logement')]
class ReservationLogement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'dateDebut', type: Types::DATE_MUTABLE, nullable: true)]
    #[Assert\NotBlank(message: "La date de début est obligatoire")]
    #[Assert\GreaterThanOrEqual(
        "today", 
        message: "La date de début doit être aujourd'hui ou dans le futur"
    )]
    private ?\DateTimeInterface $dateDebut = null;

    #[ORM\Column(name: 'dateFin', type: Types::DATE_MUTABLE, nullable: true)]
    #[Assert\NotBlank(message: "La date de fin est obligatoire")]
    #[Assert\GreaterThan(
        propertyPath: "dateDebut",
        message: "La date de fin doit être après la date de début"
    )]
    private ?\DateTimeInterface $dateFin = null;

    #[ORM\Column(
        type: 'string',
        columnDefinition: "ENUM('confirmée', 'en_attente', 'refusée') DEFAULT 'en_attente'",
        nullable: false
    )]
    private string $status = 'en_attente';

    #[ORM\Column(name: 'idLogement', length: 255, nullable: true)]
    #[Assert\NotBlank(message: "L'identifiant du logement est obligatoire")]
    private ?string $idLogement = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class, inversedBy: 'reservationsAsProprietaire')]
    #[ORM\JoinColumn(name: 'cinProprietaire', referencedColumnName: 'cin')]
    private ?Utilisateur $proprietaire = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class, inversedBy: 'reservationsAsEtudiant')]
    #[ORM\JoinColumn(name: 'cinEtudiant', referencedColumnName: 'cin')]
    private ?Utilisateur $etudiant = null;

    public function __construct()
    {
        $this->status = 'en_attente';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDateDebut(): ?\DateTimeInterface
    {
        return $this->dateDebut;
    }

    public function setDateDebut(?\DateTimeInterface $dateDebut): static
    {
        $this->dateDebut = $dateDebut;
        return $this;
    }

    public function getDateFin(): ?\DateTimeInterface
    {
        return $this->dateFin;
    }

    public function setDateFin(?\DateTimeInterface $dateFin): static
    {
        $this->dateFin = $dateFin;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getIdLogement(): ?string
    {
        return $this->idLogement;
    }

    public function setIdLogement(?string $idLogement): static
    {
        $this->idLogement = $idLogement;
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

    public function isModifiable(): bool
    {
        return $this->status === 'en_attente';
    }

    public function isDeletable(): bool
    {
        if ($this->status === 'refusée') {
            return false;
        }

        if (!$this->dateDebut) {
            return true;
        }

        try {
            $now = new \DateTime();
            $diff = $now->diff($this->dateDebut);
            return $diff->days >= 1; // Can delete if more than 1 day before start
        } catch (\Exception $e) {
            return true;
        }
    }
}