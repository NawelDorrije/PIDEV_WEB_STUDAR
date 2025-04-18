<?php
namespace App\Controller;

use App\Entity\ImageLogement;
use App\Entity\Logement;
use App\Form\LogementType;

use App\Repository\LogementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Psr\Log\LoggerInterface;

#[Route('/logement')]
final class LogementController extends AbstractController
{
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    #[Route('/', name: 'app_logement_index', methods: ['GET'])]
    public function index(LogementRepository $logementRepository): Response
    {
        return $this->render('Client/logement/index.html.twig', [
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

    #[Route('/filtrage', name: 'app_logement_index_filtrage', methods: ['GET'])]
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

        return $this->render('Client/logement/index.html.twig', [
            'logements' => $logements,
            'filter' => $filter,
            'geocodeError' => false,
        ]);
    }

    #[Route('/new', name: 'app_logement_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, Security $security): Response
    {
        $logement = new Logement();
        $form = $this->createForm(LogementType::class, $logement);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $this->logger->info('Form submitted', [
                'is_valid' => $form->isValid(),
                'photos_data' => $form->get('photos')->getData() ? count($form->get('photos')->getData()) : 0,
            ]);

            if ($form->isValid()) {
                // Set address
                $address = $form->get('address')->getData();
                if ($address) {
                    $logement->setAdresse($address);
                }

                // Set user
                $user = $security->getUser();
                if (!$user) {
                    $this->addFlash('error', 'You must be logged in to create a logement.');
                    return $this->redirectToRoute('app_logement_index', [], Response::HTTP_SEE_OTHER);
                }
                $logement->setUtilisateurCin($user);

                // Handle photos
                $photos = $form->get('photos')->getData();
                if (!empty($photos)) {
                    $photosDirectory = $this->getParameter('photos_directory');
                    foreach ($photos as $index => $file) {
                        if ($file) {
                            $this->logger->info('Processing photo', [
                                'index' => $index,
                                'name' => $file->getClientOriginalName(),
                                'type' => $file->getMimeType(),
                                'size' => $file->getSize(),
                            ]);

                            $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif'];
                            if (!in_array($file->getMimeType(), $allowedMimeTypes)) {
                                $this->addFlash('error', 'Invalid file type: ' . $file->getClientOriginalName());
                                continue;
                            }
                            if ($file->getSize() > 5 * 1024 * 1024) {
                                $this->addFlash('error', 'File too large: ' . $file->getClientOriginalName());
                                continue;
                            }

                            try {
                                $fileName = md5(uniqid()) . '.' . $file->guessExtension();
                                $file->move($photosDirectory, $fileName);
                                $photo = new ImageLogement();
                                $photo->setUrl($fileName);
                                $logement->addImageLogement($photo);
                                $entityManager->persist($photo);
                                $this->logger->info('Photo uploaded', ['file' => $fileName]);
                            } catch (FileException $e) {
                                $this->logger->error('Photo upload failed', [
                                    'file' => $file->getClientOriginalName(),
                                    'error' => $e->getMessage(),
                                ]);
                                $this->addFlash('error', 'Error uploading ' . $file->getClientOriginalName());
                            }
                        }
                    }
                } else {
                    $this->logger->info('No photos uploaded');
                }

                // Set localisation
                $lat = $form->get('lat')->getData();
                $lng = $form->get('lng')->getData();
                if ($lat && $lng && is_numeric($lat) && is_numeric($lng)) {
                    $lat = (float) $lat;
                    $lng = (float) $lng;
                    if ($lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180) {
                        $localisation = new \LongitudeOne\Spatial\PHP\Types\Geography\Point($lat, $lng);
                        $logement->setLocalisation($localisation);
                    } else {
                        $this->addFlash('warning', 'Invalid coordinates provided.');
                    }
                }

                try {
                    $entityManager->persist($logement);
                    $entityManager->flush();
                    $this->addFlash('success', 'Logement created successfully!');
                    return $this->redirectToRoute('app_logement_index', [], Response::HTTP_SEE_OTHER);
                } catch (\Exception $e) {
                    $this->logger->error('Error saving logement', ['error' => $e->getMessage()]);
                    $this->addFlash('error', 'Error saving logement.');
                }
            } else {
                // Log form errors
                $errors = $form->getErrors(true, true);
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = sprintf(
                        'Field: %s, Error: %s',
                        $error->getOrigin() ? $error->getOrigin()->getName() : 'Global',
                        $error->getMessage()
                    );
                }
                $this->logger->warning('Form validation failed', ['errors' => $errorMessages]);
                foreach ($errorMessages as $errorMessage) {
                    $this->addFlash('error', $errorMessage);
                }
            }
        }

        // Reset form view to ensure consistent rendering
        $formView = $form->createView();

        return $this->render('Client/logement/new.html.twig', [
            'form' => $formView,
            'logement' => $logement, // Pass logement for context
        ]);
    }
    #[Route('/{id}', name: 'app_logement_show', methods: ['GET'])]
    public function show(Logement $logement): Response
    {
        $imageLogements = $logement->getImageLogements();

        return $this->render('Client/logement/show.html.twig', [
            'logement' => $logement,
            'imageLogements' => $imageLogements,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_logement_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Logement $logement, EntityManagerInterface $entityManager, Security $security): Response
    {
        $form = $this->createForm(LogementType::class, $logement);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $lat = $form->get('lat')->getData();
                $lng = $form->get('lng')->getData();

                // Handle deleted photos
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

                // Set user
                $user = $security->getUser();
                if ($user) {
                    $logement->setUtilisateurCin($user);
                }

                // Handle new photos
                $photos = $form->get('photos')->getData();
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
                                $this->logger->error('File upload failed: ' . $e->getMessage());
                                $this->addFlash('error', 'Error uploading file.');
                            }
                        }
                    }
                }

                // Update localisation
                if ($lat && $lng) {
                    $localisation = new \LongitudeOne\Spatial\PHP\Types\Geography\Point($lat, $lng);
                    $logement->setLocalisation($localisation);
                }

                $entityManager->flush();

                $this->addFlash('success', 'Logement updated successfully!');
                return $this->redirectToRoute('app_logement_show', ['id' => $logement->getId()], Response::HTTP_SEE_OTHER);
            } else {
                $this->addFlash('error', 'Please correct the errors in the form.');
                $this->logger->warning('Form validation failed', ['errors' => $form->getErrors(true)]);
            }
        }

        return $this->render('Client/logement/edit.html.twig', [
            'logement' => $logement,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_logement_delete', methods: ['POST'])]
    public function delete(Request $request, Logement $logement, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $logement->getId(), $request->request->get('_token'))) {
            foreach ($logement->getImageLogements() as $photo) {
                $filePath = $this->getParameter('photos_directory') . '/' . $photo->getUrl();
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
                $entityManager->remove($photo);
            }
            $entityManager->remove($logement);
            $entityManager->flush();
            $this->addFlash('success', 'Logement deleted successfully!');
        } else {
            $this->addFlash('error', 'Invalid CSRF token.');
        }

        return $this->redirectToRoute('app_logement_index', [], Response::HTTP_SEE_OTHER);
    }
  

}