<?php

namespace App\Entity;

use App\Enums\GestionTransport\VoitureDisponibilite;
use App\Repository\VoitureRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Constraints as Assert;
use Vich\UploaderBundle\Mapping\Annotation as Vich;


#[ORM\Entity(repositoryClass: VoitureRepository::class)]
class Voiture
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $idVoiture = null;

    #[ORM\Column(length: 20)]
    private ?string $model = null;

    #[ORM\Column(length: 12)]
    private ?string $numSerie = null;

    #[ORM\Column(length: 200)]  
    private ?string $photo;
    
    #[Vich\UploadableField(mapping: 'voiture_photos', fileNameProperty: 'photo')]
    #[Assert\Image(
        maxSize: '5M',
        maxSizeMessage: 'The image is too large ({{ size }} {{ suffix }}). Maximum allowed is {{ limit }} {{ suffix }}.',
        mimeTypes: ['image/jpeg', 'image/png', 'image/gif'],
        mimeTypesMessage: 'Please upload a valid image (JPEG, PNG, GIF).'
    )]
    private ?File $photoFile = null;

    #[ORM\Column(type:'voiture_disponibilite', length: 50)]
    private ?VoitureDisponibilite $disponibilite = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $timestamp = null;

    public function __construct()
    {
        $this->disponibilite = VoitureDisponibilite::DISPONIBLE; 
        $this->timestamp = new \DateTimeImmutable();
    }

    public function getIdVoiture(): ?int
    {
        return $this->idVoiture;
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
    public function setPhotoFile(?File $photo = null): void
    {
        $this->photoFile = $photo;

        if (null !== $photo) {
            $this->timestamp = new \DateTime();
        }
    }

    public function getPhotoFile(): ?File
    {
        return $this->photo;
    }  
}