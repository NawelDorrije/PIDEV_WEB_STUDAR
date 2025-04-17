<?php

namespace App\Entity\GestionMeubles;

use App\Entity\Utilisateur;
use App\Repository\GestionMeubles\MeubleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
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

    #[ORM\ManyToOne(targetEntity: Utilisateur::class, inversedBy: 'meubles')]
    #[ORM\JoinColumn(name: 'cin_vendeur', referencedColumnName: 'cin', nullable: false)]
    #[Assert\NotBlank(message: "Le vendeur est obligatoire.")]
    private ?Utilisateur $vendeur = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Assert\NotBlank(message: "La date d'enregistrement est obligatoire.")]
    private ?\DateTime $dateEnregistrement = null;

    #[ORM\Column(type: "string", length: 255, nullable: true)]
    #[Assert\File(mimeTypes: ["image/jpeg", "image/png", "image/webp"], mimeTypesMessage: "Le fichier doit être une image valide (JPG, PNG, WEBP).")]
    private ?string $image = null;

    #[ORM\OneToMany(mappedBy: 'meuble', targetEntity: LignePanier::class, cascade: ['persist', 'remove'])]
    private Collection $lignesPanier;

    public function __construct()
    {
        $this->lignesPanier = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): self
    {
        $this->nom = $nom;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getPrix(): ?float
    {
        return $this->prix;
    }

    public function setPrix(float $prix): self
    {
        $this->prix = $prix;
        return $this;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): self
    {
        $this->statut = $statut;
        return $this;
    }

    public function getCategorie(): ?string
    {
        return $this->categorie;
    }

    public function setCategorie(string $categorie): self
    {
        $this->categorie = $categorie;
        return $this;
    }

    public function getVendeur(): ?Utilisateur
    {
        return $this->vendeur;
    }

    public function setVendeur(?Utilisateur $vendeur): self
    {
        $this->vendeur = $vendeur;
        return $this;
    }

    public function getDateEnregistrement(): ?\DateTime
    {
        return $this->dateEnregistrement;
    }

    public function setDateEnregistrement(\DateTime $dateEnregistrement): self
    {
        $this->dateEnregistrement = $dateEnregistrement;
        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): self
    {
        $this->image = $image;
        return $this;
    }

    /**
     * @return Collection<int, LignePanier>
     */
    public function getLignesPanier(): Collection
    {
        return $this->lignesPanier;
    }

    public function addLignePanier(LignePanier $lignePanier): self
    {
        if (!$this->lignesPanier->contains($lignePanier)) {
            $this->lignesPanier->add($lignePanier);
            $lignePanier->setMeuble($this);
        }
        return $this;
    }

    public function removeLignePanier(LignePanier $lignePanier): self
    {
        if ($this->lignesPanier->removeElement($lignePanier)) {
            if ($lignePanier->getMeuble() === $this) {
                $lignePanier->setMeuble(null);
            }
        }
        return $this;
    }
}