<?php
namespace App\Controller;
use App\Service\GeminiService;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use App\Entity\ImageLogement;
use App\Entity\Logement;
use App\Entity\Utilisateur;
use App\Form\LogementType;

use App\Repository\LogementRepository;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use MailerSend\Helpers\Builder\EmailParams;
use MailerSend\Helpers\Builder\Recipient;
use MailerSend\MailerSend;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
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
    private $mailerFrom;
    private $mailerFromName;
    private $geminiService;
        public function __construct(
        LoggerInterface $logger,
        GeminiService $geminiService,
        string $mailerFrom,
        string $mailerFromName
            ) {
        $this->logger = $logger;
        $this->mailerFrom = $mailerFrom;
        $this->mailerFromName = $mailerFromName;
        $this->geminiService = $geminiService;

    }
    private function addReactionData(array $logements): array
    {
        $logementsWithReactions = [];
        foreach ($logements as $logement) {
            $emojis = $logement->getEmojis() ?? [];
            $reactionCounts = array_count_values($emojis);
            $totalReactions = count($emojis);
            $logementsWithReactions[] = [
                'entity' => $logement,
                'reactionCounts' => $reactionCounts,
                'totalReactions' => $totalReactions,
                'reactionScore' => ($reactionCounts['❤️'] ?? 0) * 3 + ($reactionCounts['👍'] ?? 0) * 2 + ($reactionCounts['👎'] ?? 0) * -1,
            ];
        }
    
        // Trier par reactionScore
        usort($logementsWithReactions, function ($a, $b) {
            return $b['reactionScore'] <=> $a['reactionScore'];
        });
    
        return $logementsWithReactions;
    }
    #[Route('/logement', name: 'app_logement_index', methods: ['GET'])]
    public function index(LogementRepository $logementRepository): Response
    {
        $logements = $logementRepository->findAll();
        $logementsWithReactions = $this->addReactionData($logements);
    
        return $this->render('Client/logement/index.html.twig', [
            'logements' => $logementsWithReactions,
            'filter' => [
                'type' => null,
                'prix' => null,
                'nbrChambre' => null,
                'adresse' => null,
            ],
            'geocodeError' => false,
        ]);
    }
    #[Route('/logement/users', name: 'app_logement_users', methods: ['GET'])]
    public function getUsers(UtilisateurRepository $utilisateurRepository): Response
    {
        $users = $utilisateurRepository->findAll();
        // Transform users into a simple array to avoid serialization issues
        $userData = array_map(function (Utilisateur $user) {
            return [
                'id' => $user->getCin(),
                'nom' => $user->getNom(),
                'email' => $user->getEmail(),
            ];
        }, $users);
    
        $this->logger->info('Users retrieved for Share Modal', [
            'count' => count($users),
        ]);
    
        return $this->json($userData);
    }
    // New method to handle sharing a logement
    #[Route('/{id}/share', name: 'app_logement_share', methods: ['POST'])]
    public function share(Request $request, Logement $logement, UtilisateurRepository $utilisateurRepository, MailerInterface $mailer): JsonResponse
    {
        try {
            $this->logger->info('Starting share method', ['logement_id' => $logement->getId()]);
    
            $user = $this->getUser();
            if (!$user) {
                $this->logger->info('User not authenticated');
                return $this->json(['error' => 'Authentication required'], 401);
            }
            $this->logger->info('User authenticated', ['user_id' => $user->getUserIdentifier()]);
    
            $userId = $request->request->get('userId');
            if (!$userId) {
                $this->logger->info('User ID missing');
                return $this->json(['error' => 'User ID is required'], 400);
            }
            $this->logger->info('User ID received', ['user_id' => $userId]);
    
            $recipient = $utilisateurRepository->findByCin($userId);
            if (!$recipient) {
                $this->logger->info('Recipient not found', ['user_id' => $userId]);
                return $this->json(['error' => 'User not found'], 404);
            }
            $this->logger->info('Recipient found', ['recipient_email' => $recipient->getEmail()]);
    
            if (!$recipient->getEmail() || !filter_var($recipient->getEmail(), FILTER_VALIDATE_EMAIL)) {
                $this->logger->info('Invalid recipient email', ['email' => $recipient->getEmail()]);
                return $this->json(['error' => 'Recipient email is invalid'], 400);
            }
            $this->logger->info('Recipient email validated');
    
            if (!$logement->getAdresse()) {
                $this->logger->info('Logement address missing', ['logement_id' => $logement->getId()]);
                return $this->json(['error' => 'Logement address is missing'], 400);
            }
            $this->logger->info('Logement address validated', ['adresse' => $logement->getAdresse()]);
    
            if (!$this->mailerFrom || !filter_var($this->mailerFrom, FILTER_VALIDATE_EMAIL)) {
                $this->logger->info('Invalid sender email configuration', ['mailer_from' => $this->mailerFrom]);
                return $this->json(['error' => 'Sender email configuration is invalid'], 500);
            }
            $this->logger->info('Sender email validated', ['mailer_from' => $this->mailerFrom]);
    
            $this->logger->info('Preparing to send email', [
                'from' => $this->mailerFrom,
                'to' => $recipient->getEmail(),
            ]);
            $email = (new Email())
                ->from(new Address($this->mailerFrom, $this->mailerFromName))
                ->to($recipient->getEmail())
                ->subject('Partage de logement')
                ->html('<p>Bonjour, voici un logement qui pourrait vous intéresser : ' . $logement->getAdresse() . '</p>');
    
            $this->logger->info('Sending email');
            $mailer->send($email);
            $this->logger->info('Email sent successfully');
    
            return $this->json(['success' => true, 'message' => 'Logement partagé avec succès !']);
        } catch (\Throwable $e) {
            $this->logger->error('Error sharing logement', [
                'error' => $e->getMessage(),
                'logement_id' => $logement->getId(),
                'user_id' => $userId ?? 'unknown',
                'stack_trace' => $e->getTraceAsString(),
            ]);
            return $this->json(['error' => 'Failed to share logement: ' . $e->getMessage()], 500);
        }
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
    
        $logementsWithReactions = $this->addReactionData($logements);
    
        return $this->render('Client/logement/index.html.twig', [
            'logements' => $logementsWithReactions,
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
    #[Route('/logement/{id}/react', name: 'app_logement_react', methods: ['POST'])]
    public function react(Request $request, Logement $logement, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user) {
            $this->addFlash('error', 'Vous devez être connecté pour réagir.');
            return $this->redirectToRoute('app_logement_index', [], Response::HTTP_SEE_OTHER);
        }
    
        $emoji = $request->request->get('emoji');
        $allowedEmojis = ['👍', '❤️', '😢', '😡', '👎'];
        if (!in_array($emoji, $allowedEmojis)) {
            $this->addFlash('error', 'Emoji non valide.');
            return $this->redirectToRoute('app_logement_index', [], Response::HTTP_SEE_OTHER);
        }
    
        $logement->addEmoji($user->getUserIdentifier(), $emoji);
        $entityManager->flush();
    
        $this->addFlash('success', 'Réaction mise à jour avec succès !');
        return $this->redirectToRoute('app_logement_index', [], Response::HTTP_SEE_OTHER);
    }
    #[Route('/test-email', name: 'app_test_email', methods: ['GET'])]
    public function testEmail(MailerInterface $mailer): Response
    {
        try {
            $email = (new Email())
                ->from(new Address($this->mailerFrom, $this->mailerFromName))
                ->to('oumeimatibaoui@gmai.com') // Replace with a valid email
                ->subject('Test Email')
                ->html('<p>This is a test email.</p>');
    
            $mailer->send($email);
    
            return new Response('Email sent successfully!');
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Test email failed', ['error' => $e->getMessage()]);
            return new Response('Failed to send email: ' . $e->getMessage(), 500);
        }
    }
    #[Route('/search/{query}', name: 'app_logement_search', methods: ['GET'])]
    public function search(string $query, LogementRepository $logementRepository): JsonResponse
    {
        try {
            $logements = $logementRepository->findAll();
            $this->logger->info('Logements retrieved', ['count' => count($logements)]);

            if (empty($logements)) {
                $this->logger->info('No logements found');
                return $this->json(['results' => [], 'summary' => 'Aucun logement disponible'], 200);
            }

            $results = $this->geminiService->interpretQuery($query, $logements);
            $this->logger->info('Gemini result', ['results' => $results]);

            if (empty($results)) {
                $this->logger->info('No matching logements found');
                return $this->json(['results' => [], 'summary' => 'Aucun logement correspondant trouvé'], 200);
            }

            $resultData = [];
            foreach ($results as $result) {
                $logement = $logementRepository->find($result['id']);
                if ($logement) {
                    $resultData[] = [
                        'id' => $logement->getId(),
                        'adresse' => $logement->getAdresse(),
                        'description' => $logement->getDescription(),
                        'prix' => $logement->getPrix(),
                        'nbrChambre' => $logement->getNbrChambre(),
                        'type' => $logement->getType(),
                        'photo' => $logement->getImageLogements()->first() ? '/Uploads/photos/' . $logement->getImageLogements()->first()->getUrl() : '/assets/images/property-03.jpg',
                        'reason' => $result['reason'],
                    ];
                }
            }

            if (empty($resultData)) {
                $this->logger->info('No valid logements found after processing');
                return $this->json(['results' => [], 'summary' => 'Aucun logement valide trouvé'], 200);
            }
            usort($resultData, function ($a, $b) {
                return $b['reactionScore'] <=> $a['reactionScore'];
            });
            $summary = $this->geminiService->summarizeResults($logementRepository->find($results[0]['id']), $query);
          
            $this->logger->info('Logements selected', [
                'query' => $query,
                'count' => count($resultData),
                'results' => $resultData,
            ]);

            return $this->json([
                'results' => $resultData,
                'summary' => $summary,
            ], 200);
        } catch (\Exception $e) {
            $this->logger->error('Error in search method', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->json(['error' => 'Erreur lors de la recherche'], 500);
        }
    }
}