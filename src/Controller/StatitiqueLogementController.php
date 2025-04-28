<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class StatitiqueLogementController extends AbstractController
{
    #[Route('/statitique/logement', name: 'app_statitique_logement')]
    public function index(): Response
    {
        return $this->render('statitique_logement/index.html.twig', [
            'controller_name' => 'StatitiqueLogementController',
        ]);
    }
}