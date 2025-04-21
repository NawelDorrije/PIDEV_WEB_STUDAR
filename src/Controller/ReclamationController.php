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

        if (empty($title) || empty($description)) {
            return new JsonResponse(['error' => 'Le titre et la description sont requis.'], 400);
        }

        $reclamation->setTitre($title);
        $reclamation->setDescription($description);

        $entityManager->flush();

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
}