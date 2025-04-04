<?php

namespace App\Entity;

use App\Repository\ReservationLogementRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReservationLogementRepository::class)]
#[ORM\Table(name: 'reservation_logement')]
class ReservationLogement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'dateDebut', type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateDebut = null;

    #[ORM\Column(name: 'dateFin', type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateFin = null;

    #[ORM\Column(
        type: 'string',
        columnDefinition: "ENUM('confirmée', 'en_attente', 'refusée')",
        nullable: true
    )]
    private ?string $status = null;

    #[ORM\Column(name: 'cinEtudiant', length: 20, nullable: true)]
private ?string $cinEtudiant = null;

#[ORM\Column(name: 'cinProprietaire', length: 8, nullable: true)]
private ?string $cinProprietaire = null;

#[ORM\Column(name: 'idLogement', length: 255, nullable: true)]
private ?string $idLogement = null;

    // Getters and Setters
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

    public function getCinEtudiant(): ?string
    {
        return $this->cinEtudiant;
    }

    public function setCinEtudiant(?string $cinEtudiant): static
    {
        $this->cinEtudiant = $cinEtudiant;
        return $this;
    }

    public function getCinProprietaire(): ?string
    {
        return $this->cinProprietaire;
    }

    public function setCinProprietaire(?string $cinProprietaire): static
    {
        $this->cinProprietaire = $cinProprietaire;
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
}