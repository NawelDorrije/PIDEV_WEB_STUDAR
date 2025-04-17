<?php

namespace App\Controller\GestionTransport;

use App\Entity\GestionTransport\Voiture;
use App\Entity\Utilisateur;
use App\Form\GestionTransport\VoitureType;
use App\Repository\GestionTransport\VoitureRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_TRANSPORTEUR')]
#[Route('/voiture')]
final class VoitureController extends AbstractController
{
    #[Route(name: 'app_voiture_index', methods: ['GET'])]
    public function index(Request $request, VoitureRepository $voitureRepository): Response
    {
        $disponibilite = $request->query->get('disponibilite');
        $user = $this->getUser();
        
        $voitures = $disponibilite
            ? $voitureRepository->findByAvailabilityAndUser($disponibilite, $user)
            : $voitureRepository->findByUser($user);

        return $this->render('GestionTransport/voiture/index.html.twig', [
            'voitures' => $voitures,
            'current_filter' => $disponibilite
        ]);
    }

    #[Route('/new', name: 'app_voiture_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        
        if (!$user instanceof Utilisateur) {
            throw $this->createAccessDeniedException('Vous devez être connecté pour accéder à cette page');
        }
    
        $voiture = new Voiture();
        $voiture->setUtilisateur($user); // Set the user here
    
        $form = $this->createForm(VoitureType::class, $voiture);
        $form->handleRequest($request);
    
        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($voiture);
            $entityManager->flush();
    
            return $this->redirectToRoute('app_voiture_index', [], Response::HTTP_SEE_OTHER);
        }
    
        return $this->render('GestionTransport/voiture/new.html.twig', [
            'voiture' => $voiture,
            'form' => $form->createView(),
            'current_user' => $user // Pass user to template
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