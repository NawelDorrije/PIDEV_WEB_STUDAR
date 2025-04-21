<?php

namespace App\Entity;

use App\Repository\OptionsRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OptionsRepository::class)]
#[ORM\Table(name: 'options')]
class Options
{
    #[ORM\Id]
    #[ORM\Column(name: "id_option", type: "integer", options: ["unsigned" => true])]
    #[ORM\GeneratedValue(strategy: "IDENTITY")]
    private ?int $id_option = null;

    #[ORM\Column(length: 255)]
    private ?string $nom_option = null;

    /**
     * @var Collection<int, LogementOptions>
     */
    #[ORM\OneToMany(targetEntity: LogementOptions::class, mappedBy: 'option')]
    private Collection $logementOptions;

    public function __construct()
    {
        $this->logementOptions = new ArrayCollection();
    }

    public function getIdOption(): ?int
    {
        return $this->id_option;
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
            $logementOption->setOption($this);
        }
        return $this;
    }

    public function removeLogementOption(LogementOptions $logementOption): static
    {
        if ($this->logementOptions->removeElement($logementOption)) {
            if ($logementOption->getOption() === $this) {
                $logementOption->setOption(null);
            }
        }
        return $this;
    }
}