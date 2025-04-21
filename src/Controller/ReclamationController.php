<?php

namespace App\Controller;

use App\Entity\Reclamation;
use App\Entity\Logement;
use App\Form\ReclamationType;
use App\Repository\ReclamationRepository;
use App\Repository\LogementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\UtilisateurRepository;
use App\Entity\Utilisateur;

#[Route('/reclamation')]
class ReclamationController extends AbstractController
{
    #[Route('/new', name: 'app_reclamation_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, LogementRepository $logementRepository): Response
    {
        $reclamation = new Reclamation();
        $reclamation->setTimestamp(new \DateTime());

        $logementId = $request->query->get('logement_id');
        if ($logementId) {
            $logement = $logementRepository->find($logementId);
            if ($logement) {
                $reclamation->setLogement($logement);
            } else {
                if ($request->isXmlHttpRequest()) {
                    return new JsonResponse(['success' => false, 'error' => 'Logement non trouvé.'], 404);
                }
                $this->addFlash('error', 'Logement non trouvé.');
            }
        }

        $form = $this->createForm(ReclamationType::class, $reclamation);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $entityManager->persist($reclamation);
                $entityManager->flush();

                if ($request->isXmlHttpRequest()) {
                    return new JsonResponse([
                        'success' => true,
                        'message' => 'Réclamation créée avec succès.',
                    ]);
                }

                $this->addFlash('success', 'Réclamation créée avec succès.');
                return $this->redirectToRoute('app_logement_show', ['id' => $reclamation->getLogement()->getId()], Response::HTTP_SEE_OTHER);
            } elseif ($request->isXmlHttpRequest()) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Erreur dans le formulaire. Veuillez vérifier les champs.',
                ], 400);
            }
        }

        if ($request->isXmlHttpRequest() && $request->isMethod('GET')) {
            return $this->render('reclamation/_form.html.twig', [
                'form' => $form->createView(),
            ]);
        }

        return $this->render('reclamation/new.html.twig', [
            'reclamation' => $reclamation,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/add/{logementId}', name: 'app_reclamation_add', methods: ['POST'])]
    public function addReclamation(Request $request, int $logementId, EntityManagerInterface $entityManager): JsonResponse
    {
        $submittedToken = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('add_reclamation', $submittedToken)) {
            return new JsonResponse(['error' => 'Invalid CSRF token.'], 400);
        }
    
        $title = $request->request->get('title');
        $description = $request->request->get('description');
    
        if (empty($title) || empty($description)) {
            return new JsonResponse(['error' => 'Le titre et la description sont requis.'], 400);
        }
    
        $logement = $entityManager->getRepository(Logement::class)->find($logementId);
        if (!$logement) {
            return new JsonResponse(['error' => 'Logement non trouvé.'], 404);
        }
    
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'Utilisateur non connecté.'], 401);
        }
    
        // Ensure the user has a valid CIN
        $cin = $user->getCin();
        if (empty($cin)) {
            return new JsonResponse(['error' => 'L\'utilisateur n\'a pas de CIN valide.'], 400);
        }
    
        $reclamation = new Reclamation();
        $reclamation->setTitre($title);
        $reclamation->setDescription($description);
        $reclamation->setLogement($logement);
        $reclamation->setTimestamp(new \DateTime());
        $reclamation->setUtilisateur($user);
        $reclamation->setCin($cin);
        $reclamation->setStatut('en cours'); // Explicitly set the status
    
        try {
            $entityManager->persist($reclamation);
            $entityManager->flush();
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Erreur lors de l\'enregistrement de la réclamation : ' . $e->getMessage()], 500);
        }
    
        return new JsonResponse(['success' => 'Réclamation ajoutée avec succès !']);
    }
    #[Route('/modify/{id}', name: 'app_reclamation_modify', methods: ['POST'])]
    public function modifyReclamation(Request $request, int $id, EntityManagerInterface $entityManager): JsonResponse
    {
        $submittedToken = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('modify_reclamation', $submittedToken)) {
            return new JsonResponse(['error' => 'Invalid CSRF token.'], 400);
        }

        $reclamation = $entityManager->getRepository(Reclamation::class)->find($id);
        if (!$reclamation) {
            return new JsonResponse(['error' => 'Réclamation non trouvée.'], 404);
        }

        $user = $this->getUser();
        if (!$user || $user !== $reclamation->getUtilisateur()) {
            return new JsonResponse(['error' => 'Vous n\'êtes pas autorisé à modifier cette réclamation.'], 403);
        }

        // Check 24-hour limit
        $now = new \DateTime();
        $interval = $now->diff($reclamation->getTimestamp());
        $hours = $interval->h + ($interval->days * 24);
        if ($hours > 24) {
            return new JsonResponse(['error' => 'Vous ne pouvez pas modifier une réclamation après 24 heures.'], 403);
        }

        $title = $request->request->get('title');
        $description = $request->request->get('description');
        $statut = $request->request->get('statut');

        if (empty($title) || empty($description) || empty($statut)) {
            return new JsonResponse(['error' => 'Le titre, la description et le statut sont requis.'], 400);
        }
    
        $reclamation->setTitre($title);
        $reclamation->setDescription($description);
        $reclamation->setStatut('en cours'); // Explicitly set the status
        
        try {
            $entityManager->flush();
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Erreur lors de la modification de la réclamation : ' . $e->getMessage()], 500);
        }
    
        return new JsonResponse(['success' => 'Réclamation modifiée avec succès !']);
    }

    #[Route('/delete/{id}', name: 'app_reclamation_delete', methods: ['POST'])]
    public function deleteReclamation(Request $request, int $id, EntityManagerInterface $entityManager): JsonResponse
    {
        $submittedToken = $request->headers->get('X-CSRF-Token');
        if (!$this->isCsrfTokenValid('delete_reclamation', $submittedToken)) {
            return new JsonResponse(['error' => 'Invalid CSRF token.'], 400);
        }

        $reclamation = $entityManager->getRepository(Reclamation::class)->find($id);
        if (!$reclamation) {
            return new JsonResponse(['error' => 'Réclamation non trouvée.'], 404);
        }

        $user = $this->getUser();
        if (!$user || $user !== $reclamation->getUtilisateur()) {
            return new JsonResponse(['error' => 'Vous n\'êtes pas autorisé à supprimer cette réclamation.'], 403);
        }

        $entityManager->remove($reclamation);
        $entityManager->flush();

        return new JsonResponse(['success' => 'Réclamation supprimée avec succès !']);
    }
    #[Route('/admin/reclamation', name: 'admin_reclamation', methods: ['GET'])]
