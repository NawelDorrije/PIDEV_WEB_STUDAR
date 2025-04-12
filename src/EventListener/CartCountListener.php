<?php

namespace App\EventListener;

use App\Entity\Utilisateur;
use App\Repository\GestionMeubles\LignePanierRepository;
use App\Repository\GestionMeubles\PanierRepository;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class CartCountListener
{
    private $panierRepository;
    private $lignePanierRepository;
    private $tokenStorage;

    public function __construct(
        PanierRepository $panierRepository,
        LignePanierRepository $lignePanierRepository,
        TokenStorageInterface $tokenStorage
    ) {
        $this->panierRepository = $panierRepository;
        $this->lignePanierRepository = $lignePanierRepository;
        $this->tokenStorage = $tokenStorage;
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $user = $this->tokenStorage->getToken()?->getUser();

        if ($user instanceof Utilisateur) {
            $panier = $this->panierRepository->findPanierEnCours($user);
            $cartCount = 0;
            if ($panier) {
                $lignesPanier = $this->lignePanierRepository->findByPanierId($panier->getId());
                $cartCount = count($lignesPanier);
            }
            $request->attributes->set('cartCount', $cartCount);
        } else {
            $request->attributes->set('cartCount', 0);
        }
    }
}