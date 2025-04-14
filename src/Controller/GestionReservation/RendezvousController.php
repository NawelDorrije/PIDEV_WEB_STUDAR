<?php

namespace App\Controller\GestionReservation;

use App\Entity\Logement;
use App\Entity\Rendezvous;
use App\Entity\Utilisateur;
use App\Form\RendezvousType;
use App\Repository\RendezvousRepository;
use App\Repository\LogementRepository;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Knp\Component\Pager\PaginatorInterface;


#[Route('/rendezvous')]
final class RendezvousController extends AbstractController
{
    #[Route('/', name: 'app_rendezvous_index', methods: ['GET'])]
    public function index(
        Request $request, 
        RendezvousRepository $rendezvousRepository, 
        LogementRepository $logementRepository,
        PaginatorInterface $paginator
    ): Response
    {
        // Get filter parameters from request
        $status = $request->query->get('status');
        $dateFilter = $request->query->get('date');
        
        // Create query builder
        $queryBuilder = $rendezvousRepository->createQueryBuilder('r')
            ->orderBy('r.date', 'DESC');
        
        // Apply filters
        if ($status) {
            $queryBuilder->andWhere('r.status = :status')
                ->setParameter('status', $status);
        }
        
        if ($dateFilter) {
            $queryBuilder->andWhere('r.date = :date')
                ->setParameter('date', new \DateTime($dateFilter));
        }
        
        // Paginate the query
        $rendezvouses = $paginator->paginate(
            $queryBuilder->getQuery(),
            $request->query->getInt('page', 1),
            10 // Items per page
        );

        return $this->render('rendezvous/index.html.twig', [
            'rendezvouses' => $rendezvouses,
            'current_status' => $status,
            'date_filter' => $dateFilter,
            'logement_repo' => $logementRepository
        ]);
    }
    
    
    #[Route('/new', name: 'app_rendezvous_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $rendezvous = new Rendezvous();
        $form = $this->createForm(RendezvousType::class, $rendezvous);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($rendezvous);
            $entityManager->flush();

            $this->addFlash('success', 'Rendez-vous créé avec succès');
            return $this->redirectToRoute('app_rendezvous_index');
        }

        return $this->render('rendezvous/new.html.twig', [
            'rendezvous' => $rendezvous,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_rendezvous_show', methods: ['GET'])]
public function show(Rendezvous $rendezvou, LogementRepository $logementRepository): Response
{
    return $this->render('rendezvous/show.html.twig', [
        'rendezvou' => $rendezvou,
        'logement_repo' => $logementRepository
    ]);
}

#[Route('/{id}/edit', name: 'app_rendezvous_edit', methods: ['GET', 'POST'])]
public function edit(Request $request, Rendezvous $rendezvou, EntityManagerInterface $entityManager, LogementRepository $logementRepository): Response
{
        if (!$rendezvou->isModifiable()) {
            $this->addFlash('error', 'Les rendez-vous confirmés ou refusés ne peuvent pas être modifiés');
            return $this->redirectToRoute('app_rendezvous_show', ['id' => $rendezvou->getId()]);
        }

        $form = $this->createForm(RendezvousType::class, $rendezvou);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Le rendez-vous a été modifié avec succès');
            return $this->redirectToRoute('app_rendezvous_index', ['id' => $rendezvou->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('rendezvous/edit.html.twig', [
          'rendezvou' => $rendezvou,
          'form' => $form,
          'logement_repo' => $logementRepository
      ]);
    }

    #[Route('/{id}', name: 'app_rendezvous_delete', methods: ['POST'])]
    public function delete(Request $request, Rendezvous $rendezvou, EntityManagerInterface $entityManager): Response
    {
        if (!$rendezvou->isDeletable()) {
            $this->addFlash('error', 'Ce rendez-vous ne peut pas être supprimé');
            return $this->redirectToRoute('app_rendezvous_show', ['id' => $rendezvou->getId()]);
        }

        if ($this->isCsrfTokenValid('delete'.$rendezvou->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($rendezvou);
            $entityManager->flush();
            $this->addFlash('success', 'Le rendez-vous a été supprimé avec succès');
        }

        return $this->redirectToRoute('app_rendezvous_index', [], Response::HTTP_SEE_OTHER);
    }

    // src/Controller/GestionReservation/RendezvousController.php
    #[Route('/get-logements/{proprietaireCin}', name: 'get_logements_by_proprietaire', methods: ['GET'])]
public function getLogementsByProprietaire(
    string $proprietaireCin, 
    LogementRepository $logementRepo
): JsonResponse 
{
    $logements = $logementRepo->findBy(['utilisateur_cin' => $proprietaireCin]);
    
    $data = [];
    foreach ($logements as $logement) {
        $data[] = [
            'id' => $logement->getId(),
            'adresse' => $logement->getAdresse() ?: 'Logement #'.$logement->getId()
        ];
    }

    return new JsonResponse($data);
}
}