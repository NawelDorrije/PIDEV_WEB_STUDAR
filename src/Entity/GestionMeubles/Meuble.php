<?php

namespace App\Entity\GestionMeubles;

use App\Repository\GestionMeubles\MeubleRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: MeubleRepository::class)]
#[ORM\Table(name: "meubles")]
class Meuble
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Le nom du meuble est obligatoire.")]
    #[Assert\Length(min: 3, minMessage: "Le nom doit contenir au moins {{ limit }} caractères.")]
    private ?string $nom = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(min: 10, minMessage: "La description doit contenir au moins {{ limit }} caractères.")]
    #[Assert\NotBlank(message: "La description ne peut pas être vide si elle est renseignée.", allowNull: true)]
    private ?string $description = null;

    #[ORM\Column(type: "decimal", precision: 10, scale: 2)]
    #[Assert\NotBlank(message: "Le prix est obligatoire.")]
    #[Assert\Positive(message: "Le prix doit être un nombre positif.")]
    private ?float $prix = null;

    #[ORM\Column(type: "string", length: 50)]
    #[Assert\NotBlank(message: "Le statut du meuble est obligatoire.")]
    #[Assert\Choice(choices: ["disponible", "indisponible"], message: "Le statut doit être 'disponible' ou 'indisponible'.")]
    private ?string $statut = null;

    #[ORM\Column(type: "string", length: 50)]
    #[Assert\NotBlank(message: "La catégorie du meuble est obligatoire.")]
    #[Assert\Choice(choices: ["neuf", "occasion"], message: "La catégorie doit être 'neuf' ou 'occasion'.")]
    private ?string $categorie = null;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank(message: "Le CIN du vendeur est obligatoire.")]
    #[Assert\Length(max: 20, maxMessage: "Le CIN du vendeur ne peut pas dépasser {{ limit }} caractères.")]
    private ?string $cinVendeur = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Assert\NotBlank(message: "La date d'enregistrement est obligatoire.")]
    private ?\DateTime $dateEnregistrement = null;

    #[ORM\Column(type: "string", length: 255, nullable: true)]
    #[Assert\File(mimeTypes: ["image/jpeg", "image/png", "image/webp"], mimeTypesMessage: "Le fichier doit être une image valide (JPG, PNG, WEBP).")]
    private ?string $image = null;

    public function getId(): ?int { return $this->id; }

    public function getNom(): ?string { return $this->nom; }
    public function setNom(string $nom): static { $this->nom = $nom; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }

    public function getPrix(): ?float { return $this->prix; }
    public function setPrix(float $prix): static { $this->prix = $prix; return $this; }

    public function getStatut(): ?string { return $this->statut; }
    public function setStatut(string $statut): static { $this->statut = $statut; return $this; }

    public function getCategorie(): ?string { return $this->categorie; }
    public function setCategorie(string $categorie): static { $this->categorie = $categorie; return $this; }

    public function getCinVendeur(): ?string { return $this->cinVendeur; }
    public function setCinVendeur(string $cinVendeur): static { $this->cinVendeur = $cinVendeur; return $this; }

    public function getDateEnregistrement(): ?\DateTime { return $this->dateEnregistrement; }
    public function setDateEnregistrement(\DateTime $dateEnregistrement): static { $this->dateEnregistrement = $dateEnregistrement; return $this; }

    public function getImage(): ?string { return $this->image; }
    public function setImage(?string $image): static { $this->image = $image; return $this; }
}