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

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $departureLat = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $departureLng = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $destinationLat = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $destinationLng = null;

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

    public function getDepartureLat(): ?float
    {
        return $this->departureLat;
    }

    public function setDepartureLat(?float $departureLat): static
    {
        $this->departureLat = $departureLat;
        return $this;
    }

    public function getDepartureLng(): ?float
    {
        return $this->departureLng;
    }

    public function setDepartureLng(?float $departureLng): static
    {
        $this->departureLng = $departureLng;
        return $this;
    }

    public function getDestinationLat(): ?float
    {
        return $this->destinationLat;
    }

    public function setDestinationLat(?float $destinationLat): static
    {
        $this->destinationLat = $destinationLat;
        return $this;
    }

    public function getDestinationLng(): ?float
    {
        return $this->destinationLng;
    }

    public function setDestinationLng(?float $destinationLng): static
    {
        $this->destinationLng = $destinationLng;
        return $this;
    }


    public function isModifiable(): bool
{
    return $this->status === 'en_attente';
}

public function isDeletable(): bool
{
    // Cannot delete refused reservations
    if ($this->status === 'refusée') {
        return false;
    }
    
    // Add any other business rules here
    return true;
}

// public function getDistance(): ?float
// {
//     if (!$this->hasCoordinates()) {
//         return null;
//     }
    
//     // Formule haversine pour calculer la distance en km
//     $latFrom = deg2rad($this->departureLat);
//     $lonFrom = deg2rad($this->departureLng);
//     $latTo = deg2rad($this->destinationLat);
//     $lonTo = deg2rad($this->destinationLng);

//     $latDelta = $latTo - $latFrom;
//     $lonDelta = $lonTo - $lonFrom;

//     $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
//         cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));
    
//     return $angle * 6371; // Rayon de la Terre en km
// }

// public function getEstimatedTime(): ?string
// {
//     $distance = $this->getDistance();
//     if ($distance === null) {
//         return null;
//     }
    
//     // Estimation: 1 minute par km (modifiable selon votre besoin)
//     $minutes = round($distance);
//     return $minutes . ' min';
// }
}