public function index(Request $request, ReclamationRepository $reclamationRepository, EntityManagerInterface $entityManager): Response
{
    $page = $request->query->getInt('page', 1);
    $limit = 10; // Nombre d'éléments par page
    $sortBy = $request->query->get('sort_by', 'timestamp');
    $sortOrder = $request->query->get('sort_order', 'desc');
    $userFilter = $request->query->get('user_filter', '');
    $dateFilter = $request->query->get('date_filter', '');

    // Compter le nombre total pour la pagination
    $countQuery = $reclamationRepository->createQueryBuilder('r');
    if ($userFilter) {
        $countQuery->andWhere('r.cin = :cin')
                   ->setParameter('cin', $userFilter);
    }
    if ($dateFilter) {
        $countQuery->andWhere('r.timestamp LIKE :date')
                   ->setParameter('date', $dateFilter . '%');
    }
    $totalReclamations = $countQuery->select('COUNT(r.id)')
                                    ->getQuery()
                                    ->getSingleScalarResult();
    $totalPages = ceil($totalReclamations / $limit);

    // Récupérer les réclamations paginées
    $queryBuilder = $reclamationRepository->createQueryBuilder('r');
    if ($userFilter) {
        $queryBuilder->andWhere('r.cin = :cin')
                     ->setParameter('cin', $userFilter);
    }
    if ($dateFilter) {
        $queryBuilder->andWhere('r.timestamp LIKE :date')
                     ->setParameter('date', $dateFilter . '%');
    }
    $queryBuilder->orderBy("r.$sortBy", $sortOrder)
                 ->setFirstResult(($page - 1) * $limit)
                 ->setMaxResults($limit);

    $reclamations = $queryBuilder->getQuery()->getResult();

    // Récupérer les utilisateurs pour le filtrage
    $utilisateurRepository = $entityManager->getRepository(utilisateur::class);
    $usersQuery = $utilisateurRepository->createQueryBuilder('u')
        ->select('u')
        ->distinct()
        ->innerJoin('App\Entity\Reclamation', 'r', 'WITH', 'r.cin = u.cin')
        ->getQuery();

    $users = $usersQuery->getResult();

    return $this->render('admin/reclamation/index.html.twig', [
        'reclamations' => $reclamations,
        'current_page' => $page,
        'total_pages' => $totalPages,
        'sort_by' => $sortBy,
        'sort_order' => $sortOrder,
        'selected_user' => $userFilter,
        'selected_date' => $dateFilter,
        'users' => $users,
    ]);
}

    #[Route('/admin/reclamation/{id}', name: 'admin_reclamation_show', methods: ['GET'])]
    public function show(Reclamation $reclamation): Response
    {
        return $this->render('admin/reclamation/show.html.twig', [
            'reclamation' => $reclamation,
        ]);
    }

    #[Route('/admin/reclamation/{id}/edit', name: 'admin_reclamation_edit_recommend', methods: ['GET', 'POST'])]
    public function edit(Request $request, Reclamation $reclamation, EntityManagerInterface $entityManager): Response
    {
        if ($request->isMethod('POST')) {
            $submittedToken = $request->request->get('_token');
            if (!$this->isCsrfTokenValid('edit_reclamation', $submittedToken)) {
                $this->addFlash('error', 'Invalid CSRF token.');
                return $this->redirectToRoute('admin_reclamation');
            }

            $statut = $request->request->get('statut');
            if (!in_array($statut, ['en cours', 'traité', 'refusé'])) {
                $this->addFlash('error', 'Statut invalide.');
                return $this->redirectToRoute('admin_reclamation');
            }

            $reclamation->setStatut($statut);
            $entityManager->flush();

            $this->addFlash('success', 'Statut de la réclamation mis à jour avec succès.');
            return $this->redirectToRoute('admin_reclamation');
        }

        return $this->render('admin/reclamation/edit.html.twig', [
            'reclamation' => $reclamation,
        ]);
    }

    #[Route('/admin/reclamation/{id}', name: 'admin_reclamation_delete', methods: ['POST'])]
    public function delete(Request $request, Reclamation $reclamation, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $reclamation->getId(), $request->request->get('_token'))) {
            $entityManager->remove($reclamation);
            $entityManager->flush();
            $this->addFlash('success', 'Réclamation supprimée avec succès.');
        } else {
            $this->addFlash('error', 'Invalid CSRF token.');
        }

        return $this->redirectToRoute('admin_reclamation');
    }
    #[Route('/admin/reclamations', name: 'admin_reclamations', methods: ['GET'])]
public function indexSimple(ReclamationRepository $reclamationRepository): Response
{
    $reclamations = $reclamationRepository->findAll();

    return $this->render('admin/reclamation/simple_index.html.twig', [
        'reclamations' => $reclamations,
    ]);
}
}