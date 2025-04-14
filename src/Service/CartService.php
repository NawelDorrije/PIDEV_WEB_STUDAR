<?php

namespace App\Service;

use App\Entity\Utilisateur;
use App\Repository\GestionMeubles\PanierRepository;
use App\Repository\GestionMeubles\LignePanierRepository;
use Symfony\Component\Security\Core\Security;

class CartService
{
    private $panierRepository;
    private $lignePanierRepository;
    private $security;

    public function __construct(
        PanierRepository $panierRepository,
        LignePanierRepository $lignePanierRepository,
        Security $security
    ) {
        $this->panierRepository = $panierRepository;
        $this->lignePanierRepository = $lignePanierRepository;
        $this->security = $security;
    }

    public function getCartCount(): int
    {
        $utilisateur = $this->security->getUser();
        if (!$utilisateur instanceof Utilisateur) {
            return 0;
        }

        $panier = $this->panierRepository->findPanierEnCours($utilisateur);
        if (!$panier) {
            return 0;
        }

        $lignesPanier = $this->lignePanierRepository->findByPanierId($panier->getId());
        return count($lignesPanier);
    }
}