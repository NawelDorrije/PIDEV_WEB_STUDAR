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

final class MeubleController extends AbstractController
{
    private MeubleRepository $meubleRepository;

    // Injection du repository via le constructeur
    public function __construct(MeubleRepository $meubleRepository)
    {
        $this->meubleRepository = $meubleRepository;
    }

    #[Route('/meubles', name: 'app_gestion_meubles_meuble')]
    public function index(): Response
    {
        // Récupérer tous les meubles avec le repository
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
        // Définir les valeurs par défaut
        $meuble->setDateEnregistrement(new \DateTime());
        $meuble->setCinVendeur('14450157');
        $meuble->setStatut('disponible');
        $meuble->setCategorie('occasion');

        $form = $this->createForm(MeubleType::class, $meuble);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Gérer l'upload de l'image
            $imageFile = $form->get('image')->getData();
            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $newFilename = $originalFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();

                try {
                    $imageFile->move(
                        $this->getParameter('images_directory'), // Assure-toi que ce paramètre est défini dans config/services.yaml
                        $newFilename
                    );
                    $meuble->setImage($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Erreur lors de l\'upload de l\'image : ' . $e->getMessage());
                    return $this->redirectToRoute('app_gestion_meuble_ajouter');
                }
            }

            // Sauvegarder le meuble avec le repository
            $this->meubleRepository->save($meuble);

            $this->addFlash('success', 'Meuble ajouté avec succès !');
            return $this->redirectToRoute('app_gestion_meubles_meuble');
        }

        return $this->render('gestion_meubles/meuble/ajouter.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}