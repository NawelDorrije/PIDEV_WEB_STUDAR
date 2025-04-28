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
use Symfony\Component\Security\Http\Attribute\IsGranted;


#[Route('/reservation/transport')]
final class ReservationTransportController extends AbstractController
{

    #[Route('/', name: 'app_reservation_transport_base', methods: ['GET'])]
    public function baseRedirect(): Response
    {
        if ($this->isGranted('ROLE_TRANSPORTEUR')) {
            return $this->redirectToRoute('app_reservation_transport_index');
        }
        if ($this->isGranted('ROLE_ETUDIANT')) {
            return $this->redirectToRoute('app_reservation_transport_etudiant');
        }
        
        throw $this->createAccessDeniedException();
    }

    #[Route('/transporteur', name: 'app_reservation_transport_index', methods: ['GET'])]
    #[IsGranted('ROLE_TRANSPORTEUR')]
    public function indexTransporteur(Request $request, ReservationTransportRepository $repo): Response
    {
        $status = $request->query->get('status');
        
        $reservations = $repo->findByTransporteurAndStatus(
            $this->getUser(),
            $status
        );

        return $this->render('reservation_transport/index.html.twig', [
            'reservations' => $reservations,
            'current_status' => $status,
            'user_role' => 'transporteur'
        ]);
    }

    #[Route('/etudiant', name: 'app_reservation_transport_etudiant', methods: ['GET'])]
    #[IsGranted('ROLE_ETUDIANT')]
    public function indexEtudiant(Request $request, ReservationTransportRepository $repo): Response
    {
        $status = $request->query->get('status');
        
        $reservations = $repo->findByEtudiantAndStatus(
            $this->getUser(),
            $status
        );

        return $this->render('reservation_transport/index_etudiant.html.twig', [
            'reservations' => $reservations,
            'current_status' => $status,
            'user_role' => 'etudiant'
        ]);
    }

    #[Route('/new', name: 'app_reservation_transport_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request, 
        EntityManagerInterface $em,
        Geocoder $geocoder
    ): Response {
        $reservation = new ReservationTransport(); 

        // Automatically set the etudiant if the current user is a student
        if ($this->isGranted('ROLE_ETUDIANT')) {
            $reservation->setEtudiant($this->getUser());
        }    

        $form = $this->createForm(ReservationTransportType::class, $reservation);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Géocodage des adresses
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
                
                // Redirect based on user role
                if ($this->isGranted('ROLE_ETUDIANT')) {
                    return $this->redirectToRoute('app_reservation_transport_etudiant');
                }
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
        $user = $this->getUser();
        
        if (!$user || ($user !== $reservationTransport->getTransporteur() && $user !== $reservationTransport->getEtudiant())) {
            throw $this->createAccessDeniedException('You can only view your own reservations.');
        }
    
        $template = $this->isGranted('ROLE_ETUDIANT') 
            ? 'reservation_transport/show.html.twig' 
            : 'reservation_transport/show_TRANS.html.twig';
    
        return $this->render($template, [
            'reservation_transport' => $reservationTransport,
           
        ]);
    }

    #[Route('/{id}/edit', name: 'app_reservation_transport_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_TRANSPORTEUR')]
    public function edit(Request $request, ReservationTransport $reservationTransport, EntityManagerInterface $entityManager): Response
    {
        if ($this->getUser() !== $reservationTransport->getTransporteur()) {
            throw $this->createAccessDeniedException('You can only edit your own reservations.');
        }
        
        $form = $this->createForm(ReservationTransportType::class, $reservationTransport);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            
            $this->addFlash('success', 'Réservation mise à jour avec succès');
            return $this->redirectToRoute('app_reservation_transport_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('reservation_transport/edit.html.twig', [
            'reservation' => $reservationTransport,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_reservation_transport_delete', methods: ['POST'])]
    #[IsGranted('ROLE_TRANSPORTEUR')]
    public function delete(Request $request, ReservationTransport $reservationTransport, EntityManagerInterface $entityManager): Response
    {
        if ($this->getUser() !== $reservationTransport->getTransporteur()) {
            throw $this->createAccessDeniedException('You can only delete your own reservations.');
        }
        
        if ($this->isCsrfTokenValid('delete'.$reservationTransport->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($reservationTransport);
            $entityManager->flush();
            $this->addFlash('success', 'Réservation supprimée avec succès');
        }

        return $this->redirectToRoute('app_reservation_transport_index', [], Response::HTTP_SEE_OTHER);
    }
    
    

    
    }
