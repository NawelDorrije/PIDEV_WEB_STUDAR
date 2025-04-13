<?php

namespace App\Controller\GestionTransport;

use App\Entity\Voiture;
use App\Form\VoitureType;
use App\Repository\VoitureRepository;
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
            $photo = $form->get('photo')->getData();

            if ($photo) {
                $newFilename = uniqid().'.'.$photo->guessExtension();
                
                $photo->move(
                    $this->getParameter('photos_directory'),
                    $newFilename
                );
                $voiture->setPhoto($newFilename);
            } else {
                $voiture->setPhoto('default-car.jpg');
            }

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
    public function show(Voiture $voiture): Response
    {
        return $this->render('GestionTransport/voiture/show.html.twig', [
            'voiture' => $voiture,
        ]);
    }

    #[Route('/{idVoiture}/edit', name: 'app_voiture_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Voiture $voiture, EntityManagerInterface $entityManager): Response
    {
        $currentPhoto = $voiture->getPhoto();
        $form = $this->createForm(VoitureType::class, $voiture);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $photo = $form->get('photo')->getData();
            
            if ($photo) {
                $newFilename = uniqid().'.'.$photo->guessExtension();
                
                try {
                    $photo->move(
                        $this->getParameter('photos_directory'),
                        $newFilename
                    );
                    $voiture->setPhoto($newFilename);
                    
                    // Delete old photo if it exists and isn't default
                    if ($currentPhoto && $currentPhoto !== 'default-car.jpg') {
                        $this->deletePhotoFile($currentPhoto);
                    }
                } catch (\Exception $e) {
                    $this->addFlash('error', 'File upload failed: '.$e->getMessage());
                    return $this->redirectToRoute('app_voiture_edit', ['idVoiture' => $voiture->getIdVoiture()]);
                }
            }

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
                // Delete photo file if it exists and isn't default
                if ($voiture->getPhoto() && $voiture->getPhoto() !== 'default-car.jpg') {
                    $this->deletePhotoFile($voiture->getPhoto());
                }
                
                $entityManager->remove($voiture);
                $entityManager->flush();
            } catch (\Exception $e) {
                $this->addFlash('error', 'Deletion failed: '.$e->getMessage());
            }
        }

        return $this->redirectToRoute('app_voiture_index', [], Response::HTTP_SEE_OTHER);
    }

    /**
     * Helper method to safely delete photo files
     */
    private function deletePhotoFile(string $filename): void
    {
        $photoPath = $this->getParameter('photos_directory').'/'.basename($filename);
        
        if (file_exists($photoPath)) {
            try {
                unlink($photoPath);
            } catch (\Exception $e) {
                $this->addFlash('warning', 'Could not delete photo file: '.$e->getMessage());
            }
        }
    }
}