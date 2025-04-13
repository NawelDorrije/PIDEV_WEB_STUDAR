<?php

namespace App\Controller\GestionReservation;

use App\Entity\ReservationLogement;
use App\Form\ReservationLogementType;
use App\Repository\ReservationLogementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use TCPDF;
use TCPDF2DBarcode;

#[Route('/reservation/logement_ADMIN')]
final class ReservationLogementController_ADMIN extends AbstractController
{
    #[Route('/', name: 'app_reservation_logement_index_ADMIN', methods: ['GET'])]
    public function index(Request $request, ReservationLogementRepository $reservationLogementRepository): Response
    {
        $status = $request->query->get('status');
        
        if ($status) {
            $reservations = $reservationLogementRepository->findBy(['status' => $status]);
        } else {
            $reservations = $reservationLogementRepository->findAll();
        }
        
        return $this->render('reservation_logement/index_ADMIN.html.twig', [
            'reservation_logements' => $reservations,
            'current_status' => $status
        ]);
    }

  
    #[Route('/{id}', name: 'app_reservation_logement_show_ADMIN', methods: ['GET'])]
    public function show(ReservationLogement $reservationLogement): Response
    {
        return $this->render('reservation_logement/show_ADMIN.html.twig', [
            'reservation_logement' => $reservationLogement,
        ]);
    }

#[Route('/statistics/owner', name: 'app_reservation_logement_statistics_owner')]
public function statisticsOwner(ReservationLogementRepository $repository): Response
{
    // Récupérer le CIN du propriétaire connecté (à adapter selon votre système d'authentification)
    $cinProprietaire = $this->getUser()->getCin(); // Adaptez cette ligne
    
    $stats = $repository->getMonthlyStatisticsForOwner($cinProprietaire);
    
    return $this->render('reservation_logement/statistics_owner.html.twig', [
        'stats' => $stats,
        'max' => !empty($stats) ? max(array_column($stats, 'count')) : 0
    ]);
}
}
