<?php

namespace App\Entity\GestionTransport;

use App\Repository\GestionTransport\TransportRepository;
use App\Entity\GestionTransport\Voiture;
use App\Enums\GestionTransport\TransportStatus;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\ReservationTransport;

#[ORM\Entity(repositoryClass: TransportRepository::class)]
#[ORM\Table(name: 'transport')]
class Transport
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(name: 'id', type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Voiture::class, inversedBy: 'transports')]
    #[ORM\JoinColumn(name: 'id_voiture', referencedColumnName: 'id_voiture', nullable: false)]
    private ?Voiture $voiture = null;

    #[ORM\OneToOne(targetEntity: ReservationTransport::class)]
    #[ORM\JoinColumn(name: 'reservation_id', referencedColumnName: 'id', nullable: false)]
    private ?ReservationTransport $reservation = null;

    #[ORM\Column]
    private ?float $trajetEnKm = null;

    #[ORM\Column]
    private ?float $tarif = null;

    #[ORM\Column(type: 'transport_status')]
    private ?TransportStatus $status = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $timestamp = null;

    public function getId(): ?int
    {
        return $this->id;
    }
    public function getIdVoiture(): ?int
{
    return $this->voiture?->getIdVoiture();
}
    public function getVoiture(): ?Voiture
    {
        return $this->voiture;
    }

    public function setVoiture(?Voiture $voiture): static
    {
        $this->voiture = $voiture;
        return $this;
    }

    public function getTrajetEnKm(): ?float
    {
        return $this->trajetEnKm;
    }

    public function setTrajetEnKm(float $trajetEnKm): static
    {
        $this->trajetEnKm = $trajetEnKm;
        return $this;
    }

    public function getTarif(): ?float
    {
        return $this->tarif;
    }

    public function setTarif(float $tarif): static
    {
        $this->tarif = $tarif;
        return $this;
    }

    public function getStatus(): ?TransportStatus
    {
        return $this->status;
    }

    public function setStatus(TransportStatus $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getTimestamp(): ?\DateTimeInterface
    {
        return $this->timestamp;
    }

    public function setTimestamp(\DateTimeInterface $timestamp): static
    {
        $this->timestamp = $timestamp;
        return $this;
    }

    public function __construct() {
        $this->timestamp = new \DateTime(); 
    }
    


    public function getReservation(): ?ReservationTransport
    {
        return $this->reservation;
    }

    public function setReservation(?ReservationTransport $reservation): static
    {
        $this->reservation = $reservation;
        return $this;
    }

    // The address fields will be accessed through the reservation
    public function getAdresseDepart(): ?string
    {
        return $this->reservation?->getAdresseDepart();
    }

    public function getAdresseDestination(): ?string
    {
        return $this->reservation?->getAdresseDestination();
    }
}