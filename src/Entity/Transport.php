<?php

namespace App\Entity;

use App\Repository\TransportRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TransportRepository::class)]
class Transport
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'transports')]
    #[ORM\JoinColumn(name: 'id_voiture', referencedColumnName: 'id_voiture', nullable: false)]
    private ?Voiture $idVoiture = null;

    #[ORM\ManyToOne(inversedBy: 'transports')]
    #[ORM\JoinColumn(name: 'cin', referencedColumnName: 'cin', nullable: false)]
    private ?Utilisateur $cin = null;

    #[ORM\ManyToOne(inversedBy: 'transports')]
    #[ORM\JoinColumn(name: 'reservation_id', referencedColumnName: 'id', nullable: false)]
    private ?ReservationTransport $reservationId = null;

    #[ORM\Column]
    private ?float $trajetEnKm = null;

    #[ORM\Column]
    private ?float $tarif = null;

    #[ORM\Column(length: 255)]
    private ?string $status = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $timestamp = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIdVoiture(): ?Voiture
    {
        return $this->idVoiture;
    }

    public function setIdVoiture(?Voiture $idVoiture): static
    {
        $this->idVoiture = $idVoiture;
        return $this;
    }

    public function getCin(): ?Utilisateur
    {
        return $this->cin;
    }

    public function setCin(?Utilisateur $cin): static
    {
        $this->cin = $cin;
        return $this;
    }

    public function getReservationId(): ?ReservationTransport
    {
        return $this->reservationId;
    }

    public function setReservationId(?ReservationTransport $reservationId): static
    {
        $this->reservationId = $reservationId;
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

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
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
}