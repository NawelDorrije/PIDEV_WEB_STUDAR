<?php

namespace App\Controller\GestionMeubles;

use App\Entity\GestionMeubles\Meuble;
use App\Form\MeubleType;
use App\Repository\GestionMeubles\MeubleRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class MeubleController extends AbstractController
{
    private MeubleRepository $meubleRepository;
    private ValidatorInterface $validator;

    public function __construct(MeubleRepository $meubleRepository, ValidatorInterface $validator)
    {
        $this->meubleRepository = $meubleRepository;
        $this->validator = $validator;
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

        if ($form->isSubmitted() && $form->isValid()) {
            $imageFile = $form->get('image')->getData();
            if ($imageFile) {
                // Valider le fichier uploadé
                $constraint = new File([
                    'maxSize' => '5M',
                    'mimeTypes' => ['image/jpeg', 'image/png', 'image/gif'],
                    'mimeTypesMessage' => 'Veuillez uploader une image valide (JPEG, PNG, GIF).',
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
            } else {
                $meuble->setImage(null);
            }

            $this->meubleRepository->save($meuble);

            $this->addFlash('success', 'Meuble ajouté avec succès !');
            return $this->redirectToRoute('app_gestion_meubles_mes_meubles');
        }

        return $this->render('gestion_meubles/meuble/form.html.twig', [
            'form' => $form->createView(),
            'meuble' => $meuble,
            'is_edit' => false,
        ]);
    }

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

        $form = $this->createForm(MeubleType::class, $meuble);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $imageFile = $form->get('image')->getData();
            if ($imageFile) {
                // Valider le fichier uploadé
                $constraint = new File([
                    'maxSize' => '5M',
                    'mimeTypes' => ['image/jpeg', 'image/png', 'image/gif'],
                    'mimeTypesMessage' => 'Veuillez uploader une image valide (JPEG, PNG, GIF).',
                ]);
                $violations = $this->validator->validate($imageFile, $constraint);

                if (count($violations) > 0) {
                    foreach ($violations as $violation) {
                        $this->addFlash('error', $violation->getMessage());
                    }
                    return $this->redirectToRoute('app_gestion_meuble_modifier', ['id' => $meuble->getId()]);
                }

                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $newFilename = $originalFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();

                try {
                    $imageFile->move(
                        $this->getParameter('images_directory'),
                        $newFilename
                    );
                    // Supprimer l'ancienne image si elle existe
                    if ($meuble->getImage()) {
                        $oldImagePath = $this->getParameter('images_directory') . '/' . $meuble->getImage();
                        if (file_exists($oldImagePath)) {
                            unlink($oldImagePath);
                        }
                    }
                    $meuble->setImage($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Erreur lors de l\'upload de l\'image : ' . $e->getMessage());
                    return $this->redirectToRoute('app_gestion_meuble_modifier', ['id' => $meuble->getId()]);
                }
            }

            $this->meubleRepository->save($meuble);

            $this->addFlash('success', 'Meuble modifié avec succès !');
            return $this->redirectToRoute('app_gestion_meubles_mes_meubles');
        }

        return $this->render('gestion_meubles/meuble/form.html.twig', [
            'form' => $form->createView(),
            'meuble' => $meuble,
            'is_edit' => true,
        ]);
    }


    #[Route('/meubles/a-vendre', name: 'app_gestion_meubles_a_vendre', methods: ['GET'])]
    public function meublesAVendre(): Response
    {
        // Récupérer l'utilisateur connecté (étudiant)
        // $user = $this->getUser();
        // if (!$user instanceof UserInterface) {
        //     throw $this->createAccessDeniedException('Vous devez être connecté pour voir les meubles à vendre.');
        // }

        // // Récupérer le CIN de l'utilisateur connecté
        // $cinAcheteur = $user->getCin(); // Assurez-vous que votre entité Utilisateur a une méthode getCin()

        // Récupérer les meubles disponibles à la vente
        $meubles = $this->meubleRepository->findMeublesDisponiblesPourAcheteur("14450157");

        return $this->render('gestion_meubles/meuble/a_vendre.html.twig', [
            'meubles' => $meubles,
        ]);
    }
}