<?php

namespace App\Controller;

use App\Entity\ImageLogement;
use App\Form\ImageLogementType;
use App\Repository\ImageLogementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\Id;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/image/logement')]
final class ImageLogementAdmineController extends AbstractController
{
    #[Route(name: 'app_image_logement_index', methods: ['GET'])]
    public function index(ImageLogementRepository $imageLogementRepository): Response
    {
        return $this->render('Admin/image_logement/index.html.twig', [
            'image_logements' => $imageLogementRepository->findAll(),
        ]);
    }
    #[Route('/logement/{id}', name: 'app_image_logement_by_logement', methods: ['GET'])]
    public function showImageLogement(int $id, ImageLogementRepository $imageLogementRepository): Response
    {
        $imageLogements = $imageLogementRepository->findBy(['logement' => $id]);
        return $this->render('Admin/image_logement/index.html.twig', [
            'image_logements' => $imageLogements,
        ]);
    }
    #[Route('/new', name: 'app_image_logement_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $imageLogement = new ImageLogement();
        $form = $this->createForm(ImageLogementType::class, $imageLogement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($imageLogement);
            $entityManager->flush();

            return $this->redirectToRoute('app_image_logement_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('Admin/image_logement/new.html.twig', [
            'image_logements' => $imageLogement,
            'form' => $form,
        ]);
    }

    #[Route('/images/{id}', name: 'app_image_logement_show', methods: ['GET'])]
    public function show(ImageLogement $imageLogement): Response
    {
        return $this->render('Admin/image_logement/show.html.twig', [
            'image_logement' => $imageLogement,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_image_logement_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, ImageLogement $imageLogement, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ImageLogementType::class, $imageLogement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_image_logement_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('Admin/image_logement/edit.html.twig', [
            'image_logement' => $imageLogement,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_image_logement_delete', methods: ['POST'])]
    public function delete(Request $request, ImageLogement $imageLogement, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$imageLogement->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($imageLogement);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_image_logement_index', [], Response::HTTP_SEE_OTHER);
    }
}
