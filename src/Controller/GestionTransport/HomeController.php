<?php

namespace App\Controller\GestionTransport;

use App\Repository\GestionTransport\VoitureRepository;
use App\Repository\GestionTransport\TransportRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/gestion-transport')]
class HomeController extends AbstractController
{
    #[Route('/', name: 'app_transport_home')]
    public function index(VoitureRepository $voitureRepository, TransportRepository $transportRepository): Response
    {
        $voitures = $voitureRepository->findBy([], ['timestamp' => 'DESC'], 3);
        $transports = $transportRepository->findBy([], ['timestamp' => 'DESC'], 3);

        return $this->render('GestionTransport/index.html.twig', [
            'voitures' => $voitures,
            'transports' => $transports,
        ]);
    }

    #[Route('/test-route', name: 'app_test')]
    public function test(): Response
    {
        return new Response('Test successful!');
    }
}