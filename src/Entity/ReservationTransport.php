<?php

namespace App\Entity;

use App\Enums\GestionReservation\StatusReservation;
use App\Repository\ReservationTransportRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReservationTransportRepository::class)]
#[ORM\Table(name: 'reservation_transport')]
class ReservationTransport
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'adresseDepart', length: 255, nullable: true)]
    private ?string $adresseDepart = null;

    #[ORM\Column(name: 'adresseDestination', length: 255, nullable: true)]
    private ?string $adresseDestination = null;

    #[ORM\Column(name: 'tempsArrivage', length: 50, nullable: true)]
    private ?string $tempsArrivage = null;

    #[ORM\Column(
        name: 'status',
        type: 'string',
        columnDefinition: "ENUM('confirmée', 'en_attente', 'refusée')",
        nullable: true,
        options: ['default' => 'en_attente']
    )]
    private ?string $status = 'en_attente';

    #[ORM\Column(name: 'cinEtudiant', length: 8, nullable: true)]
    private ?string $cinEtudiant = null;

    #[ORM\Column(name: 'cinTransporteur', length: 8, nullable: true)]
    private ?string $cinTransporteur = null;

    // Getters and Setters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAdresseDepart(): ?string
    {
        return $this->adresseDepart;
    }

    public function setAdresseDepart(?string $adresseDepart): static
    {
        $this->adresseDepart = $adresseDepart;
        return $this;
    }

    public function getAdresseDestination(): ?string
    {
        return $this->adresseDestination;
    }

    public function setAdresseDestination(?string $adresseDestination): static
    {
        $this->adresseDestination = $adresseDestination;
        return $this;
    }

    public function getTempsArrivage(): ?string
    {
        return $this->tempsArrivage;
    }

    public function setTempsArrivage(?string $tempsArrivage): static
    {
        $this->tempsArrivage = $tempsArrivage;
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

    public function getCinTransporteur(): ?string
    {
        return $this->cinTransporteur;
    }

    public function setCinTransporteur(?string $cinTransporteur): static
    {
        $this->cinTransporteur = $cinTransporteur;
        return $this;
    }
}