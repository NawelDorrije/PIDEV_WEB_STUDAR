<?php
namespace App\Entity;

use App\Enums\Statut;
use App\Repository\LogementRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use LongitudeOne\Spatial\PHP\Types\SpatialInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: LogementRepository::class)]
#[ORM\Table(name: "logement")]
class Logement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id')]
    private int $id;

    #[ORM\Column(name: 'nbrChambre')]
    #[Assert\NotBlank(message: "Le nombre de chambres ne peut pas être vide.")]
    #[Assert\GreaterThan(
        value: 0,
        message: "Le nombre de chambres doit être supérieur à 0."
    )]
    private int $nbrChambre;

    #[ORM\Column]
    #[Assert\NotBlank(message: "Le prix ne peut pas être vide.")]
    #[Assert\GreaterThan(
        value: 0,
        message: "Le prix doit être supérieur à 0."
    )]
    private float $prix;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "La description ne peut pas être vide.")]
    private ?string $description = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Le type ne peut pas être vide.")]
    private ?string $type = null;

    #[ORM\Column(type: 'point')]
    private ?SpatialInterface $localisation = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $adresse = null;

    #[ORM\Column(type: 'string', enumType: Statut::class)]
    private Statut $statut = Statut::DISPONIBLE;
  /**
    * @var Collection<int, LogementOptions>
    */
    #[ORM\OneToMany(
        targetEntity: LogementOptions::class, 
        mappedBy: 'logement',  // Must match property name in LogementOptions
        orphanRemoval: true
    )]
    private Collection $logementOptions;

  /**
     * @var Collection<int, ImageLogement>
     */
    #[ORM\OneToMany(
        targetEntity: ImageLogement::class, 
        mappedBy: 'logement', // Corrected to match the property name in ImageLogement
        cascade: ['persist', 'remove']
    )]
    private Collection $imageLogements;
    #[ORM\ManyToOne(inversedBy: 'logements')]
    #[ORM\JoinColumn(name: 'utilisateur_cin', referencedColumnName: 'cin')]
    private ?Utilisateur $utilisateur_cin = null;

    /**
     * @var Collection<int, Reclamation>
     */
    #[ORM\OneToMany(targetEntity: Reclamation::class, mappedBy: 'logement')]
    private Collection $reclamations;
  
   public function __construct()
   {
       $this->logementOptions = new ArrayCollection();
       $this->imageLogements = new ArrayCollection();
       $this->reclamations = new ArrayCollection();
   }
 
   public function __toString(): string
   {
       return $this->type . ' (ID: ' . $this->id . ')';
   }
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNbrChambre(): int
    {
        return $this->nbrChambre;
    }

    public function setNbrChambre(int $nbrChambre): static
    {
        $this->nbrChambre = $nbrChambre;

        return $this;
    }

    

    public function getPrix(): float
    {
        return $this->prix;
    }

    public function setPrix(float $prix): static
    {
        $this->prix = $prix;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getLocalisation(): ?SpatialInterface
    {
        return $this->localisation;
    }

    public function setLocalisation(SpatialInterface $localisation): static
    {
        $this->localisation = $localisation;

        return $this;
    }

    public function getAdresse(): ?string
    {
        return $this->adresse;
    }
    
    public function setAdresse(?string $adresse): static
    {
        $this->adresse = $adresse;
    
        return $this;
    }

    public function getStatut(): Statut
    {
        return $this->statut;
    }

    public function setStatut(Statut $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    /**
     * @return Collection<int, LogementOptions>
     */
    public function getLogementOptions(): Collection
    {
        return $this->logementOptions;
    }

    public function addLogementOption(LogementOptions $logementOption): static
    {
        if (!$this->logementOptions->contains($logementOption)) {
            $this->logementOptions->add($logementOption);
        }
        return $this;
    }

    public function removeLogementOption(LogementOptions $logementOption): static
    {
        $this->logementOptions->removeElement($logementOption);
        return $this;
    }

   /**
 * @return Collection<int, ImageLogement>
 */
public function getImageLogements(): Collection
{
    return $this->imageLogements;
}

public function addImageLogement(ImageLogement $imageLogement): static
{
    if (!$this->imageLogements->contains($imageLogement)) {
        $this->imageLogements->add($imageLogement);
        $imageLogement->setLogement($this);
    }
    return $this;
}

public function removeImageLogement(ImageLogement $imageLogement): static
{
    if ($this->imageLogements->removeElement($imageLogement)) {
        // set the owning side to null (unless already changed)
        if ($imageLogement->getLogement() === $this) {
            $imageLogement->setLogement( $this);
        }
    }
    return $this;
}

public function getUtilisateurCin(): ?Utilisateur
{
    return $this->utilisateur_cin;
}

public function setUtilisateurCin(?Utilisateur $utilisateur_cin): static
{
    $this->utilisateur_cin = $utilisateur_cin;

    return $this;
}

/**
 * @return Collection<int, Reclamation>
 */
public function getReclamations(): Collection
{
    return $this->reclamations;
}

public function addReclamation(Reclamation $reclamation): static
{
    if (!$this->reclamations->contains($reclamation)) {
        $this->reclamations->add($reclamation);
        $reclamation->setLogement($this);
    }

    return $this;
}

public function removeReclamation(Reclamation $reclamation): static
{
    if ($this->reclamations->removeElement($reclamation)) {
        // set the owning side to null (unless already changed)
        if ($reclamation->getLogement() === $this) {
            $reclamation->setLogement(null);
        }
    }

    return $this;
}


}