<?php

namespace App\Controller\GestionMeubles;

use App\Entity\GestionMeubles\LignePanier;
use App\Entity\GestionMeubles\Meuble;
use App\Entity\GestionMeubles\Panier;
use App\Form\MeubleType;
use App\Repository\GestionMeubles\LignePanierRepository;
use App\Repository\GestionMeubles\MeubleRepository;
use App\Repository\GestionMeubles\PanierRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class MeubleController extends AbstractController
{
    private MeubleRepository $meubleRepository;
    private ValidatorInterface $validator;
    private PanierRepository $panierRepository;
    private LignePanierRepository $lignePanierRepository;
    public function __construct(
        MeubleRepository $meubleRepository,
        PanierRepository $panierRepository,
        ValidatorInterface $validator,
        LignePanierRepository $lignePanierRepository
            ) {
        $this->meubleRepository = $meubleRepository;
        $this->panierRepository = $panierRepository;
        $this->validator = $validator;
        $this->lignePanierRepository = $lignePanierRepository;
    }

    #[Route('/meubles', name: 'app_gestion_meubles_meuble')]
    public function index(): Response
    {
        $meubles = $this->meubleRepository->findAllMeubles();

        return $this->render('gestion_meubles/meuble/index.html.twig', [
            'controller_name' => 'GestionMeubles/MeubleController',
            'meubles' => $meubles,
        ]);
    }
    #[Route('/meubles/ajouter', name: 'app_gestion_meuble_ajouter', methods: ['GET', 'POST'])]
    public function ajouter(Request $request): Response
    {
        $meuble = new Meuble();
        $meuble->setDateEnregistrement(new \DateTime());
        $meuble->setCinVendeur('14450157');
        $meuble->setStatut('disponible');
        $meuble->setCategorie('occasion');
    
        $form = $this->createForm(MeubleType::class, $meuble);
        $form->handleRequest($request);
    
        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $imageFile = $form->get('image')->getData();
                
                if (!$imageFile) {
                    $this->addFlash('error', 'Veuillez sélectionner une image');
                    return $this->redirectToRoute('app_gestion_meuble_ajouter');
                }
    
                // Validation du fichier
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
    
                // Traitement de l'image
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $newFilename = $originalFilename.'-'.uniqid().'.'.$imageFile->guessExtension();
    
                try {
                    $imageFile->move(
                        $this->getParameter('images_directory'),
                        $newFilename
                    );
                    $meuble->setImage($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Erreur lors de l\'upload de l\'image : '.$e->getMessage());
                    return $this->redirectToRoute('app_gestion_meuble_ajouter');
                }
    
                $this->meubleRepository->save($meuble);
                $this->addFlash('success', 'Meuble ajouté avec succès !');
                return $this->redirectToRoute('app_gestion_meubles_mes_meubles');
            } else {
                // Récupérer toutes les erreurs du formulaire
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
    
    #[Route('/meubles/{id}/modifier', name: 'app_gestion_meuble_modifier', methods: ['GET', 'POST'])]
    public function modifier(Request $request, Meuble $meuble): Response
    {
        if ($meuble->getCinVendeur() !== "14450157") {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à modifier ce meuble.');
        }
    
        if ($meuble->getStatut() === 'indisponible') {
            $this->addFlash('error', 'Vous ne pouvez pas modifier un meuble déjà vendu.');
            return $this->redirectToRoute('app_gestion_meubles_mes_meubles');
        }
    
        $oldImage = $meuble->getImage();
        $form = $this->createForm(MeubleType::class, $meuble, [
            'validation_groups' => ['Default', 'edit']
        ]);
        
        $form->handleRequest($request);
    
        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $imageFile = $form->get('image')->getData();
                
                // Si nouvelle image est uploadée
                if ($imageFile) {
                    // Validation du fichier
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
                        return $this->redirectToRoute('app_gestion_meuble_modifier', ['id' => $meuble->getId()]);
                    }
    
                    $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                    $newFilename = $originalFilename.'-'.uniqid().'.'.$imageFile->guessExtension();
    
                    try {
                        $imageFile->move(
                            $this->getParameter('images_directory'),
                            $newFilename
                        );
                        // Supprimer l'ancienne image si elle existe
                        if ($oldImage && file_exists($this->getParameter('images_directory').'/'.$oldImage)) {
                            unlink($this->getParameter('images_directory').'/'.$oldImage);
                        }
                        $meuble->setImage($newFilename);
                    } catch (FileException $e) {
                        $this->addFlash('error', 'Erreur lors de l\'upload de l\'image : '.$e->getMessage());
                        return $this->redirectToRoute('app_gestion_meuble_modifier', ['id' => $meuble->getId()]);
                    }
                } else {
                    // Si aucune nouvelle image n'est uploadée, conserver l'ancienne
                    $meuble->setImage($oldImage);
                }
    
                $this->meubleRepository->save($meuble);
                $this->addFlash('success', 'Meuble modifié avec succès !');
                return $this->redirectToRoute('app_gestion_meubles_mes_meubles');
            } else {
                // Récupérer toutes les erreurs du formulaire
                $errors = $form->getErrors(true);
                foreach ($errors as $error) {
                    $this->addFlash('error', $error->getMessage());
                }
            }
        }
    
        return $this->render('gestion_meubles/meuble/form.html.twig', [
            'form' => $form->createView(),
            'meuble' => $meuble,
            'is_edit' => true,
        ]);
    }
    // #[Route('/meubles/ajouter', name: 'app_gestion_meuble_ajouter', methods: ['GET', 'POST'])]
    // public function ajouter(Request $request): Response
    // {
    //     $meuble = new Meuble();
    //     $meuble->setDateEnregistrement(new \DateTime());
    //     $meuble->setCinVendeur('14450157');
    //     $meuble->setStatut('disponible');
    //     $meuble->setCategorie('occasion');

    //     $form = $this->createForm(MeubleType::class, $meuble);
    //     $form->handleRequest($request);

    //     if ($form->isSubmitted() && $form->isValid()) {
    //         $imageFile = $form->get('image')->getData();
    //         if ($imageFile) {
    //             // Valider le fichier uploadé
    //             $constraint = new File([
    //                 'maxSize' => '5M',
    //                 'mimeTypes' => ['image/jpeg', 'image/png', 'image/gif'],
    //                 'mimeTypesMessage' => 'Veuillez uploader une image valide (JPEG, PNG, GIF).',
    //             ]);
    //             $violations = $this->validator->validate($imageFile, $constraint);

    //             if (count($violations) > 0) {
    //                 foreach ($violations as $violation) {
    //                     $this->addFlash('error', $violation->getMessage());
    //                 }
    //                 return $this->redirectToRoute('app_gestion_meuble_ajouter');
    //             }

    //             $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
    //             $newFilename = $originalFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();

    //             try {
    //                 $imageFile->move(
    //                     $this->getParameter('images_directory'),
    //                     $newFilename
    //                 );
    //                 $meuble->setImage($newFilename);
    //             } catch (FileException $e) {
    //                 $this->addFlash('error', 'Erreur lors de l\'upload de l\'image : ' . $e->getMessage());
    //                 return $this->redirectToRoute('app_gestion_meuble_ajouter');
    //             }
    //         } else {
    //             $meuble->setImage(null);
    //         }

    //         $this->meubleRepository->save($meuble);

    //         $this->addFlash('success', 'Meuble ajouté avec succès !');
    //         return $this->redirectToRoute('app_gestion_meubles_mes_meubles');
    //     }

    //     return $this->render('gestion_meubles/meuble/form.html.twig', [
    //         'form' => $form->createView(),
    //         'meuble' => $meuble,
    //         'is_edit' => false,
    //     ]);
    // }

    #[Route('/mes-meubles', name: 'app_gestion_meubles_mes_meubles')]
    public function consulterMesMeubles(): Response
    {
        $cinVendeur = "14450157";
        $meubles = $this->meubleRepository->findByCinVendeur($cinVendeur);

        return $this->render('gestion_meubles/meuble/consulter_mes_meubles.html.twig', [
            'meubles' => $meubles,
        ]);
    }

    #[Route('/meubles/{id}/supprimer', name: 'app_gestion_meuble_supprimer', methods: ['POST'])]
    public function supprimer(Request $request, Meuble $meuble): Response
    {
        if ($meuble->getCinVendeur() !== "14450157") {
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

    // #[Route('/meubles/{id}/modifier', name: 'app_gestion_meuble_modifier', methods: ['GET', 'POST'])]
    // public function modifier(Request $request, Meuble $meuble): Response
    // {
    //     if ($meuble->getCinVendeur() !== "14450157") {
    //         throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à modifier ce meuble.');
    //     }

    //     if ($meuble->getStatut() === 'indisponible') {
    //         $this->addFlash('error', 'Vous ne pouvez pas modifier un meuble déjà vendu.');
    //         return $this->redirectToRoute('app_gestion_meubles_mes_meubles');
    //     }

    //     $form = $this->createForm(MeubleType::class, $meuble);
    //     $form->handleRequest($request);

    //     if ($form->isSubmitted() && $form->isValid()) {
    //         $imageFile = $form->get('image')->getData();
    //         if ($imageFile) {
    //             // Valider le fichier uploadé
    //             $constraint = new File([
    //                 'maxSize' => '5M',
    //                 'mimeTypes' => ['image/jpeg', 'image/png', 'image/gif'],
    //                 'mimeTypesMessage' => 'Veuillez uploader une image valide (JPEG, PNG, GIF).',
    //             ]);
    //             $violations = $this->validator->validate($imageFile, $constraint);

    //             if (count($violations) > 0) {
    //                 foreach ($violations as $violation) {
    //                     $this->addFlash('error', $violation->getMessage());
    //                 }
    //                 return $this->redirectToRoute('app_gestion_meuble_modifier', ['id' => $meuble->getId()]);
    //             }

    //             $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
    //             $newFilename = $originalFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();

    //             try {
    //                 $imageFile->move(
    //                     $this->getParameter('images_directory'),
    //                     $newFilename
    //                 );
    //                 // Supprimer l'ancienne image si elle existe
    //                 if ($meuble->getImage()) {
    //                     $oldImagePath = $this->getParameter('images_directory') . '/' . $meuble->getImage();
    //                     if (file_exists($oldImagePath)) {
    //                         unlink($oldImagePath);
    //                     }
    //                 }
    //                 $meuble->setImage($newFilename);
    //             } catch (FileException $e) {
    //                 $this->addFlash('error', 'Erreur lors de l\'upload de l\'image : ' . $e->getMessage());
    //                 return $this->redirectToRoute('app_gestion_meuble_modifier', ['id' => $meuble->getId()]);
    //             }
    //         }

    //         $this->meubleRepository->save($meuble);

    //         $this->addFlash('success', 'Meuble modifié avec succès !');
    //         return $this->redirectToRoute('app_gestion_meubles_mes_meubles');
    //     }

    //     return $this->render('gestion_meubles/meuble/form.html.twig', [
    //         'form' => $form->createView(),
    //         'meuble' => $meuble,
    //         'is_edit' => true,
    //     ]);
    // }


    // #[Route('/meubles/a-vendre', name: 'app_gestion_meubles_a_vendre', methods: ['GET'])]
    // public function meublesAVendre(): Response
    // {
    //     // Récupérer l'utilisateur connecté (étudiant)
    //     // $user = $this->getUser();
    //     // if (!$user instanceof UserInterface) {
    //     //     throw $this->createAccessDeniedException('Vous devez être connecté pour voir les meubles à vendre.');
    //     // }

    //     // // Récupérer le CIN de l'utilisateur connecté
    //     // $cinAcheteur = $user->getCin(); // Assurez-vous que votre entité Utilisateur a une méthode getCin()

    //     // Récupérer les meubles disponibles à la vente
    //     $meubles = $this->meubleRepository->findMeublesDisponiblesPourAcheteur("14450157");

    //     return $this->render('gestion_meubles/meuble/a_vendre.html.twig', [
    //         'meubles' => $meubles,
    //     ]);
    // }
    // #[Route('/meubles/a-vendre', name: 'app_gestion_meubles_a_vendre', methods: ['GET'])]
    // public function meublesAVendre(): Response
    // {
    //     $cinAcheteur = "14450157";
    //     $meubles = $this->meubleRepository->findMeublesDisponiblesPourAcheteur($cinAcheteur);

    //     return $this->render('gestion_meubles/meuble/a_vendre.html.twig', [
    //         'meubles' => $meubles,
    //         'cin_acheteur' => $cinAcheteur,
    //     ]);
    // }
    #[Route('/meubles/ajouter-au-panier/{id}', name: 'app_gestion_meubles_ajouter_panier', methods: ['POST'])]
    public function ajouterAuPanier(Request $request, int $id): JsonResponse
    {
        try {
            if (!$this->isCsrfTokenValid('add_to_cart_' . $id, $request->request->get('_token'))) {
                throw new \Exception('Token CSRF invalide');
            }

            $cinAcheteur = "14450157"; // À remplacer par $this->getUser()->getCin() en production
            $meuble = $this->meubleRepository->find($id);

            if (!$meuble) {
                throw new \Exception('Meuble non trouvé');
            }

            if ($meuble->getStatut() !== 'disponible') {
                throw new \Exception('Ce meuble n\'est plus disponible');
            }

            $panier = $this->panierRepository->findPanierEnCours($cinAcheteur);
            if (!$panier) {
                $panier = new Panier();
                $panier->setCinAcheteur($cinAcheteur);
                $panier->setStatut(Panier::STATUT_EN_COURS);
                $panier->setDateAjout(new \DateTime());
                $this->panierRepository->save($panier, true);
            }

            if ($this->lignePanierRepository->verifierProduitDansPanier($panier->getId(), $meuble->getId())) {
                return $this->json([
                    'warning' => 'Ce meuble est déjà dans votre panier.'
                ], Response::HTTP_CONFLICT);
            }

            $lignePanier = new LignePanier();
            $lignePanier->setPanier($panier);
            $lignePanier->setMeuble($meuble);
            $this->lignePanierRepository->save($lignePanier, true);

            return $this->json([
                'message' => 'Le meuble a été ajouté à votre panier.',
                'panier_id' => $panier->getId(),
                'meuble_nom' => $meuble->getNom(),
                'redirect' => $this->generateUrl('app_gestion_meubles_a_vendre') // URL pour recharger la page
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return $this->json([
                'error' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/meubles/a-vendre', name: 'app_gestion_meubles_a_vendre', methods: ['GET'])]
    public function meublesAVendre(): Response
    {
        $cinAcheteur = "14450157"; // À remplacer par $this->getUser()->getCin() en production
        $meubles = $this->meubleRepository->findMeublesDisponiblesPourAcheteur($cinAcheteur);

        return $this->render('gestion_meubles/meuble/a_vendre.html.twig', [
            'meubles' => $meubles,
            'cin_acheteur' => $cinAcheteur,
        ]);
    }
}