<?php

namespace App\Controller\GestionTransport;

use App\Repository\GestionTransport\VoitureRepository;
use App\Repository\GestionTransport\TransportRepository;
use App\Enums\GestionTransport\VoitureDisponibilite;
use App\Enums\GestionTransport\TransportStatus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_TRANSPORTEUR')]
#[Route('/gestion-transport')]
class HomeController extends AbstractController
{
    #[Route('/', name: 'app_transport_home')]
    public function index(VoitureRepository $voitureRepository, TransportRepository $transportRepository): Response
    {
        $user = $this->getUser();
        
        // Get current user's vehicles
        $voitures = $voitureRepository->findBy(['utilisateur' => $user], ['timestamp' => 'DESC']);
        
        // Get all transports for current user's vehicles
        $allTransports = [];
        foreach ($voitures as $voiture) {
            $vehicleTransports = $transportRepository->findBy(['voiture' => $voiture]);
            $allTransports = array_merge($allTransports, $vehicleTransports);
        }
        
        // Filter active transports
        $transports = array_filter($allTransports, function($t) {
            return $t->getStatus() === TransportStatus::ACTIF;
        });
        
        // Calculate stats for current user only
        $stats = [
            'total_voitures' => count($voitures),
            'voitures_disponibles' => count(array_filter($voitures, function($v) {
                return $v->getDisponibilite() === VoitureDisponibilite::DISPONIBLE;
            })),
            'total_transports' => count($allTransports),
            'transports_actifs' => count($transports),
            'transports_completes' => count(array_filter($allTransports, function($t) {
                return $t->getStatus() === TransportStatus::COMPLETE;
            })),
        ];

        return $this->render('GestionTransport/index.html.twig', [
            'voitures' => $voitures,
            'transports' => $transports,
            'stats' => $stats,
        ]);
    }
}