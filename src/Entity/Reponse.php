<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReponseRepository::class)]
#[ORM\Table(name: "reponse")]
class Reponse
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_reponse', type: 'integer')]
    private ?int $idReponse = null;
    
    #[ORM\ManyToOne(targetEntity: Reclamation::class)]
    #[ORM\JoinColumn(name: 'id_reclamation', referencedColumnName: 'idReclamation', nullable: false)]
    private ?Reclamation $reclamation = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(name: 'cin_admin', referencedColumnName: 'cin', nullable: false)]
    private ?Utilisateur $admin = null;

    #[ORM\Column(name: 'contenue_reponse', type: 'text')]
    private ?string $contenueReponse = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $timestamp = null;
    // Getters and setters
    public function getIdReponse(): ?int
    {
        return $this->idReponse;
    }

    public function getReclamation(): ?Reclamation
    {
        return $this->reclamation;
    }

    public function setReclamation(?Reclamation $reclamation): static
    {
        $this->reclamation = $reclamation;
        return $this;
    }

    public function getAdmin(): ?Utilisateur
    {
        return $this->admin;
    }

    public function setAdmin(?Utilisateur $admin): static
    {
        $this->admin = $admin;
        return $this;
    }

    public function getContenueReponse(): ?string
    {
        return $this->contenueReponse;
    }

    public function setContenueReponse(string $contenueReponse): static
    {
        $this->contenueReponse = $contenueReponse;
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