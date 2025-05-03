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

#[Route('/reservation/transport')]
final class ReservationTransportController extends AbstractController
{
    #[Route('/', name: 'app_reservation_transport_index', methods: ['GET'])]
    public function index(Request $request, ReservationTransportRepository $repo): Response
    {
        $utilisateur = $this->getUser();
        if (!$utilisateur instanceof Utilisateur) {
            throw $this->createAccessDeniedException('Vous devez être connecté.');
        }

        $status = $request->query->get('status');
        $reservations = $status 
            ? $repo->findBy(['status' => $status, 'etudiant' => $utilisateur]) 
            : $repo->findBy(['etudiant' => $utilisateur]);

        return $this->render('reservation_transport/index.html.twig', [
            'reservations' => $reservations,
            'current_status' => $status
        ]);
    }

    #[Route('/new', name: 'app_reservation_transport_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request, 
        EntityManagerInterface $em,
        Geocoder $geocoder
    ): Response {
        $utilisateur = $this->getUser();
        if (!$utilisateur instanceof Utilisateur) {
            throw $this->createAccessDeniedException('Vous devez être connecté.');
        }

        $reservation = new ReservationTransport();
        $form = $this->createForm(ReservationTransportType::class, $reservation);
        
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $reservation->setEtudiant($utilisateur);

                if ($reservation->getAdresseDepart()) {
                    $departure = $geocoder->geocode($reservation->getAdresseDepart());
                    if ($departure) {
                        $reservation->setDepartureLat((float)$departure['lat']);
                        $reservation->setDepartureLng((float)$departure['lon']);
                    } else {
                        $this->addFlash('warning', 'L\'adresse de départ n\'a pas pu être localisée sur la carte');
                    }
                }
                
                if ($reservation->getAdresseDestination()) {
                    $destination = $geocoder->geocode($reservation->getAdresseDestination());
                    if ($destination) {
                        $reservation->setDestinationLat((float)$destination['lat']);
                        $reservation->setDestinationLng((float)$destination['lon']);
                    } else {
                        $this->addFlash('warning', 'L\'adresse de destination n\'a pas pu être localisée sur la carte');
                    }
                }
                
                $em->persist($reservation);
                $em->flush();
                
                $this->addFlash('success', 'Réservation créée avec succès');
                return $this->redirectToRoute('app_reservation_transport_index');
                
            } catch (\Exception $e) {
                $this->addFlash('error', 'Une erreur est survenue lors de la création de la réservation');
            }
        }

        return $this->render('reservation_transport/new.html.twig', [
            'form' => $form->createView(),
            'reservation' => $reservation
        ]);
    }

    #[Route('/{id}', name: 'app_reservation_transport_show', methods: ['GET'])]
    public function show(ReservationTransport $reservationTransport): Response
    {
        $utilisateur = $this->getUser();
        if (!$utilisateur instanceof Utilisateur || $reservationTransport->getEtudiant() !== $utilisateur) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas accéder à cette réservation.');
        }

        return $this->render('reservation_transport/show.html.twig', [
            'reservation_transport' => $reservationTransport,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_reservation_transport_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, ReservationTransport $reservationTransport, EntityManagerInterface $entityManager): Response
    {
        $utilisateur = $this->getUser();
        if (!$utilisateur instanceof Utilisateur || $reservationTransport->getEtudiant() !== $utilisateur) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas modifier cette réservation.');
        }

        $form = $this->createForm(ReservationTransportType::class, $reservationTransport);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_reservation_transport_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('reservation_transport/edit.html.twig', [
            'reservation_transport' => $reservationTransport,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_reservation_transport_delete', methods: ['POST'])]
    public function delete(Request $request, ReservationTransport $reservationTransport, EntityManagerInterface $entityManager): Response
    {
        $utilisateur = $this->getUser();
        if (!$utilisateur instanceof Utilisateur || $reservationTransport->getEtudiant() !== $utilisateur) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas supprimer cette réservation.');
        }

        if ($this->isCsrfTokenValid('delete'.$reservationTransport->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($reservationTransport);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_reservation_transport_index', [], Response::HTTP_SEE_OTHER);
    }
}