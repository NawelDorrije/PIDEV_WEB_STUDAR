<?php

namespace App\Entity;

use App\Repository\ImageLogementRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ImageLogementRepository::class)]
#[ORM\Table(name: "image_logement")]
class ImageLogement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: "id_image", type: "integer")]
    private int $id;

    #[ORM\Column(length: 255)]
    private ?string $url = null;

   // ImageLogement.php
#[ORM\ManyToOne(inversedBy: 'imageLogements')]
#[ORM\JoinColumn(name: "logement_id", referencedColumnName: "id", nullable: false)] // <-- Fix here
private Logement $logement;
    public function getId(): int
    {
        return $this->id;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(string $url): static
    {
        $this->url = $url;
        return $this;
    }

    public function getLogement(): Logement
    {
        return $this->logement;
    }

    public function setLogement(Logement $logement): static // Parameter name updated for clarity
    {
        $this->logement = $logement;
        return $this;
    }
}