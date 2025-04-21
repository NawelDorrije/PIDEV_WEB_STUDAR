<?php
namespace App\Controller;

use App\Entity\ImageLogement;
use App\Entity\Logement;
use App\Entity\LogementOptions;
use App\Entity\Options;
use App\Form\LogementType;
use App\Repository\LogementRepository;
use App\Repository\OptionsRepository;
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
    public function index(LogementRepository $logementRepository, Request $request): Response
    {
        // Get the current user
        $user = $this->getUser();

        // Check if the user is logged in
        if (!$user) {
            $this->addFlash('error', 'Vous devez être connecté pour voir vos logements.');
            return $this->redirectToRoute('app_logement_index', [], Response::HTTP_SEE_OTHER);
        }

        // Fetch logements associated with the user's cin
        $logements = $logementRepository->findBy(['utilisateur_cin' => $user->getCin()]);

        // Prepare filter values (you can expand this based on request parameters)
        $filter = [
            'type' => $request->query->get('type'),
            'prix' => $request->query->get('prix'),
            'nbrChambre' => $request->query->get('nbrChambre'),
            'adresse' => $request->query->get('adresse'),
        ];

        // Render the template with the fetched logements, filter, and geocodeError flag
        return $this->render('Client/logement/index.html.twig', [
            'logements' => $logements,
            'filter' => $filter,
            'geocodeError' => false,
        ]);
    }

    #[Route('/filtrage', name: 'app_logement_index_filtrage', methods: ['GET'])]
    public function indexFiltrage(Request $request, LogementRepository $logementRepository): Response
    {
            // Get the current user
            $user = $this->getUser();

            // Check if the user is logged in
            if (!$user) {
                $this->addFlash('error', 'Vous devez être connecté pour voir vos logements.');
                return $this->redirectToRoute('app_logement_index', [], Response::HTTP_SEE_OTHER);
            }
        $filter = [
            'utilisateur_cin'=>$request->query->get('utilisateur_cin')?:$user,
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
    public function new(Request $request, EntityManagerInterface $entityManager, Security $security, OptionsRepository $optionsRepository): Response
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
    
                // Persist Logement first
                try {
                    $entityManager->persist($logement);
                    $entityManager->flush();
                    $this->logger->info('Logement persisted', ['id_logement' => $logement->getId()]);
                } catch (\Exception $e) {
                    $this->logger->error('Failed to persist logement', ['error' => $e->getMessage()]);
                    $this->addFlash('error', 'Error saving logement: ' . $e->getMessage());
                    return $this->render('Client/logement/new.html.twig', [
                        'form' => $form->createView(),
                        'logement' => $logement,
                    ]);
                }
    
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
    
                // Handle options
                $optionsData = $request->request->get('options', '[]');
                $this->logger->info('Options received', ['optionsData' => $optionsData]);
                $options = json_decode($optionsData, true);
                if (is_array($options) && !empty($options)) {
                    foreach ($options as $optionName) {
                        $optionName = trim($optionName);
                        if (!empty($optionName) && strlen($optionName) <= 255) {
                            $this->logger->info('Processing option', ['optionName' => $optionName]);
                            $option = $optionsRepository->findOneBy(['nom_option' => $optionName]);
                            if (!$option) {
                                try {
                                    $option = new Options();
                                    $option->setNomOption($optionName);
                                    $this->logger->debug('Persisting new option', ['nom_option' => $optionName]);
                                    $entityManager->persist($option);
                                    $entityManager->flush();
                                    $this->logger->info('New option created', ['id_option' => $option->getIdOption()]);
                                } catch (\Exception $e) {
                                    $this->logger->error('Failed to create option', [
                                        'optionName' => $optionName,
                                        'error' => $e->getMessage(),
                                        'trace' => $e->getTraceAsString(),
                                    ]);
                                    $this->addFlash('error', 'Failed to create option: ' . $optionName);
                                    continue;
                                }
                            } else {
                                $this->logger->info('Existing option found', ['id_option' => $option->getIdOption()]);
                            }
    
                            if ($option->getIdOption() === null) {
                                $this->logger->error('Option has no ID', ['optionName' => $optionName]);
                                $this->addFlash('error', 'Invalid option ID for: ' . $optionName);
                                continue;
                            }
    
                            try {
                                $logementOption = new LogementOptions($logement, $option);
                                $logementOption->setValeur(true);
                                $this->logger->debug('Persisting LogementOption', [
                                    'id_logement' => $logement->getId(),
                                    'id_option' => $option->getIdOption(),
                                ]);
                                $entityManager->persist($logementOption);
                                $logement->addLogementOption($logementOption);
                                $this->logger->info('LogementOption created', [
                                    'id_logement' => $logement->getId(),
                                    'id_option' => $option->getIdOption(),
                                ]);
                            } catch (\Exception $e) {
                                $this->logger->error('Failed to create LogementOption', [
                                    'optionName' => $optionName,
                                    'id_logement' => $logement->getId(),
                                    'id_option' => $option->getIdOption(),
                                    'error' => $e->getMessage(),
                                    'trace' => $e->getTraceAsString(),
                                ]);
                                $this->addFlash('error', 'Failed to create LogementOption for: ' . $optionName);
                                continue;
                            }
                        } else {
                            $this->logger->warning('Skipping invalid option name', ['optionName' => $optionName]);
                            if (empty($optionName)) {
                                $this->addFlash('warning', 'Empty option name skipped.');
                            } else {
                                $this->addFlash('warning', 'Option name too long: ' . $optionName);
                            }
                        }
                    }
                } else {
                    $this->logger->info('No valid options provided');
                }
    
                // Final flush
                try {
                    if (!$entityManager->isOpen()) {
                        $this->logger->error('EntityManager is closed, cannot proceed with flush');
                        $this->addFlash('error', 'Database error: Unable to save logement due to a previous error.');
                        return $this->render('Client/logement/new.html.twig', [
                            'form' => $form->createView(),
                            'logement' => $logement,
                        ]);
                    }
                    $entityManager->flush();
                    $this->addFlash('success', 'Logement created successfully!');
                    return $this->redirectToRoute('app_logement_index', [], Response::HTTP_SEE_OTHER);
                } catch (\Exception $e) {
                    $this->logger->error('Error saving logement', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    $this->addFlash('error', 'Error saving logement: ' . $e->getMessage());
                }
            } else {
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
                    $this->addFlash('error', 'Error: ' . $errorMessage);
                }
            }
        }
    
        return $this->render('Client/logement/new.html.twig', [
            'form' => $form->createView(),
            'logement' => $logement,
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
    public function edit(
        Request $request,
        Logement $logement,
        EntityManagerInterface $entityManager,
        Security $security,
        OptionsRepository $optionsRepository
    ): Response {
        $form = $this->createForm(LogementType::class, $logement);
        $form->handleRequest($request);

        // Fetch existing options for rendering
        $options = [];
        foreach ($logement->getLogementOptions() as $logementOption) {
            $option = $logementOption->getOption();
            if ($option) {
                $options[] = $option->getNomOption();
            }
        }

        if ($form->isSubmitted()) {
            $this->logger->info('Edit form submitted', [
                'is_valid' => $form->isValid(),
                'photos_data' => $form->get('photos')->getData() ? count($form->get('photos')->getData()) : 0,
                'options_data' => $request->request->get('options', '[]'),
            ]);

            if ($form->isValid()) {
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
                                $this->addFlash('error', 'Invalid file type: ' . $file->getClientOriginalName());
                                continue;
                            }
                            if ($file->getSize() > 5 * 1024 * 1024) {
                                $this->addFlash('error', 'File too large: ' . $file->getClientOriginalName());
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
                }

                // Update localisation
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

                // Handle options
                $optionsData = $request->request->get('options', '[]');
                $newOptions = json_decode($optionsData, true);
                $existingOptions = [];
                foreach ($logement->getLogementOptions() as $logementOption) {
                    $option = $logementOption->getOption();
                    $existingOptions[$option->getNomOption()] = $logementOption;
                }

                if (is_array($newOptions) && !empty($newOptions)) {
                    foreach ($newOptions as $optionName) {
                        $optionName = trim($optionName);
                        if (!empty($optionName) && strlen($optionName) <= 255) {
                            if (!isset($existingOptions[$optionName])) {
                                $option = $optionsRepository->findOneBy(['nom_option' => $optionName]);
                                if (!$option) {
                                    $option = new Options();
                                    $option->setNomOption($optionName);
                                    $entityManager->persist($option);
                                    $entityManager->flush();
                                }
                                $logementOption = new LogementOptions($logement, $option);
                                $logementOption->setValeur(true);
                                $entityManager->persist($logementOption);
                                $logement->addLogementOption($logementOption);
                                $this->logger->info('LogementOption created', ['option' => $optionName]);
                            }
                        }
                    }
                }

                foreach ($existingOptions as $optionName => $logementOption) {
                    if (!in_array($optionName, $newOptions)) {
                        $logement->removeLogementOption($logementOption);
                        $entityManager->remove($logementOption);
                        $this->logger->info('LogementOption removed', ['option' => $optionName]);
                    }
                }

                // Final flush
                try {
                    if (!$entityManager->isOpen()) {
                        $this->logger->error('EntityManager is closed, cannot proceed with flush');
                        $this->addFlash('error', 'Database error: Unable to save logement due to a previous error.');
                        return $this->render('Client/logement/edit.html.twig', [
                            'logement' => $logement,
                            'form' => $form->createView(),
                            'options' => $options,
                        ]);
                    }
                    $entityManager->flush();
                    $this->addFlash('success', 'Logement updated successfully!');
                    return $this->redirectToRoute('app_logement_show', ['id' => $logement->getId()], Response::HTTP_SEE_OTHER);
                } catch (\Exception $e) {
                    $this->logger->error('Error saving logement', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    $this->addFlash('error', 'Error saving logement: ' . $e->getMessage());
                    return $this->render('Client/logement/edit.html.twig', [
                        'logement' => $logement,
                        'form' => $form->createView(),
                        'options' => $options,
                    ]);
                }
            } else {
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
                    $this->addFlash('error', 'Error: ' . $errorMessage);
                }
                return $this->render('Client/logement/edit.html.twig', [
                    'logement' => $logement,
                    'form' => $form->createView(),
                    'options' => $options,
                ]);
            }
        }

        return $this->render('Client/logement/edit.html.twig', [
            'logement' => $logement,
            'form' => $form->createView(),
            'options' => $options,
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
        } else {
            $this->addFlash('error', 'Invalid CSRF token.');
        }

        return $this->redirectToRoute('app_logement_index', [], Response::HTTP_SEE_OTHER);
    }
}