<?php

namespace App\Entity;

use App\Repository\VoitureRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: VoitureRepository::class)]
class Voiture
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $idVoiture = null;

    #[ORM\ManyToOne(inversedBy: 'voitures')]
    #[ORM\JoinColumn(name: 'cin', referencedColumnName: 'cin', nullable: false)]
    private ?Utilisateur $cin = null;

    #[ORM\Column(length: 20)]
    private ?string $model = null;

    #[ORM\Column(length: 12)]
    private ?string $numSerie = null;

    #[ORM\Column(length: 200)]
    private ?string $photo = null;

    #[ORM\Column(length: 255)]
    private ?string $disponibilite = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $timestamp = null;

    #[ORM\OneToMany(mappedBy: 'idVoiture', targetEntity: Transport::class, cascade: ['persist', 'remove'])]
    private Collection $transports;

    public function __construct()
    {
        $this->transports = new ArrayCollection();
    }

    public function getIdVoiture(): ?int
    {
        return $this->idVoiture;
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

    public function getModel(): ?string
    {
        return $this->model;
    }

    public function setModel(string $model): static
    {
        $this->model = $model;
        return $this;
    }

    public function getNumSerie(): ?string
    {
        return $this->numSerie;
    }

    public function setNumSerie(string $numSerie): static
    {
        $this->numSerie = $numSerie;
        return $this;
    }

    public function getPhoto(): ?string
    {
        return $this->photo;
    }

    public function setPhoto(string $photo): static
    {
        $this->photo = $photo;
        return $this;
    }

    public function getDisponibilite(): ?string
    {
        return $this->disponibilite;
    }

    public function setDisponibilite(string $disponibilite): static
    {
        $this->disponibilite = $disponibilite;
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

    /**
     * @return Collection<int, Transport>
     */
    public function getTransports(): Collection
    {
        return $this->transports;
    }

    public function addTransport(Transport $transport): static
    {
        if (!$this->transports->contains($transport)) {
            $this->transports->add($transport);
            $transport->setIdVoiture($this);
        }
        return $this;
    }

    public function removeTransport(Transport $transport): static
    {
        if ($this->transports->removeElement($transport)) {
            if ($transport->getIdVoiture() === $this) {
                $transport->setIdVoiture(null);
            }
        }
        return $this;
    }
}