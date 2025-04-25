<?php

namespace App\Controller\GestionMeubles;

use App\Entity\GestionMeubles\LignePanier;
use App\Entity\GestionMeubles\Meuble;
use App\Entity\GestionMeubles\Panier;
use App\Entity\Utilisateur;
use App\Form\MeubleType;
use App\Repository\GestionMeubles\LignePanierRepository;
use App\Repository\GestionMeubles\MeubleRepository;
use App\Repository\GestionMeubles\PanierRepository;
use App\Repository\UtilisateurRepository;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/meubles')]
final class MeubleController extends AbstractController
{
    private MeubleRepository $meubleRepository;
    private ValidatorInterface $validator;
    private PanierRepository $panierRepository;
    private LignePanierRepository $lignePanierRepository;
    private UtilisateurRepository $utilisateurRepository;

    public function __construct(
        MeubleRepository $meubleRepository,
        PanierRepository $panierRepository,
        ValidatorInterface $validator,
        LignePanierRepository $lignePanierRepository,
        UtilisateurRepository $utilisateurRepository
    ) {
        $this->meubleRepository = $meubleRepository;
        $this->panierRepository = $panierRepository;
        $this->validator = $validator;
        $this->lignePanierRepository = $lignePanierRepository;
        $this->utilisateurRepository = $utilisateurRepository;
    }

    #[Route('/admin', name: 'app_gestion_meubles_meuble_admin')]
    public function index(Request $request, PaginatorInterface $paginator): Response
    {    
            $this->denyAccessUnlessGranted('ROLE_ADMIN');

        // Récupérer tous les meubles avec leurs vendeurs associés
        $queryBuilder = $this->meubleRepository->createQueryBuilder('m')
            ->leftJoin('m.vendeur', 'v')
            ->addSelect('v');

        // Paginer les résultats
        $pagination = $paginator->paginate(
            $queryBuilder, // Requête à paginer
            $request->query->getInt('page', 1), // Numéro de page, par défaut 1
            10 // Nombre d'éléments par page
        );

        return $this->render('gestion_meubles/meuble/liste_admin.html.twig', [
            'controller_name' => 'GestionMeubles/MeubleController',
            'pagination' => $pagination,
        ]);
    }

