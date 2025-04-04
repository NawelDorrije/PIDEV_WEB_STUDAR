<?php

namespace App\Controller\GestionReservation;

use App\Entity\Rendezvous;
use App\Form\RendezvousType;
use App\Repository\RendezvousRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/rendezvous')]
final class RendezvousController extends AbstractController
{
    #[Route('/', name: 'app_rendezvous_index', methods: ['GET'])]
    public function index(Request $request, RendezvousRepository $rendezvousRepository): Response
    {
        $status = $request->query->get('status');
        
        if ($status) {
            $rendezvouses = $rendezvousRepository->findBy(['status' => $status]);
        } else {
            $rendezvouses = $rendezvousRepository->findAll();
        }
        
        return $this->render('rendezvous/index.html.twig', [
            'rendezvouses' => $rendezvouses,
            'current_status' => $status
        ]);
    }

    #[Route('/new', name: 'app_rendezvous_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $rendezvou = new Rendezvous();
        $form = $this->createForm(RendezvousType::class, $rendezvou);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($rendezvou);
            $entityManager->flush();

            return $this->redirectToRoute('app_rendezvous_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('rendezvous/new.html.twig', [
            'rendezvou' => $rendezvou,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_rendezvous_show', methods: ['GET'])]
    public function show(Rendezvous $rendezvou): Response
    {
        return $this->render('rendezvous/show.html.twig', [
            'rendezvou' => $rendezvou,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_rendezvous_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Rendezvous $rendezvou, EntityManagerInterface $entityManager): Response
    {
        // Prevent editing if status is confirmed or refused
        if (!$rendezvou->isModifiable()) {
            $this->addFlash('error', 'Les rendez-vous confirmés ou refusés ne peuvent pas être modifiés');
            return $this->redirectToRoute('app_rendezvous_show', ['id' => $rendezvou->getId()]);
        }

        $form = $this->createForm(RendezvousType::class, $rendezvou);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Le rendez-vous a été modifié avec succès');
            return $this->redirectToRoute('app_rendezvous_show', ['id' => $rendezvou->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('rendezvous/edit.html.twig', [
            'rendezvou' => $rendezvou,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_rendezvous_delete', methods: ['POST'])]
    public function delete(Request $request, Rendezvous $rendezvou, EntityManagerInterface $entityManager): Response
    {
        // Prevent deletion if not allowed
        if (!$rendezvou->isDeletable()) {
            $this->addFlash('error', 'Ce rendez-vous ne peut pas être supprimé');
            return $this->redirectToRoute('app_rendezvous_show', ['id' => $rendezvou->getId()]);
        }

        // CSRF protection
        if ($this->isCsrfTokenValid('delete'.$rendezvou->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($rendezvou);
            $entityManager->flush();
            $this->addFlash('success', 'Le rendez-vous a été supprimé avec succès');
        }

        return $this->redirectToRoute('app_rendezvous_index', [], Response::HTTP_SEE_OTHER);
    }
}