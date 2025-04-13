<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Entity\Logement;
use App\Form\LogementType;
use App\Repository\LogementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\SecurityBundle\Security;

#[Route('/logement')]
final class LogementController extends AbstractController
{
    #[Route('/', name: 'app_logement_index', methods: ['GET'])]
    public function index(LogementRepository $logementRepository): Response
    {
        return $this->render('logement/index.html.twig', [
            'logements' => $logementRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_logement_new', methods: ['GET', 'POST'])]
   // src/Controller/LogementController.php

public function new(Request $request, EntityManagerInterface $entityManager, Security $security): Response
{
    $logement = new Logement();
    $form = $this->createForm(LogementType::class, $logement);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        // Récupérer l'adresse du formulaire (champ non mappé)
        $address = $form->get('address')->getData();
        $logement->setAdresse($address); // Assigner à la propriété 'adresse'

        // Associer l'utilisateur
        $user = $security->getUser();
        if ($user) {
            $logement->setUtilisateurCin($user);
        }

        $entityManager->persist($logement);
        $entityManager->flush();

        return $this->redirectToRoute('app_logement_index');
    }

    return $this->render('logement/new.html.twig', [
        'form' => $form->createView(),
    ]);
}

    #[Route('/{id}', name: 'app_logement_show', methods: ['GET'])]
    public function show(Logement $logement): Response
    {
        return $this->render('logement/show.html.twig', [
            'logement' => $logement,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_logement_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Logement $logement, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(LogementType::class, $logement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_logement_index');
        }

        return $this->render('logement/edit.html.twig', [
            'logement' => $logement,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_logement_delete', methods: ['POST'])]
    public function delete(Request $request, Logement $logement, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$logement->getId(), $request->request->get('_token'))) {
            $entityManager->remove($logement);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_logement_index');
    }
}