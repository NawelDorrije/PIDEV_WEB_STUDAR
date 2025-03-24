<?php

namespace App\Controller\GestionMeubles; // Corrige ici

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class MeubleController extends AbstractController{
    #[Route('/meubles', name: 'app_gestion_meubles_meuble')]
    public function index(): Response
    {
        return $this->render('gestion_meubles/meuble/index.html.twig', [
            'controller_name' => 'GestionMeubles/MeubleController',
        ]);
    }
    #[Route('/meubles/ajouter', name: 'app_gestion_meuble_ajouter')]
    public function ajouter(): Response
    {
        return $this->render('gestion_meubles/meuble/ajouter.html.twig', [
            'controller_name' => 'GestionMeubles/MeubleController',
        ]);
    }
}
