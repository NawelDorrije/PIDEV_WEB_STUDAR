<?php

namespace App\Controller\GestionTransport;

use App\Entity\GestionTransport\Transport;
use App\Form\GestionTransport\TransportType;
use App\Repository\GestionTransport\TransportRepository;
use App\Service\DistanceService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;


#[IsGranted('ROLE_TRANSPORTEUR')]
#[Route('/transport')]
final class TransportController extends AbstractController
{
    #[Route(name: 'app_transport_index', methods: ['GET'])]
    public function index(TransportRepository $transportRepository, Request $request): Response
    {
        $status = $request->query->get('status');
        $user = $this->getUser();
        
        $transports = $status 
            ? $transportRepository->findByStatusAndUser($status, $user)
            : $transportRepository->findByUser($user);

        return $this->render('GestionTransport/transport/index.html.twig', [
            'transports' => $transports,
            'current_filter' => $status
        ]);
    }

    #[Route('/new', name: 'app_transport_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, DistanceService $distanceService): Response
    {
        $transport = new Transport();
        $form = $this->createForm(TransportType::class, $transport);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                
           
            $reservation = $transport->getReservation();
            $depart = $reservation->getAdresseDepart();
            $arrivee = $reservation->getAdresseDestination();

            $distanceKm = $distanceService->calculateDistanceKm($depart, $arrivee);
            $transport->setTrajetEnKm($distanceKm);

            // Example tarif calculation
            $tarif = $distanceKm * 0.5;
            $transport->setTarif($tarif);

            $entityManager->persist($reservation);
            $entityManager->persist($transport);
            $entityManager->flush();
            $this->addFlash('succès', 'Transport créé avec succès');
            return $this->redirectToRoute('app_transport_index', [], Response::HTTP_SEE_OTHER); }
            catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException $e) {
                $this->addFlash('erreur', 'Cette réservation est déjà prise par un autre transport.');
            } catch (\Exception $e) {
                $this->addFlash('erreur', 'Une erreur est survenue lors de la création du transport.');
            }
        }

        return $this->render('GestionTransport/transport/new.html.twig', [
            'transport' => $transport,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_transport_show', methods: ['GET'])]
    public function show(Transport $transport): Response
    {
        return $this->render('GestionTransport/transport/show.html.twig', [
            'transport' => $transport,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_transport_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request, 
        Transport $transport, 
        EntityManagerInterface $entityManager,
        DistanceService $distanceService
    ): Response {
        $form = $this->createForm(TransportType::class, $transport);
        $form->handleRequest($request);
    
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                
           
            // Recalculate distance and tariff when reservation changes
            $reservation = $transport->getReservation();
            $depart = $reservation->getAdresseDepart();
            $arrivee = $reservation->getAdresseDestination();
    
            $distanceKm = $distanceService->calculateDistanceKm($depart, $arrivee);
            $transport->setTrajetEnKm($distanceKm);
    
            // Recalculate tariff
            $tarif = $distanceKm * 0.5; // Adjust multiplier as needed
            $transport->setTarif($tarif);
    
            $entityManager->flush();
    
            return $this->redirectToRoute('app_transport_index', [], Response::HTTP_SEE_OTHER); }
            catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException $e) {
                $this->addFlash('erreur', 'Cette réservation est déjà prise par un autre transport.');     
            }
            catch (\Exception $e) {
                $this->addFlash('erreur', 'Une erreur est survenue lors de la modification du transport.'); 
            }
        }
    
        return $this->render('GestionTransport/transport/edit.html.twig', [
            'transport' => $transport,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_transport_delete', methods: ['POST'])]
    public function delete(Request $request, Transport $transport, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$transport->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($transport);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_transport_index', [], Response::HTTP_SEE_OTHER);
    }
}
