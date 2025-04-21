<?php

namespace App\Entity;

use App\Repository\LogementOptionsRepository;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\LogementOptionsId;


#[ORM\Entity(repositoryClass: LogementOptionsRepository::class)]
#[ORM\Table(name: 'logement_options')]
#[IdClass(LogementOptionsId::class)]
class LogementOptions
{
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Logement::class, inversedBy: 'logementOptions')]
    #[ORM\JoinColumn(name: 'id_logement', referencedColumnName: 'id', nullable: false)]
    private ?Logement $logement = null;

    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Options::class, inversedBy: 'logementOptions')]
    #[ORM\JoinColumn(name: 'id_option', referencedColumnName: 'id_option', nullable: false)]
    private ?Options $option = null;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $valeur = null;

    public function __construct(?Logement $logement = null, ?Options $option = null)
    {
        $this->logement = $logement;
        $this->option = $option;
        $this->valeur = true;
    }

    public function getLogement(): ?Logement
    {
        return $this->logement;
    }

    public function setLogement(?Logement $logement): static
    {
        $this->logement = $logement;
        return $this;
    }

    public function getOption(): ?Options
    {
        return $this->option;
    }

    public function setOption(?Options $option): static
    {
        $this->option = $option;
        return $this;
    }

    public function getValeur(): ?bool
    {
        return $this->valeur;
    }

    public function setValeur(?bool $valeur): static
    {
        $this->valeur = $valeur;
        return $this;
    }
}