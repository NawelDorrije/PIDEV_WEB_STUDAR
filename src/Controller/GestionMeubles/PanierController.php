<?php

namespace App\Controller\GestionMeubles;

use App\Entity\GestionMeubles\Panier;
use App\Entity\Utilisateur;
use App\Repository\GestionMeubles\LignePanierRepository;
use App\Repository\GestionMeubles\PanierRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/meubles/panier')]
final class PanierController extends AbstractController
{
    private PanierRepository $panierRepository;
    private LignePanierRepository $lignePanierRepository;

    public function __construct(
        PanierRepository $panierRepository,
        LignePanierRepository $lignePanierRepository
    ) {
        $this->panierRepository = $panierRepository;
        $this->lignePanierRepository = $lignePanierRepository;
    }

    #[Route('/', name: 'app_gestion_meubles_panier', methods: ['GET'])]
    public function index(): Response
    {
        $paniers = $this->panierRepository->findAll();
        return $this->render('gestion_meubles/panier/index.html.twig', [
            'controller_name' => 'GestionMeubles/PanierController',
            'paniers' => $paniers,
        ]);
    }

    #[Route('/create', name: 'app_gestion_meubles_panier_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        try {
            $utilisateur = $this->getUser();
            if (!$utilisateur instanceof Utilisateur) {
                throw new \InvalidArgumentException('Vous devez être connecté pour créer un panier');
            }
    
            $panier = new Panier();
            $panier->setAcheteur($utilisateur); // Utilise la relation
            $this->panierRepository->save($panier, true);
    
            return $this->json([
                'message' => 'Panier créé avec succès',
                'id' => $panier->getId()
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return $this->json([
                'error' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }
    #[Route('/lignes', name: 'app_gestion_meubles_lignes_panier', methods: ['GET'])]
    public function voirLignesPanier(): Response
    {
        $utilisateur = $this->getUser();

        $cinAcheteur = "14450157"; // À remplacer par $this->getUser()->getCin() pour récupérer l'utilisateur connecté
        $panier = $this->panierRepository->findPanierEnCours($utilisateur);
    
        $cartCount = 0; // Initialisation du compteur
        if ($panier) {
            $lignesPanier = $this->lignePanierRepository->findByPanierId($panier->getId());
            $cartCount = count($lignesPanier); // Nombre total de lignes dans le panier
            $total = $this->panierRepository->calculerSommePanier($panier->getId());
        } else {
            $lignesPanier = [];
            $total = 0.0;
        }
    
        return $this->render('gestion_meubles/panier/voir_lignes.html.twig', [
            'lignesPanier' => $lignesPanier,
            'cin_acheteur' => $cinAcheteur,
            'total' => $total,
            'cartCount' => $cartCount, // Passage du nombre de produits au template
        ]);
    }

    #[Route('/lignes/{id}/remove', name: 'app_gestion_meubles_ligne_panier_remove', methods: ['POST'])]
    public function removeLigne(int $id): Response
    {
        $ligne = $this->lignePanierRepository->find($id);
        if ($ligne) {
            $this->lignePanierRepository->remove($ligne, true);
            $this->addFlash('success', 'Le meuble a été supprimé du panier.');
        } else {
            $this->addFlash('error', 'Ligne de panier non trouvée.');
        }
        return $this->redirectToRoute('app_gestion_meubles_lignes_panier');
    }


    #[Route('/{id}', name: 'app_gestion_meubles_panier_show', methods: ['GET'])]
    public function show(int $id): Response
    {
        $panier = $this->panierRepository->find($id);
        if (!$panier) {
            throw $this->createNotFoundException('Panier non trouvé');
        }
        return $this->render('gestion_meubles/panier/show.html.twig', [
            'panier' => $panier,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_gestion_meubles_panier_edit', methods: ['PUT'])]
    public function edit(Request $request, int $id): JsonResponse
    {
        // Code inchangé
        try {
            $panier = $this->panierRepository->find($id);
            if (!$panier) {
                throw $this->createNotFoundException('Panier non trouvé');
            }

            $data = json_decode($request->getContent(), true);
            if (isset($data['cin_acheteur'])) {
                $panier->setCinAcheteur($data['cin_acheteur']);
            }
            if (isset($data['statut'])) {
                $panier->setStatut($data['statut']);
            }
            if (isset($data['date_validation'])) {
                $panier->setDateValidation(new \DateTime($data['date_validation']));
            }
            if (isset($data['date_annulation'])) {
                $panier->setDateAnnulation(new \DateTime($data['date_annulation']));
            }

            $this->panierRepository->update($panier, true);

            return $this->json([
                'message' => 'Panier mis à jour avec succès'
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'error' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}', name: 'app_gestion_meubles_panier_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        // Code inchangé
        try {
            $panier = $this->panierRepository->find($id);
            if (!$panier) {
                throw $this->createNotFoundException('Panier non trouvé');
            }

            $this->panierRepository->remove($panier, true);

            return $this->json([
                'message' => 'Panier supprimé avec succès'
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'error' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/en-cours/{cin}', name: 'app_gestion_meubles_panier_en_cours', methods: ['GET'])]
    public function findPanierEnCours(string $cin): JsonResponse
    {
        // Code inchangé
        $utilisateur = $this->getUser();

        $panier = $this->panierRepository->findPanierEnCours($utilisateur);
        if (!$panier) {
            return $this->json([
                'message' => 'Aucun panier en cours trouvé'
            ], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'id' => $panier->getId(),
            'cin_acheteur' => $panier->getCinAcheteur(),
            'date_ajout' => $panier->getDateAjout()->format('Y-m-d H:i:s'),
            'statut' => $panier->getStatut(),
        ]);
    }
}