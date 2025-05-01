<?php

namespace App\Entity\GestionMeubles;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'lignes_panier')]
class LignePanier
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Panier::class, inversedBy: 'lignesPanier')]
    #[ORM\JoinColumn(name: 'id_panier', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Panier $panier = null;

    #[ORM\ManyToOne(targetEntity: Meuble::class)]
    #[ORM\JoinColumn(name: 'id_meuble', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Meuble $meuble = null;

    public function __construct()
    {
    }

    // Constructeur avec paramètres (optionnel)
    public function __constructWithIds(?Panier $panier, ?Meuble $meuble)
    {
        $this->panier = $panier;
        $this->meuble = $meuble;
    }

    // Getters et Setters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getPanier(): ?Panier
    {
        return $this->panier;
    }

    public function setPanier(?Panier $panier): self
    {
        $this->panier = $panier;
        return $this;
    }

    public function getMeuble(): ?Meuble
    {
        return $this->meuble;
    }

    public function setMeuble(?Meuble $meuble): self
    {
        $this->meuble = $meuble;
        return $this;
    }

    public function __toString(): string
    {
        return sprintf(
            'LignePanier{id: %d, idPanier: %d, idMeuble: %d}',
            $this->id ?? 0,
            $this->panier?->getId() ?? 0,
            $this->meuble?->getId() ?? 0
        );
    }
}