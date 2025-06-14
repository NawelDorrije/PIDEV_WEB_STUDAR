<?php

namespace App\Controller\GestionTransport;

use App\Entity\GestionTransport\Voiture;
use App\Form\GestionTransport\VoitureType;
use App\Repository\GestionTransport\VoitureRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/voiture')]
final class VoitureController extends AbstractController
{
    #[Route(name: 'app_voiture_index', methods: ['GET'])]
    public function index(VoitureRepository $voitureRepository): Response
    {
        return $this->render('GestionTransport/voiture/index.html.twig', [
            'voitures' => $voitureRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_voiture_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $voiture = new Voiture();
        $form = $this->createForm(VoitureType::class, $voiture);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Default photo handled in Voiture entity constructor
            $entityManager->persist($voiture);
            $entityManager->flush();

            return $this->redirectToRoute('app_voiture_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('GestionTransport/voiture/new.html.twig', [
            'voiture' => $voiture,
            'form' => $form,
        ]);
    }

    #[Route('/{idVoiture}', name: 'app_voiture_show', methods: ['GET'])]
    // In your show/edit/list methods, replace any reference to 'photo' with 'image'
    public function show(Voiture $voiture): Response
    {
        return $this->render('GestionTransport/voiture/show.html.twig', [
            'voiture' => $voiture,
            'image_path' => $voiture->getImage()
        ]);
    }

    #[Route('/{idVoiture}/edit', name: 'app_voiture_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Voiture $voiture, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(VoitureType::class, $voiture);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            return $this->redirectToRoute('app_voiture_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('GestionTransport/voiture/edit.html.twig', [
            'voiture' => $voiture,
            'form' => $form,
        ]);
    }

    #[Route('/{idVoiture}', name: 'app_voiture_delete', methods: ['POST'])]
    public function delete(Request $request, Voiture $voiture, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$voiture->getIdVoiture(), $request->getPayload()->getString('_token'))) {
            try {
                $entityManager->remove($voiture);
                $entityManager->flush();
            } catch (\Exception $e) {
                $this->addFlash('error', 'Deletion failed: '.$e->getMessage());
            }
        }

        return $this->redirectToRoute('app_voiture_index', [], Response::HTTP_SEE_OTHER);
    }
}