    #[Route('/ajouter', name: 'app_gestion_meuble_ajouter', methods: ['GET', 'POST'])]
    public function ajouter(Request $request): Response
    {         
        $this->denyAccessUnlessGranted('ROLE_ETUDIANT');
        $meuble = new Meuble();
        $meuble->setDateEnregistrement(new \DateTime());
        $meuble->setStatut('disponible');
        $meuble->setCategorie('occasion');

        // Récupérer l'utilisateur connecté au lieu d'un CIN hardcoded
        $utilisateur = $this->getUser();
        if (!$utilisateur instanceof Utilisateur) {
            throw $this->createAccessDeniedException('Vous devez être connecté pour ajouter un meuble.');
        }
        $meuble->setVendeur($utilisateur);

        $form = $this->createForm(MeubleType::class, $meuble);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $imageFile = $form->get('image')->getData();

                if (!$imageFile) {
                    $this->addFlash('error', 'Veuillez sélectionner une image');
                    return $this->redirectToRoute('app_gestion_meuble_ajouter');
                }

                $constraint = new File([
                    'maxSize' => '5M',
                    'mimeTypes' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
                    'mimeTypesMessage' => 'Veuillez uploader une image valide (JPEG, PNG, GIF, WEBP).',
                ]);

                $violations = $this->validator->validate($imageFile, $constraint);

                if (count($violations) > 0) {
                    foreach ($violations as $violation) {
                        $this->addFlash('error', $violation->getMessage());
                    }
                    return $this->redirectToRoute('app_gestion_meuble_ajouter');
                }

                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $newFilename = $originalFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();

                try {
                    $imageFile->move(
                        $this->getParameter('images_directory'),
                        $newFilename
                    );
                    $meuble->setImage($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Erreur lors de l\'upload de l\'image : ' . $e->getMessage());
                    return $this->redirectToRoute('app_gestion_meuble_ajouter');
                }

                $this->meubleRepository->save($meuble);
                $this->addFlash('success', 'Meuble ajouté avec succès !');
                return $this->redirectToRoute('app_gestion_meubles_mes_meubles');
            } else {
                $errors = $form->getErrors(true);
                foreach ($errors as $error) {
                    $this->addFlash('error', $error->getMessage());
                }
            }
        }

        return $this->render('gestion_meubles/meuble/form.html.twig', [
            'form' => $form->createView(),
            'meuble' => $meuble,
            'is_edit' => false,
        ]);
    }
    #[Route('/{id}/modifier', name: 'app_gestion_meuble_modifier', methods: ['GET', 'POST'])]
    public function modifier(Request $request, Meuble $meuble): Response
    {
        $utilisateur = $this->getUser();
    if (!$utilisateur instanceof Utilisateur || $meuble->getVendeur() !== $utilisateur) {
        throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à modifier ce meuble.');
    }

    if ($meuble->getStatut() === 'indisponible') {
        $this->addFlash('error', 'Vous ne pouvez pas modifier un meuble déjà vendu.');
        return $this->redirectToRoute('app_gestion_meubles_mes_meubles');
    }

    $oldImage = $meuble->getImage();
    $form = $this->createForm(MeubleType::class, $meuble, ['is_edit' => true]);
    $form->handleRequest($request);
    
        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $imageFile = $form->get('image')->getData();
    
                if ($imageFile) {
                    $constraint = new File([
                        'maxSize' => '5M',
                        'mimeTypes' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
                        'mimeTypesMessage' => 'Veuillez uploader une image valide (JPEG, PNG, GIF, WEBP).',
                    ]);
    
                    $violations = $this->validator->validate($imageFile, $constraint);
                    if (count($violations) > 0) {
                        foreach ($violations as $violation) {
                            $this->addFlash('error', $violation->getMessage());
                        }
                        return $this->render('gestion_meubles/meuble/form.html.twig', [
                            'form' => $form->createView(),
                            'meuble' => $meuble,
                            'is_edit' => true,
                        ]);
                    }
    
                    $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                    $newFilename = $originalFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();
    
                    try {
                        $imageFile->move(
                            $this->getParameter('images_directory'),
                            $newFilename
                        );
                        // Supprimer l'ancienne image si elle existe
                        if ($oldImage) {
                            $oldImagePath = $this->getParameter('images_directory') . '/' . $oldImage;
                            if (file_exists($oldImagePath)) {
                                unlink($oldImagePath);
                            }
                        }
                        $meuble->setImage($newFilename);
                    } catch (FileException $e) {
                        error_log('File upload error: ' . $e->getMessage());
                        $this->addFlash('error', 'Erreur lors de l\'upload de l\'image : ' . $e->getMessage());
                        return $this->render('gestion_meubles/meuble/form.html.twig', [
                            'form' => $form->createView(),
                            'meuble' => $meuble,
                            'is_edit' => true,
                        ]);
                    }
                }
    
                // Utiliser la méthode edit
                $this->meubleRepository->edit($meuble);
                $this->addFlash('success', 'Meuble modifié avec succès !');
                return $this->redirectToRoute('app_gestion_meubles_mes_meubles');
            } else {
                $errors = $form->getErrors(true);
                foreach ($errors as $error) {
                    $this->addFlash('error', $error->getMessage());
                }
                return $this->render('gestion_meubles/meuble/form.html.twig', [
                    'form' => $form->createView(),
                    'meuble' => $meuble,
                    'is_edit' => true,
                ]);

            }
        }
    
        return $this->render('gestion_meubles/meuble/form.html.twig', [
            'form' => $form->createView(),
            'meuble' => $meuble,
            'is_edit' => true,
        ]);
    }


    #[Route('/mes-meubles', name: 'app_gestion_meubles_mes_meubles')]
    public function consulterMesMeubles(): Response
    {
        $utilisateur = $this->getUser();
        if (!$utilisateur instanceof Utilisateur) {
            throw $this->createAccessDeniedException('Vous devez être connecté pour consulter vos meubles.');
        }

        $meubles = $this->meubleRepository->findBy(['vendeur' => $utilisateur]);

        return $this->render('gestion_meubles/meuble/consulter_mes_meubles.html.twig', [
            'meubles' => $meubles,
            'vendeur' => $utilisateur,
        ]);
    }

    #[Route('/a-acheter', name: 'app_gestion_meubles_a_acheter', methods: ['GET'])]
    public function meublesAVendre(): Response
    {
        $utilisateur = $this->getUser();
        if (!$utilisateur instanceof Utilisateur) {
            throw $this->createAccessDeniedException('Vous devez être connecté pour voir les meubles à acheter.');
        }

        $meubles = $this->meubleRepository->findMeublesDisponiblesPourAcheteur($utilisateur->getCin());

        return $this->render('gestion_meubles/meuble/a_acheter.html.twig', [
            'meubles' => $meubles,
            'vendeur' => $utilisateur,
        ]);
    }

    #[Route('/{id}/supprimer', name: 'app_gestion_meuble_supprimer', methods: ['POST'])]
    public function supprimer(Request $request, Meuble $meuble): Response
    {
        $utilisateur = $this->getUser();
        if (!$utilisateur instanceof Utilisateur || $meuble->getVendeur() !== $utilisateur) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à supprimer ce meuble.');
        }

        if ($meuble->getStatut() === 'indisponible') {
            $this->addFlash('error', 'Vous ne pouvez pas supprimer un meuble déjà vendu.');
            return $this->redirectToRoute('app_gestion_meubles_mes_meubles');
        }

        if ($this->isCsrfTokenValid('delete' . $meuble->getId(), $request->request->get('_token'))) {
            $this->meubleRepository->delete($meuble);
            $this->addFlash('success', 'Meuble supprimé avec succès !');
        } else {
            $this->addFlash('error', 'Token CSRF invalide.');
        }

        return $this->redirectToRoute('app_gestion_meubles_mes_meubles');
    }
    #[Route('/ajouter-au-panier/{id}', name: 'app_gestion_meubles_ajouter_panier', methods: ['POST'])]
    public function ajouterAuPanier(Request $request, int $id): JsonResponse
    {
        try {
            if (!$this->isCsrfTokenValid('add_to_cart_' . $id, $request->request->get('_token'))) {
                throw new \Exception('Token CSRF invalide');
            }
    
            $utilisateur = $this->getUser();
            if (!$utilisateur instanceof Utilisateur) {
                throw new \Exception('Vous devez être connecté pour ajouter au panier.');
            }
    
            $meuble = $this->meubleRepository->find($id);
            if (!$meuble) {
                throw new \Exception('Meuble non trouvé');
            }
    
            if ($meuble->getStatut() !== 'disponible') {
                throw new \Exception('Ce meuble n\'est plus disponible');
            }
    
            // Vérifier s'il existe un panier EN_COURS
            $panier = $this->panierRepository->findPanierEnCours($utilisateur);
    
            // Si un panier existe, vérifier son statut
            if ($panier && $panier->getStatut() !== Panier::STATUT_EN_COURS) {
                // Ne pas utiliser un panier VALIDE ou ANNULE
                $panier = null;
            }
    
            // Créer un nouveau panier si aucun panier EN_COURS n'existe
            if (!$panier) {
                $panier = new Panier();
                $panier->setAcheteur($utilisateur); // Utilise la relation pour synchroniser cinAcheteur
                $panier->setStatut(Panier::STATUT_EN_COURS);
                $panier->setDateAjout(new \DateTime());
                $this->panierRepository->save($panier, true);
            }
    
            // Vérifier si le meuble est déjà dans le panier
            if ($this->lignePanierRepository->verifierProduitDansPanier($panier->getId(), $meuble->getId())) {
                return $this->json([
                    'warning' => 'Ce meuble est déjà dans votre panier.'
                ], Response::HTTP_CONFLICT);
            }
    
            // Ajouter le meuble au panier
            $lignePanier = new LignePanier();
            $lignePanier->setPanier($panier);
            $lignePanier->setMeuble($meuble);
            $this->lignePanierRepository->save($lignePanier, true);
    
            return $this->json([
                'message' => 'Le meuble a été ajouté à votre panier.',
                'panier_id' => $panier->getId(),
                'meuble_nom' => $meuble->getNom(),
                'redirect' => $this->generateUrl('app_gestion_meubles_a_acheter')
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return $this->json([
                'error' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }
    // #[Route('/ajouter-au-panier/{id}', name: 'app_gestion_meubles_ajouter_panier', methods: ['POST'])]
    // public function ajouterAuPanier(Request $request, int $id): JsonResponse
    // {
    //     try {
    //         if (!$this->isCsrfTokenValid('add_to_cart_' . $id, $request->request->get('_token'))) {
    //             throw new \Exception('Token CSRF invalide');
    //         }

    //         $utilisateur = $this->getUser();
    //         if (!$utilisateur instanceof Utilisateur) {
    //             throw new \Exception('Vous devez être connecté pour ajouter au panier.');
    //         }

    //         $meuble = $this->meubleRepository->find($id);
    //         if (!$meuble) {
    //             throw new \Exception('Meuble non trouvé');
    //         }

    //         if ($meuble->getStatut() !== 'disponible') {
    //             throw new \Exception('Ce meuble n\'est plus disponible');
    //         }

    //         $panier = $this->panierRepository->findPanierEnCours($utilisateur);
    //         if (!$panier) {
    //             $panier = new Panier();
    //             $panier->setCinAcheteur($utilisateur->getCin());
    //             $panier->setStatut(Panier::STATUT_EN_COURS);
    //             $panier->setDateAjout(new \DateTime());
    //             $this->panierRepository->save($panier, true);
    //         }

    //         if ($this->lignePanierRepository->verifierProduitDansPanier($panier->getId(), $meuble->getId())) {
    //             return $this->json([
    //                 'warning' => 'Ce meuble est déjà dans votre panier.'
    //             ], Response::HTTP_CONFLICT);
    //         }

    //         $lignePanier = new LignePanier();
    //         $lignePanier->setPanier($panier);
    //         $lignePanier->setMeuble($meuble);
    //         $this->lignePanierRepository->save($lignePanier, true);

    //         return $this->json([
    //             'message' => 'Le meuble a été ajouté à votre panier.',
    //             'panier_id' => $panier->getId(),
    //             'meuble_nom' => $meuble->getNom(),
    //             'redirect' => $this->generateUrl('app_gestion_meubles_a_acheter')
    //         ], Response::HTTP_CREATED);
    //     } catch (\Exception $e) {
    //         return $this->json([
    //             'error' => $e->getMessage()
    //         ], Response::HTTP_BAD_REQUEST);
    //     }
    // }
    #[Route('/statistiques/vendeur', name: 'app_gestion_meubles_statistiques_etudiant')]
    public function statistiques(Request $request): Response
    {
        $utilisateur = $this->getUser();
        if (!$utilisateur instanceof Utilisateur) {
            throw $this->createAccessDeniedException('Vous devez être connecté pour voir vos statistiques.');
        }
    
        $cinVendeur = $utilisateur->getCin();
    
        // Existing KPI data
        $meublesIndisponibles = $this->meubleRepository->countMeublesIndisponibles($cinVendeur);
        $meublesDisponibles = $this->meubleRepository->countMeublesDisponibles($cinVendeur);
        $totalMeubles = $this->meubleRepository->countTotalMeubles($cinVendeur);
        $commandesPayees = $this->meubleRepository->countCommandesPayees($cinVendeur);
        $commandesEnAttente = $this->meubleRepository->countCommandesEnAttente($cinVendeur);
        $commandesLivrees = $this->meubleRepository->countCommandesLivrees($cinVendeur);
        $commandesAnnulees = $this->meubleRepository->countCommandesAnnulees($cinVendeur);
        $tauxCommandesAnnulees = $this->meubleRepository->getTauxCommandesAnnulees($cinVendeur);
        $revenuTotal = $this->meubleRepository->getRevenuTotal($cinVendeur);
        $tauxRetourClients = $this->meubleRepository->getTauxRetourClients($cinVendeur);
        $meublesAjoutesRecemment = $this->meubleRepository->countMeublesAjoutesRecemment($cinVendeur);
    
        // Sample chart data (replace with actual queries)
        $monthlyRevenue = [
            'labels' => ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin'],
            'data' => [1200, 1900, 3000, 2500, 4000, 3500]
        ];
    
        $furnitureAdded = [
            'labels' => ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin'],
            'data' => [10, 15, 8, 12, 20, 18]
        ];
    
        return $this->render('gestion_meubles/meuble/statistiques-current-etudiant.html.twig', [
            'meublesIndisponibles' => $meublesIndisponibles,
            'meublesDisponibles' => $meublesDisponibles,
            'totalMeubles' => $totalMeubles,
            'commandesPayees' => $commandesPayees,
            'commandesEnAttente' => $commandesEnAttente,
            'commandesLivrees' => $commandesLivrees,
            'commandesAnnulees' => $commandesAnnulees,
            'tauxCommandesAnnulees' => $tauxCommandesAnnulees,
            'revenuTotal' => $revenuTotal,
            'tauxRetourClients' => $tauxRetourClients,
            'meublesAjoutesRecemment' => $meublesAjoutesRecemment,
            'monthlyRevenue' => $monthlyRevenue,
            'furnitureAdded' => $furnitureAdded,
        ]);
    }
}