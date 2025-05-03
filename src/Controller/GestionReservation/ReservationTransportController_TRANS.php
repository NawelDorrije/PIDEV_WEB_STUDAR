<?php

namespace App\Controller\GestionReservation;

use App\Entity\ReservationTransport;
use App\Entity\Utilisateur;
use App\Form\ReservationTransportType;
use App\Repository\ReservationTransportRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Service\Geocoder;

#[Route('/reservation/transport_TRANS')]
final class ReservationTransportController_TRANS extends AbstractController
{
    #[Route('/', name: 'app_reservation_transport_index_TRANS', methods: ['GET'])]
    public function index(Request $request, ReservationTransportRepository $repo): Response
    {
        $utilisateur = $this->getUser();
        if (!$utilisateur instanceof Utilisateur) {
            throw $this->createAccessDeniedException('Vous devez être connecté.');
        }

        $status = $request->query->get('status');
        $reservations = $status 
            ? $repo->findBy(['status' => $status, 'transporteur' => $utilisateur]) 
            : $repo->findBy(['transporteur' => $utilisateur]);

        return $this->render('reservation_transport/index_TRANS.html.twig', [
            'reservations' => $reservations,
            'current_status' => $status
        ]);
    }

    #[Route('/{id}', name: 'app_reservation_transport_show_TRANS', methods: ['GET'])]
    public function show(ReservationTransport $reservationTransport): Response
    {
        $utilisateur = $this->getUser();
        if (!$utilisateur instanceof Utilisateur || $reservationTransport->getTransporteur() !== $utilisateur) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas accéder à cette réservation.');
        }

        return $this->render('reservation_transport/show_TRANS.html.twig', [
            'reservation_transport' => $reservationTransport,
        ]);
    }

    #[Route('/{id}/accept', name: 'app_reservationTransport_accept', methods: ['POST'])]
    public function accept(Request $request, ReservationTransport $reservationTransport, EntityManagerInterface $entityManager): Response
    {
        $utilisateur = $this->getUser();
        if (!$utilisateur instanceof Utilisateur || $reservationTransport->getTransporteur() !== $utilisateur) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas accepter cette réservation.');
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
        $utilisateur = $this->getUser();
        if (!$utilisateur instanceof Utilisateur || $reservationTransport->getTransporteur() !== $utilisateur) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas rejeter cette réservation.');
        }

        if ($this->isCsrfTokenValid('reject'.$reservationTransport->getId(), $request->request->get('_token'))) {
            $reservationTransport->setStatus('refusée');
            $entityManager->flush();
        }
    
        return $this->redirectToRoute('app_reservation_transport_index_TRANS');
    }
}