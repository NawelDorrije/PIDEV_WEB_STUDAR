<?php

namespace App\Controller;

use App\Repository\GestionMeubles\MeubleRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    private MeubleRepository $meubleRepository;
    public function __construct(
        MeubleRepository $meubleRepository,
      
            ) {
        $this->meubleRepository = $meubleRepository;
       
    }

    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        $cinAcheteur = "14450157"; // À remplacer par $this->getUser()->getCin() en production
        $meubles = $this->meubleRepository->findMeublesDisponiblesPourAcheteur($cinAcheteur);
        return $this->render('home/index.html.twig', [
            'controller_name' => 'HomeController',
            'meubles' => $meubles,
            'cin_acheteur' => $cinAcheteur,
        ]);
    }
    
  
}
