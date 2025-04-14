<?php

namespace App\Controller;

use App\Entity\Options;
use App\Form\OptionsType;
use App\Repository\OptionsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/options')]
final class OptionsController extends AbstractController
{
    #[Route(name: 'app_options_index', methods: ['GET'])]
    public function index(OptionsRepository $optionsRepository): Response
    {
        return $this->render('options/index.html.twig', [
            'options' => $optionsRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_options_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $option = new Options();
        $form = $this->createForm(OptionsType::class, $option);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($option);
            $entityManager->flush();

            return $this->redirectToRoute('app_options_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('options/new.html.twig', [
            'option' => $option,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_options_show', methods: ['GET'])]
    public function show(Options $option): Response
    {
        return $this->render('options/show.html.twig', [
            'option' => $option,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_options_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Options $option, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(OptionsType::class, $option);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_options_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('options/edit.html.twig', [
            'option' => $option,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_options_delete', methods: ['POST'])]
    public function delete(Request $request, Options $option, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$option->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($option);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_options_index', [], Response::HTTP_SEE_OTHER);
    }
}
