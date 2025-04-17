<?php

namespace App\Entity\GestionTransport;

use App\Enums\GestionTransport\VoitureDisponibilite;
use App\Repository\GestionTransport\VoitureRepository;
use App\Entity\Utilisateur;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Constraints as Assert;
use Vich\UploaderBundle\Mapping\Annotation as Vich;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity(repositoryClass: VoitureRepository::class)]
#[Vich\Uploadable]
class Voiture
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $idVoiture = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(name: 'cin', referencedColumnName: 'cin', nullable: false)]
    private ?Utilisateur $utilisateur = null;

    #[ORM\Column(length: 20)]
    private ?string $model = null;

    #[ORM\Column(length: 12)]
    private ?string $numSerie = null;

    #[ORM\Column(name: 'image', length: 200, nullable: true)]
    private ?string $image = null;
    
    #[Vich\UploadableField(mapping: 'voiture_photos', fileNameProperty: 'image')]
    #[Assert\Image(
        maxSize: '5M',
        maxSizeMessage: 'The image is too large ({{ size }} {{ suffix }}). Maximum allowed is {{ limit }} {{ suffix }}.',
        mimeTypes: ['image/jpeg', 'image/png', 'image/gif'],
        mimeTypesMessage: 'Please upload a valid image (JPEG, PNG, GIF).'
    )]
    private ?File $imageFile = null;

    #[ORM\Column(type: 'voiture_disponibilite', length: 50)]
    private ?VoitureDisponibilite $disponibilite = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $timestamp = null;

    #[ORM\OneToMany(mappedBy: 'voiture', targetEntity: Transport::class, orphanRemoval: true)]
    private Collection $transports;

    public function __construct()
    { 
        $this->transports = new ArrayCollection();
        $this->disponibilite = VoitureDisponibilite::DISPONIBLE; 
        $this->timestamp = new \DateTimeImmutable();
        $this->image = 'default-car.jpg';
    }

    // Update getter/setter methods
    public function setImageFile(?File $imageFile = null): void
    {
        $this->imageFile = $imageFile;

        if (null !== $imageFile) {
            $this->timestamp = new \DateTime();
        }
    }

    public function getImageFile(): ?File
    {
        return $this->imageFile;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): void
    {
        $this->image = $image;
    }

    // Standard getters and setters
    public function getIdVoiture(): ?int
    {
        return $this->idVoiture;
    }

    public function setIdVoiture(int $idVoiture): static
    {
        $this->idVoiture = $idVoiture;
        return $this;
    }

    public function getUtilisateur(): ?Utilisateur
    {
        return $this->utilisateur;
    }

    public function setUtilisateur(?Utilisateur $utilisateur): static
    {
        $this->utilisateur = $utilisateur;
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

    public function getDisponibilite(): ?VoitureDisponibilite
    {
        return $this->disponibilite;
    }

    public function setDisponibilite(?VoitureDisponibilite $disponibilite): static
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
            $transport->setVoiture($this);
        }
        return $this;
    }

    public function removeTransport(Transport $transport): static
    {
        if ($this->transports->removeElement($transport)) {
            if ($transport->getVoiture() === $this) {
                $transport->setVoiture(null);
            }
        }
        return $this;
    }
}