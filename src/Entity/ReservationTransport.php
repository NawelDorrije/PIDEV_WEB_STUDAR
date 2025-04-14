<?php

namespace App\Entity;

use Symfony\Component\Validator\Constraints as Assert;
use App\Repository\ReservationTransportRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Utilisateur;

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
        type: 'string',
        columnDefinition: "ENUM('confirmée', 'en_attente', 'refusée') DEFAULT 'en_attente'",
        nullable: false
    )]
    private string $status = 'en_attente';

    #[ORM\ManyToOne(targetEntity: Utilisateur::class, inversedBy: 'reservationsTransportAsEtudiant')]
    #[ORM\JoinColumn(name: 'cinEtudiant', referencedColumnName: 'cin')]
    private ?Utilisateur $etudiant = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class, inversedBy: 'reservationsAsTransporteur')]
    #[ORM\JoinColumn(name: 'cinTransporteur', referencedColumnName: 'cin')]
    private ?Utilisateur $transporteur = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $departureLat = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $departureLng = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $destinationLat = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $destinationLng = null;

    public function __construct()
    {
        $this->status = 'en_attente';
    }

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

    public function getEtudiant(): ?Utilisateur
    {
        return $this->etudiant;
    }

    public function setEtudiant(?Utilisateur $etudiant): self
    {
        $this->etudiant = $etudiant;
        return $this;
    }

    public function getTransporteur(): ?Utilisateur
    {
        return $this->transporteur;
    }

    public function setTransporteur(?Utilisateur $transporteur): self
    {
        $this->transporteur = $transporteur;
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
        return $this->status !== 'refusée';
    }

    public function getDistance(): ?float
    {
        if (!$this->hasCoordinates()) {
            return null;
        }
        
        $latFrom = deg2rad($this->departureLat);
        $lonFrom = deg2rad($this->departureLng);
        $latTo = deg2rad($this->destinationLat);
        $lonTo = deg2rad($this->destinationLng);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
            cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));
        
        return $angle * 6371;
    }

    public function getEstimatedTime(): ?string
    {
        $distance = $this->getDistance();
        if ($distance === null) {
            return null;
        }
        
        $minutes = round($distance);
        return $minutes . ' min';
    }

    private function hasCoordinates(): bool
    {
        return $this->departureLat !== null &&
               $this->departureLng !== null &&
               $this->destinationLat !== null &&
               $this->destinationLng !== null;
    }
}