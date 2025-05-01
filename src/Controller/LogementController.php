<?php
namespace App\Controller;
use App\Entity\Options;
use App\Enums\RoleUtilisateur;
use App\Service\GeminiService;
use CURLFile;
use Dompdf\Dompdf;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use App\Entity\ImageLogement;
use App\Entity\Logement;
use Doctrine\Persistence\ManagerRegistry as PersistenceManagerRegistry;
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
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

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
    #[Route('/', name: 'app_logement_index', methods: ['GET'])]
    public function index(LogementRepository $logementRepository, Security $security): Response
    {
        try {
            // Get the current user
            $user = $security->getUser();
    
            // Initialize logements array
            $logements = [];
    
            // Check roles and fetch logements accordingly
            if ($security->isGranted('ROLE_ADMIN') && $user) {
                // Admins get all logements
                $logements = $logementRepository->findAll();
            } elseif ($security->isGranted('ROLE_PROPRIETAIRE') && $user) {
                // Proprietaires get only their own logements
                $logements = $logementRepository->findBy(['utilisateur_cin' => $user]);
            } else {
                // Default: Other users (including anonymous) get all logements
                $logements = $logementRepository->findAll();
            }
    
            // Check if no logements are found
            if (empty($logements)) {
                $this->logger->info('No logements found for index', [
                    'user' => $user ? $user->getUserIdentifier() : 'anonymous',
                    'roles' => $user ? $user->getRoles() : []
                ]);
    
                return $this->render('Client/logement/index.html.twig', [
                    'logements' => [],
                    'filter' => [
                        'type' => null,
                        'prix' => null,
                        'nbrChambre' => null,
                        'adresse' => null,
                    ],
                    'geocodeError' => false,
                    'error' => 'No logements found.',
                ]);
            }
    
            // Add reaction data to logements
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
        } catch (\Exception $e) {
            $this->logger->error('Index error', ['error' => $e->getMessage()]);
            return $this->render('Client/logement/index.html.twig', [
                'logements' => [],
                'filter' => [
                    'type' => null,
                    'prix' => null,
                    'nbrChambre' => null,
                    'adresse' => null,
                ],
                'geocodeError' => false,
                'error' => 'An error occurred while loading the logements.',
            ]);
        }
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
    public function share(
        Request $request,
        Logement $logement,
        UtilisateurRepository $utilisateurRepository,
        MailerInterface $mailer,
        PersistenceManagerRegistry $doctrine,
        UrlGeneratorInterface $urlGenerator
    ): JsonResponse {
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

            // Generate the URL for the logement
            $logementUrl = $urlGenerator->generate(
                'app_logement_show',
                ['id' => $logement->getId()],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            // Compute the recipient's name beforehand
            $recipientName = $recipient->getNom() ?: 'Utilisateur';
            $sharerName = $user->getNom() ?: $user->getUserIdentifier();

            // Prepare the personalized HTML email content
            $emailContent = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <style>
        body {
            font-family: Arial, sans-serif;
            color: #333;
            line-height: 1.6;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            background-color: #ffffff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        h2 {
            color:rgb(155, 176, 215);
            text-align: center;
        }
        p {
            margin: 10px 0;
        }
        .logement-details {
            background-color: #f9f9f9;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
        }
        .logement-details p {
            margin: 5px 0;
        }
        .button {
            display: inline-block;
            padding: 10px 20px;
            background-color:rgb(151, 166, 192);
            color: #ffffff;
            text-decoration: none;
            border-radius: 5px;
            text-align: center;
            margin: 10px 0;
        }
        .button:hover {
            background-color:rgb(146, 157, 179);
        }
        .footer {
            text-align: center;
            font-size: 12px;
            color: #777;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Un logement qui pourrait vous intéresser !</h2>
        <p>Bonjour {$recipientName},</p>
        <p>L'utilisateur {$sharerName} a partagé un logement avec vous.</p>

        <div class="logement-details">
            <p><strong>Adresse :</strong> {$logement->getAdresse()}</p>
            <p><strong>Prix :</strong> {$logement->getPrix()} €</p>
            <p><strong>Nombre de chambres :</strong> {$logement->getNbrChambre()}</p>
            <p><strong>Type :</strong> {$logement->getType()}</p>
        </div>

        <p>
            <a href="{$logementUrl}" class="button">Voir le logement</a>
        </p>

        <p>Merci de votre intérêt !</p>

        <div class="footer">
            <p>Ceci est un email automatique, merci de ne pas répondre directement.</p>
        </div>
    </div>
</body>
</html>
HTML;
    
            $this->logger->info('Preparing to send email', [
                'from' => $this->mailerFrom,
                'to' => $recipient->getEmail(),
            ]);
            $email = (new Email())
                ->from(new Address($this->mailerFrom, $this->mailerFromName))
                ->to($recipient->getEmail())
                ->subject('Partage de logement - Découvrez ce bien !')
                ->html($emailContent);
    
            $this->logger->info('Sending email');
            $mailer->send($email);
            $this->logger->info('Email sent successfully');

            // Increment the share count
            $logement->setShareCount($logement->getShareCount() + 1);
            $doctrine->getManager()->flush();
    
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
    }    #[Route('/dashboard', name: 'app_logement_dashboard')]
    public function dashboard(
        LogementRepository $logementRepository,
        UtilisateurRepository $utilisateurRepository,
        Security $security
    ): Response {
        // Get the current user
        $user = $security->getUser();

        // Fetch logements based on user role
        if ($security->isGranted('ROLE_ADMIN') && $user) {
            $logements = $logementRepository->findAllSortedByIdDesc();
        } elseif ($security->isGranted('ROLE_PROPRIETAIRE') && $user) {
            $logements = $logementRepository->findAllSortedByIdDesc();
        } else {
            $logements = $logementRepository->findAllSortedByIdDesc();
        }

        // Add reaction data to logements for the cards
        $logementsWithReactions = $this->addReactionData($logements);

        // Initialize variables for the cards
        $mostJadore = ['logement' => null, 'count' => 0, 'owner' => 'N/A'];
        $mostLikes = ['logement' => null, 'count' => 0, 'owner' => 'N/A'];
        $mostDislikes = ['logement' => null, 'count' => 0, 'owner' => 'N/A'];

        // Find logements with the most J'adore, Likes, and Dislikes
        foreach ($logementsWithReactions as $logementData) {
            $logement = $logementData['entity'];
            $reactionCounts = $logementData['reactionCounts'];

            // J'adore (❤️)
            $jadoreCount = $reactionCounts['❤️'] ?? 0;
            if ($jadoreCount > $mostJadore['count']) {
                $mostJadore = [
                    'logement' => $logement,
                    'count' => $jadoreCount,
                    'owner' => $logement->getUtilisateurCin() ? $logement->getUtilisateurCin()->getNom() : 'N/A'
                ];
            }

            // Likes (👍)
            $likeCount = $reactionCounts['👍'] ?? 0;
            if ($likeCount > $mostLikes['count']) {
                $mostLikes = [
                    'logement' => $logement,
                    'count' => $likeCount,
                    'owner' => $logement->getUtilisateurCin() ? $logement->getUtilisateurCin()->getNom() : 'N/A'
                ];
            }

            // Dislikes (👎)
            $dislikeCount = $reactionCounts['👎'] ?? 0;
            if ($dislikeCount > $mostDislikes['count']) {
                $mostDislikes = [
                    'logement' => $logement,
                    'count' => $dislikeCount,
                    'owner' => $logement->getUtilisateurCin() ? $logement->getUtilisateurCin()->getNom() : 'N/A'
                ];
            }
        }

        // Calculate interaction totals for the chart
        $interactionData = $this->calculateInteractionData($logementRepository, $security->isGranted('ROLE_PROPRIETAIRE') && $user ? $user : null);

        // Most active property owners for the donut chart
        $owners = $utilisateurRepository->findAll();
        $ownerPostCounts = [];
        foreach ($owners as $owner) {
            $postCount = count($owner->getLogements());
            if ($postCount > 0) {
                $ownerPostCounts[] = [
                    'owner' => $owner->getNom(),
                    'postCount' => $postCount,
                ];
            }
        }
        usort($ownerPostCounts, fn($a, $b) => $b['postCount'] <=> $a['postCount']);

        return $this->render('statitique_logement/index.html.twig', [
            'mostJadore' => $mostJadore,
            'mostLikes' => $mostLikes,
            'mostDislikes' => $mostDislikes,
            'interactionData' => $interactionData,
            'ownerPostCounts' => $ownerPostCounts,
        ]);
    }

    #[Route('/dashboard/generate-report', name: 'app_dashboard_generate_report')]
    public function generateReport(
        LogementRepository $logementRepository,
        UtilisateurRepository $utilisateurRepository,
        Security $security
    ): Response {
        $user = $security->getUser();
        if ($security->isGranted('ROLE_ADMIN') && $user) {
            $logements = $logementRepository->findAllSortedByIdDesc();
        } elseif ($security->isGranted('ROLE_PROPRIETAIRE') && $user) {
            $logements = $logementRepository->findAllSortedByIdDesc();
        } else {
            $logements = $logementRepository->findAllSortedByIdDesc();
        }

        $logementsWithReactions = $this->addReactionData($logements);
        $mostJadore = ['logement' => null, 'count' => 0, 'owner' => 'N/A'];
        $mostLikes = ['logement' => null, 'count' => 0, 'owner' => 'N/A'];
        $mostDislikes = ['logement' => null, 'count' => 0, 'owner' => 'N/A'];

        foreach ($logementsWithReactions as $logementData) {
            $logement = $logementData['entity'];
            $reactionCounts = $logementData['reactionCounts'];

            $jadoreCount = $reactionCounts['❤️'] ?? 0;
            if ($jadoreCount > $mostJadore['count']) {
                $mostJadore = [
                    'logement' => $logement,
                    'count' => $jadoreCount,
                    'owner' => $logement->getUtilisateurCin() ? $logement->getUtilisateurCin()->getNom() : 'N/A'
                ];
            }

            $likeCount = $reactionCounts['👍'] ?? 0;
            if ($likeCount > $mostLikes['count']) {
                $mostLikes = [
                    'logement' => $logement,
                    'count' => $likeCount,
                    'owner' => $logement->getUtilisateurCin() ? $logement->getUtilisateurCin()->getNom() : 'N/A'
                ];
            }

            $dislikeCount = $reactionCounts['👎'] ?? 0;
            if ($dislikeCount > $mostDislikes['count']) {
                $mostDislikes = [
                    'logement' => $logement,
                    'count' => $dislikeCount,
                    'owner' => $logement->getUtilisateurCin() ? $logement->getUtilisateurCin()->getNom() : 'N/A'
                ];
            }
        }

        // Calculate interaction totals for the report
        $interactionData = $this->calculateInteractionData($logementRepository, $security->isGranted('ROLE_PROPRIETAIRE') && $user ? $user : null);

        $owners = $utilisateurRepository->findAll();
        $ownerPostCounts = [];
        foreach ($owners as $owner) {
            $postCount = count($owner->getLogements());
            if ($postCount > 0) {
                $ownerPostCounts[] = [
                    'owner' => $owner->getNom(),
                    'postCount' => $postCount,
                ];
            }
        }
        usort($ownerPostCounts, fn($a, $b) => $b['postCount'] <=> $a['postCount']);

        // Render the HTML content for the PDF
        $html = $this->renderView('statitique_logement/report.html.twig', [
            'mostJadore' => $mostJadore,
            'mostLikes' => $mostLikes,
            'mostDislikes' => $mostDislikes,
            'interactionData' => $interactionData,
            'ownerPostCounts' => $ownerPostCounts,
            'currentDate' => new \DateTime(),
        ]);


        $dompdf = new Dompdf();

        // Load the HTML content
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // Output the PDF as a response
        $pdfOutput = $dompdf->output();
        $response = new Response($pdfOutput);
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('Content-Disposition', 'attachment; filename="dashboard-report-' . date('Y-m-d') . '.pdf"');

        return $response;
    }

    /**
     * Calculate total interactions for the top 7 logements by total interactions.
     *
     * @param LogementRepository $logementRepository
     * @param Utilisateur|null $user
     * @return array
     */
    private function calculateInteractionData(LogementRepository $logementRepository, ?Utilisateur $user = null): array
    {
        // Fetch all logements (filtered by user if provided)
        $logements = $logementRepository->findAll();

        // If no logements, return empty data
        if (empty($logements)) {
            return [
                'labels' => [],
                'jadoreCounts' => [],
                'likesCounts' => [],
                'dislikesCounts' => [],
                'sharesCounts' => [],
                'logements' => [],
            ];
        }

        // Calculate interactions for each logement and compute total interactions
        $logementsWithInteractions = [];
        foreach ($logements as $logement) {
            $emojis = $logement->getEmojis() ?? [];
            $jadoreCount = 0;
            $likesCount = 0;
            $dislikesCount = 0;

            foreach ($emojis as $emoji) {
                if ($emoji === '❤️') {
                    $jadoreCount++;
                } elseif ($emoji === '👍') {
                    $likesCount++;
                } elseif ($emoji === '👎') {
                    $dislikesCount++;
                }
            }

            $sharesCount = $logement->getShareCount();
            $totalInteractions = $jadoreCount + $likesCount + $dislikesCount + $sharesCount;

            $logementsWithInteractions[] = [
                'logement' => $logement,
                'id' => $logement->getId(),
                'jadore' => $jadoreCount,
                'likes' => $likesCount,
                'dislikes' => $dislikesCount,
                'shares' => $sharesCount,
                'totalInteractions' => $totalInteractions,
            ];
        }

        // Sort logements by total interactions in descending order
        usort($logementsWithInteractions, fn($a, $b) => $b['totalInteractions'] <=> $a['totalInteractions']);

        // Take the top 7 logements
        $topLogements = array_slice($logementsWithInteractions, 0, 7);

        // Prepare data for the chart and report
        $labels = [];
        $jadoreCounts = [];
        $likesCounts = [];
        $dislikesCounts = [];
        $sharesCounts = [];

        foreach ($topLogements as $item) {
            $labels[] = "Logement {$item['id']}";
            $jadoreCounts[] = $item['jadore'];
            $likesCounts[] = $item['likes'];
            $dislikesCounts[] = $item['dislikes'];
            $sharesCounts[] = $item['shares'];
        }

        return [
            'labels' => $labels,
            'jadoreCounts' => $jadoreCounts,
            'likesCounts' => $likesCounts,
            'dislikesCounts' => $dislikesCounts,
            'sharesCounts' => $sharesCounts,
            'logements' => array_column($topLogements, 'logement'), // For PDF report details
        ];
    }



   


    // private function addReactionData(array $logements): array
    // {
    //     $logementsWithReactions = [];
    //     foreach ($logements as $logement) {
    //         $emojis = $logement->getEmojis() ?? [];
    //         $reactionCounts = [
    //             '❤️' => 0,
    //             '👍' => 0,
    //             '👎' => 0,
    //         ];

    //         foreach ($emojis as $emoji) {
    //             if (isset($reactionCounts[$emoji])) {
    //                 $reactionCounts[$emoji]++;
    //             }
    //         }

    //         $logementsWithReactions[] = [
    //             'entity' => $logement,
    //             'reactionCounts' => $reactionCounts,
    //         ];
    //     }

    //     return $logementsWithReactions;
    // }
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
//     #[Route('/dashboard', name: 'app_logement_dashboard', methods: ['GET'])]
//     public function dashboard(LogementRepository $logementRepository, UtilisateurRepository $utilisateurRepository): Response
// {
//     // Get all logements
//     $logements = $logementRepository->findAllSortedByIdDesc();
//     $logementsWithReactions = $this->addReactionData($logements);

//     // Initialize variables for the cards
//     $mostJadore = ['logement' => null, 'count' => 0, 'owner' => 'N/A'];
//     $mostLikes = ['logement' => null, 'count' => 0, 'owner' => 'N/A'];
//     $mostDislikes = ['logement' => null, 'count' => 0, 'owner' => 'N/A'];

//     // Find logements with the most J'adore, Likes, and Dislikes
//     foreach ($logementsWithReactions as $logementData) {
//         $logement = $logementData['entity'];
//         $reactionCounts = $logementData['reactionCounts'];
        
//         // J'adore (❤️)
//         $jadoreCount = $reactionCounts['❤️'] ?? 0;
//         if ($jadoreCount > $mostJadore['count']) {
//             $mostJadore = [
//                 'logement' => $logement,
//                 'count' => $jadoreCount,
//                 'owner' => $logement->getUtilisateurCin() ? $logement->getUtilisateurCin()->getNom() : 'N/A'
//             ];
//         }

//         // Likes (👍)
//         $likeCount = $reactionCounts['👍'] ?? 0;
//         if ($likeCount > $mostLikes['count']) {
//             $mostLikes = [
//                 'logement' => $logement,
//                 'count' => $likeCount,
//                 'owner' => $logement->getUtilisateurCin() ? $logement->getUtilisateurCin()->getNom() : 'N/A'
//             ];
//         }

//         // Dislikes (👎)
//         $dislikeCount = $reactionCounts['👎'] ?? 0;
//         if ($dislikeCount > $mostDislikes['count']) {
//             $mostDislikes = [
//                 'logement' => $logement,
//                 'count' => $dislikeCount,
//                 'owner' => $logement->getUtilisateurCin() ? $logement->getUtilisateurCin()->getNom() : 'N/A'
//             ];
//         }
//     }

//     // Weekly interactions for the line chart (last 8 weeks)
//     $weeklyInteractions = [];
//     $currentDate = new \DateTime();
//     for ($i = 7; $i >= 0; $i--) {
//         $weekStart = (clone $currentDate)->modify("-{$i} weeks")->setTime(0, 0, 0);
//         $weekEnd = (clone $weekStart)->modify('+6 days')->setTime(23, 59, 59);
//         $weekLabel = $weekStart->format('M d');

//         $jadoreCount = 0;
//         $likeCount = 0;
//         $dislikeCount = 0;
//         $shareCount = 0;

//         foreach ($logements as $logement) {
//             $emojis = $logement->getEmojis() ?? [];
//             foreach ($emojis as $userCin => $emoji) {
//                 if ($emoji === '❤️') $jadoreCount++;
//                 if ($emoji === '👍') $likeCount++;
//                 if ($emoji === '👎') $dislikeCount++;
//             }
//             $shareCount += $logement->getShareCount();
//         }

//         $weeklyInteractions[] = [
//             'week' => $weekLabel,
//             'jadore' => $jadoreCount,
//             'likes' => $likeCount,
//             'dislikes' => $dislikeCount,
//             'shares' => $shareCount,
//         ];
//     }

//     // Most active property owner for the donut chart
//     $owners = $utilisateurRepository->findAll();
//     $ownerPostCounts = [];
//     foreach ($owners as $owner) {
//         $postCount = count($owner->getLogements());
//         if ($postCount > 0) {
//             $ownerPostCounts[] = [
//                 'owner' => $owner->getNom(),
//                 'postCount' => $postCount,
//             ];
//         }
//     }
//     // Sort by post count descending
//     usort($ownerPostCounts, fn($a, $b) => $b['postCount'] <=> $a['postCount']);

//     return $this->render('statitique_logement/index.html.twig', [
//         'mostJadore' => $mostJadore,
//         'mostLikes' => $mostLikes,
//         'mostDislikes' => $mostDislikes,
//         'weeklyInteractions' => $weeklyInteractions,
//         'ownerPostCounts' => $ownerPostCounts,
//     ]);
// }
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
    
        $logement->addEmoji($user->getCin(), $emoji);
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