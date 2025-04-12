<?php

namespace App\Entity;

use App\Repository\OptionsRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OptionsRepository::class)]
class Options
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name:'id_option')]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nom_option = null;

    /**
     * @var Collection<int, LogementOptions>
     */
    #[ORM\OneToMany(
        targetEntity: LogementOptions::class, 
        mappedBy: 'option'  // Must match property name in LogementOptions
    )]
    private Collection $logementOptions;

    public function __construct()
    {
        $this->logementOptions = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNomOption(): ?string
    {
        return $this->nom_option;
    }

    public function setNomOption(string $nom_option): static
    {
        $this->nom_option = $nom_option;

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
}
