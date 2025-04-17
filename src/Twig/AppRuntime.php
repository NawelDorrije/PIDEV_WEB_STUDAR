<?php

namespace App\Twig;

use App\Repository\GestionMeubles\LignePanierRepository;
use App\Repository\GestionMeubles\PanierRepository;
use Twig\Extension\RuntimeExtensionInterface;

class AppRuntime implements RuntimeExtensionInterface
{
    private $lignePanierRepository;
    private $panierRepository;

    public function __construct(LignePanierRepository $lignePanierRepository, PanierRepository $panierRepository)
    {
        $this->lignePanierRepository = $lignePanierRepository;
        $this->panierRepository = $panierRepository;
    }

    public function getCartCount(?string $cinAcheteur = "14450157"): int
    {
        $panier = $this->panierRepository->findPanierEnCours($cinAcheteur);
        if (!$panier) {
            return 0;
        }

        $lignesPanier = $this->lignePanierRepository->findByPanierId($panier->getId());
        return count($lignesPanier);
    }
}