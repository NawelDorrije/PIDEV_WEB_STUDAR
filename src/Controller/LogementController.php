<?php

namespace App\Controller;

use App\Entity\ImageLogement;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Entity\Logement;
use App\Form\LogementType;
use App\Repository\LogementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

#[Route('/logement')]
final class LogementController extends AbstractController
{
    #[Route('/', name: 'app_logement_index', methods: ['GET'])]
    public function index(LogementRepository $logementRepository): Response
    {
        return $this->render('logement/index.html.twig', [
            'logements' => $logementRepository->findAll(),
            'filter' => [
                'type' => null,
                'prix' => null,
                'nbrChambre' => null,
                'adresse' => null,
            ],
            'geocodeError' => false,
        ]);
    }

    #[Route('/logement/filtrage', name: 'app_logement_index_filtrage', methods: ['GET'])]
    public function indexFiltrage(Request $request, LogementRepository $logementRepository): Response
    {
        $filter = [
            'type' => $request->query->get('type') ?: null,
            'prix' => $request->query->get('prix') ? (float) $request->query->get('prix') : null,
            'nbrChambre' => $request->query->get('nbrChambre') ? (int) $request->query->get('nbrChambre') : null,
            'adresse' => $request->query->get('adresse') ?: null,
            'distance' => $request->query->getInt('distance', 10),
        ];

        $logements = $logementRepository->findNearby(
            $filter['type'],
            $filter['prix'],
            $filter['nbrChambre'],
            null,
            null,
            $filter['distance']
        );

        return $this->render('logement/index.html.twig', [
            'logements' => $logements,
            'filter' => $filter,
            'geocodeError' => false
        ]);
    }

    #[Route('/new', name: 'app_logement_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, Security $security): Response
    {
        $logement = new Logement();
        $form = $this->createForm(LogementType::class, $logement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $latitude = $form->get('latitude')->getData();
            $longitude = $form->get('longitude')->getData();
            $address = $form->get('address')->getData();
            $logement->setAdresse($address);

            $user = $security->getUser();
            if ($user) {
                $logement->setUtilisateurCin($user);
            }

            $photos = $request->files->get('photos', []);
            if (!empty($photos)) {
                foreach ($photos as $file) {
                    if ($file) {
                        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif'];
                        if (!in_array($file->getMimeType(), $allowedMimeTypes)) {
                            $this->addFlash('error', 'Invalid file type. Only JPEG, PNG, and GIF are allowed.');
                            continue;
                        }
                        if ($file->getSize() > 5 * 1024 * 1024) {
                            $this->addFlash('error', 'File is too large. Maximum size is 5MB.');
                            continue;
                        }

                        try {
                            $fileName = md5(uniqid()) . '.' . $file->guessExtension();
                            $file->move(
                                $this->getParameter('photos_directory'),
                                $fileName
                            );
                            $photo = new ImageLogement();
                            $photo->setUrl($fileName);
                            $logement->addImageLogement($photo);
                            $entityManager->persist($photo);
                        } catch (FileException $e) {
                            $this->addFlash('error', 'Error uploading file: ' . $e->getMessage());
                        }
                    }
                }
            }
            if ($latitude && $longitude) {
                $localisation = new \LongitudeOne\Spatial\PHP\Types\Geography\Point($latitude, $longitude);
                $logement->setLocalisation($localisation);
            }
            $entityManager->persist($logement);
            $entityManager->flush();

            $this->addFlash('success', 'Logement created successfully!');
            return $this->redirectToRoute('app_logement_index');
        }

        return $this->render('logement/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_logement_show', methods: ['GET'])]
    public function show(Logement $logement): Response
    {
        $imageLogements = $logement->getImageLogements();

        return $this->render('logement/show.html.twig', [
            'logement' => $logement,
            'imageLogements' => $imageLogements,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_logement_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Logement $logement, EntityManagerInterface $entityManager, Security $security): Response
    {
        $form = $this->createForm(LogementType::class, $logement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $latitude = $form->get('latitude')->getData();
            $longitude = $form->get('longitude')->getData();
            $address = $form->get('address')->getData();
            $logement->setAdresse($address);
            $deletedPhotoIds = $request->request->get('deleted_photos', '');
            if ($deletedPhotoIds) {
                $deletedPhotoIds = explode(',', $deletedPhotoIds);
                foreach ($deletedPhotoIds as $photoId) {
                    $photo = $entityManager->getRepository(ImageLogement::class)->find($photoId);
                    if ($photo) {
                        $filePath = $this->getParameter('photos_directory') . '/' . $photo->getUrl();
                        if (file_exists($filePath)) {
                            unlink($filePath);
                        }
                        $entityManager->remove($photo);
                    }
                }
            }
            $user = $security->getUser();
            if ($user) {
                $logement->setUtilisateurCin($user);
            }

            $photos = $request->files->get('photos', []);
            if (!empty($photos)) {
                foreach ($photos as $file) {
                    if ($file) {
                        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif'];
                        if (!in_array($file->getMimeType(), $allowedMimeTypes)) {
                            $this->addFlash('error', 'Invalid file type. Only JPEG, PNG, and GIF are allowed.');
                            continue;
                        }
                        if ($file->getSize() > 5 * 1024 * 1024) {
                            $this->addFlash('error', 'File is too large. Maximum size is 5MB.');
                            continue;
                        }

                        try {
                            $fileName = md5(uniqid()) . '.' . $file->guessExtension();
                            $file->move(
                                $this->getParameter('photos_directory'),
                                $fileName
                            );
                            $photo = new ImageLogement();
                            $photo->setUrl($fileName);
                            $logement->addImageLogement($photo);
                            $entityManager->persist($photo);
                        } catch (FileException $e) {
                            $this->addFlash('error', 'Error uploading file: ' . $e->getMessage());
                        }
                    }
                }
            }

            if ($latitude && $longitude) {
                $localisation = new \LongitudeOne\Spatial\PHP\Types\Geography\Point($latitude, $longitude);
                $logement->setLocalisation($localisation);
            }

            $entityManager->flush();

            $this->addFlash('success', 'Logement updated successfully!');
            return $this->redirectToRoute('app_logement_show', ['id' => $logement->getId()]);
        }

        return $this->render('logement/edit.html.twig', [
            'logement' => $logement,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_logement_delete', methods: ['POST'])]
    public function delete(Request $request, Logement $logement, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $logement->getId(), $request->request->get('_token'))) {
            $entityManager->remove($logement);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_logement_index');
    }
}