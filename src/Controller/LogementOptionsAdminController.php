<?php

namespace App\Controller;

use App\Entity\Logement;
use App\Entity\LogementOptions;
use App\Entity\Options;
use App\Form\LogementOptionsType;
use App\Repository\LogementOptionsRepository;
use App\Repository\LogementRepository;
use App\Repository\OptionsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/Admin/logement/options')]
final class LogementOptionsAdminController extends AbstractController
{
    #[Route('/GetAll', name: 'app_logement_options_index', methods: ['GET'])]
    public function index(LogementOptionsRepository $logementOptionsRepository): Response
    {
        return $this->render('Admin/logement_options/index.html.twig', [
            'logement_options' => $logementOptionsRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_logement_options_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        LogementRepository $logementRepository,
        OptionsRepository $optionsRepository
    ): Response {
        $defaultLogement = $logementRepository->find(1);
        $defaultOption = $optionsRepository->find(1);
        $logementOption = new LogementOptions($defaultLogement, $defaultOption);

        $form = $this->createForm(LogementOptionsType::class, $logementOption);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$logementOption->getLogement() || !$logementOption->getOption()) {
                $this->addFlash('error', 'Both Logement and Option must be selected');
                return $this->redirectToRoute('app_logement_options_new');
            }

            $entityManager->persist($logementOption);
            $entityManager->flush();

            return $this->redirectToRoute('app_logement_options_index');
        }

        return $this->render('Admin/logement_options/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/show/{logement}/{option}', name: 'app_logement_options_show', methods: ['GET'])]
    public function show(LogementOptionsRepository $repo, int $logement, int $option): Response
    {
        $logementOption = $repo->findOneBy([
            'logement' => $logement,
            'option' => $option
        ]);

        if (!$logementOption) {
            throw $this->createNotFoundException('Logement option non trouvé.');
        }

        return $this->render('Admin/logement_options/show.html.twig', [
            'logement_option' => $logementOption,
        ]);
    }

    #[Route('/{logement}/{option}/edit', name: 'app_logement_options_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        LogementOptionsRepository $repo,
        int $logement,
        int $option,
        EntityManagerInterface $entityManager
    ): Response {
        $logementOption = $repo->findOneBy(['logement' => $logement, 'option' => $option]);
        if (!$logementOption) {
            throw $this->createNotFoundException('Logement option not found.');
        }

        $form = $this->createForm(LogementOptionsType::class, $logementOption);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            return $this->redirectToRoute('app_logement_options_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('Admin/logement_options/edit.html.twig', [
            'logement_option' => $logementOption,
            'form' => $form->createView(),
        ]);
    }
    #[Route('/{logement}/{option}', name: 'app_logement_options_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        LogementOptionsRepository $repo,
        int $logement,
        int $option,
        EntityManagerInterface $entityManager
    ): Response {
        $logementOption = $repo->findOneBy(['logement' => $logement, 'option' => $option]);
        if (!$logementOption) {
            throw $this->createNotFoundException('Logement option not found.');
        }

        if ($this->isCsrfTokenValid('delete' . $logementOption->getLogement()->getId() . $logementOption->getOption()->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($logementOption);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_logement_options_index', [], Response::HTTP_SEE_OTHER);
    }
}