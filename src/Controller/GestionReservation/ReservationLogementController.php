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

#[Route('/reservation/logement')]
final class ReservationLogementController extends AbstractController
{
    #[Route(name: 'app_reservation_logement_index', methods: ['GET'])]
    public function index(ReservationLogementRepository $reservationLogementRepository): Response
    {
        return $this->render('reservation_logement/index.html.twig', [
            'reservation_logements' => $reservationLogementRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_reservation_logement_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $reservationLogement = new ReservationLogement();
        $form = $this->createForm(ReservationLogementType::class, $reservationLogement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($reservationLogement);
            $entityManager->flush();

            return $this->redirectToRoute('app_reservation_logement_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('reservation_logement/new.html.twig', [
            'reservation_logement' => $reservationLogement,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_reservation_logement_show', methods: ['GET'])]
    public function show(ReservationLogement $reservationLogement): Response
    {
        return $this->render('reservation_logement/show.html.twig', [
            'reservation_logement' => $reservationLogement,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_reservation_logement_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, ReservationLogement $reservationLogement, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ReservationLogementType::class, $reservationLogement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_reservation_logement_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('reservation_logement/edit.html.twig', [
            'reservation_logement' => $reservationLogement,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_reservation_logement_delete', methods: ['POST'])]
    public function delete(Request $request, ReservationLogement $reservationLogement, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$reservationLogement->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($reservationLogement);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_reservation_logement_index', [], Response::HTTP_SEE_OTHER);
    }
}
