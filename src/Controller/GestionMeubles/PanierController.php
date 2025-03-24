<?php

namespace App\Controller\GestionMeubles; // Corrige ici

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PanierController extends AbstractController{
    #[Route('/meubles/panier', name: 'app_gestion_meubles_panier')]
    public function index(): Response
    {
        return $this->render('gestion_meubles/panier/index.html.twig', [
            'controller_name' => 'GestionMeubles/PanierController',
        ]);
    }
}
