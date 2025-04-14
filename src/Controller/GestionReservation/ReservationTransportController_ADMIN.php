<?php

namespace App\Controller\GestionReservation;

use App\Entity\ReservationTransport;
use App\Form\ReservationTransportType;
use App\Repository\ReservationTransportRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Service\Geocoder;


#[Route('/reservation/transport_ADMIN')]
final class ReservationTransportController_ADMIN extends AbstractController
{
  #[Route('/', name: 'app_reservation_transport_index_ADMIN', methods: ['GET'])]
  public function index(Request $request, ReservationTransportRepository $repo): Response
  {
      $status = $request->query->get('status');
      $reservations = $status ? $repo->findBy(['status' => $status]) : $repo->findAll();

      return $this->render('reservation_transport/index_ADMIN.html.twig', [
          'reservations' => $reservations,
          'current_status' => $status
      ]);
  }

  
    #[Route('/{id}', name: 'app_reservation_transport_show_ADMIN', methods: ['GET'])]
    public function show(ReservationTransport $reservationTransport): Response
    {
        return $this->render('reservation_transport/show_ADMIN.html.twig', [
            'reservation_transport' => $reservationTransport,
        ]);
    }

    

    
}
