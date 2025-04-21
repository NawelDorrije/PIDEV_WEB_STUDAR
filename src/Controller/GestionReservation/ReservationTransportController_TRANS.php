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
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Service\Geocoder;

#[Route('/reservation/transport_TRANS')]
final class ReservationTransportController_TRANS extends AbstractController
{
    #[Route('/', name: 'app_reservation_transport_index_TRANS', methods: ['GET'])]
    public function index(Request $request, ReservationTransportRepository $repo): Response
    {
        // Get the current user (transporteur)
        $transporteur = $this->getUser();
        
        // Check if user is logged in and is a transporteur
        if (!$transporteur) {
            throw $this->createAccessDeniedException('You must be logged in as a transporteur to view reservations.');
        }
        
        $status = $request->query->get('status');
        
        // Find reservations for this transporteur, optionally filtered by status
        if ($status) {
            $reservations = $repo->findBy([
                'transporteur' => $transporteur,
                'status' => $status
            ]);
        } else {
            $reservations = $repo->findBy([
                'transporteur' => $transporteur
            ]);
        }

        return $this->render('reservation_transport/index_TRANS.html.twig', [
            'reservations' => $reservations,
            'current_status' => $status
        ]);
    }

    #[Route('/{id}', name: 'app_reservation_transport_show_TRANS', methods: ['GET'])]
    public function show(ReservationTransport $reservationTransport): Response
    {
        // Check if the current user is the transporteur of this reservation
        if ($this->getUser() !== $reservationTransport->getTransporteur()) {
            throw $this->createAccessDeniedException('You can only view your own reservations.');
        }
        
        return $this->render('reservation_transport/show_TRANS.html.twig', [
            'reservation_transport' => $reservationTransport,
        ]);
    }

    #[Route('/{id}/accept', name: 'app_reservationTransport_accept', methods: ['POST'])]
    public function accept(Request $request, ReservationTransport $reservationTransport, EntityManagerInterface $entityManager): Response
    {
        // Check if the current user is the transporteur of this reservation
        if ($this->getUser() !== $reservationTransport->getTransporteur()) {
            throw $this->createAccessDeniedException('You can only modify your own reservations.');
        }
        
        if ($this->isCsrfTokenValid('accept'.$reservationTransport->getId(), $request->request->get('_token'))) {
            $reservationTransport->setStatus('confirmée');
            $entityManager->flush();
        }
    
        return $this->redirectToRoute('app_reservation_transport_index_TRANS');
    }
    
    #[Route('/{id}/reject', name: 'app_reservationTransport_reject', methods: ['POST'])]
    public function reject(Request $request, ReservationTransport $reservationTransport, EntityManagerInterface $entityManager): Response
    {
        // Check if the current user is the transporteur of this reservation
        if ($this->getUser() !== $reservationTransport->getTransporteur()) {
            throw $this->createAccessDeniedException('You can only modify your own reservations.');
        }
        
        if ($this->isCsrfTokenValid('reject'.$reservationTransport->getId(), $request->request->get('_token'))) {
            $reservationTransport->setStatus('refusée');
            $entityManager->flush();
        }
    
        return $this->redirectToRoute('app_reservation_transport_index_TRANS');
    }
    
    #[Route('/api/reservation/{id}/arrival-time', name:"api_reservation_arrival_time", methods: ["GET"])]
    public function getArrivalTimeApi(ReservationTransport $reservation): JsonResponse
    {
        // Check if the current user is the transporteur of this reservation
        if ($this->getUser() !== $reservation->getTransporteur()) {
            return $this->json(['error' => 'Access denied'], 403);
        }
        
        if (!$reservation) {
            return $this->json(['error' => 'Reservation not found'], 404);
        }
        
        return $this->json([
            'arrivalTime' => $reservation->getTempsArrivage(),
            'formatted' => (new \DateTime($reservation->getTempsArrivage()))->format('l j F Y à H:i') // French format
        ]);
    }
}