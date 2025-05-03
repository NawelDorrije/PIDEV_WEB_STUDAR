<?php

namespace App\Controller\Admin;

use App\Entity\ActivityLog;
use Symfony\Component\String\Slugger\SluggerInterface;
use Endroid\QrCode\QrCode as EndroidQrCode; // Use the alias
use Endroid\QrCode\Writer\PngWriter;
use libphonenumber\PhoneNumberUtil;
use libphonenumber\PhoneNumberFormat;
use Psr\Log\LoggerInterface;
use App\Entity\Utilisateur;
use App\Enums\RoleUtilisateur;
use App\Repository\UtilisateurRepository;
use App\Repository\ActivityLogRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use OTPHP\TOTP;
#[Route('/admin')]
class DashboardController extends AbstractController
{
    private $entityManager;
    private $tokenStorage;
    private $logger;
   

    public function __construct(EntityManagerInterface $entityManager, TokenStorageInterface $tokenStorage, LoggerInterface $logger)
    {
        $this->entityManager = $entityManager;
        $this->tokenStorage = $tokenStorage;
        $this->logger = $logger;
    }
    #[Route('/user/{cin}/qr-code', name: 'app_admin_user_qr_code', methods: ['GET'])]
    public function generateQrCode(string $cin, UtilisateurRepository $utilisateurRepository, LoggerInterface $logger): Response
    {
        $logger->debug('Generating QR code for CIN: ' . $cin);

        // Find the user
        $user = $utilisateurRepository->findOneBy(['cin' => $cin]);
        if (!$user) {
            $logger->error('User not found for CIN: ' . $cin);
            throw $this->createNotFoundException('Utilisateur non trouvé');
        }

        // Get and clean the phone number
        $phoneNumber = $user->getNumTel();
        if (!$phoneNumber) {
            $logger->warning('Phone number not defined for CIN: ' . $cin);
            // Return a placeholder image or message
            $placeholderPath = $this->getParameter('kernel.project_dir') . '/public/images/qr-placeholder.png';
            if (!file_exists($placeholderPath)) {
                $logger->error('Placeholder image not found at: ' . $placeholderPath);
                return new Response('QR Code unavailable (no phone number)', Response::HTTP_OK);
            }
            return new Response(
                file_get_contents($placeholderPath),
                Response::HTTP_OK,
                ['Content-Type' => 'image/png']
            );
        }

        // Clean the phone number (remove spaces, dashes, etc.)
        $phoneNumber = preg_replace('/[^0-9+]/', '', $phoneNumber);
        $logger->debug('Cleaned phone number for CIN ' . $cin . ': ' . $phoneNumber);

        // Initialize libphonenumber
        $phoneUtil = PhoneNumberUtil::getInstance();
        try {
            $parsedNumber = $phoneUtil->parse($phoneNumber, 'TN'); // Default to Tunisia
            if (!$phoneUtil->isValidNumber($parsedNumber)) {
                throw new \Exception('Numéro de téléphone invalide');
            }
            $formattedNumber = $phoneUtil->format($parsedNumber, PhoneNumberFormat::E164);
            $logger->debug('Formatted phone number: ' . $formattedNumber);
        } catch (\Exception $e) {
            $logger->warning('Invalid phone number for QR code', [
                'cin' => $cin,
                'phone' => $phoneNumber,
                'error' => $e->getMessage(),
            ]);
            $placeholderPath = $this->getParameter('kernel.project_dir') . '/public/images/qr-placeholder.png';
            if (!file_exists($placeholderPath)) {
                $logger->error('Placeholder image not found at: ' . $placeholderPath);
                return new Response('QR Code unavailable (invalid phone number)', Response::HTTP_OK);
            }
            return new Response(
                file_get_contents($placeholderPath),
                Response::HTTP_OK,
                ['Content-Type' => 'image/png']
            );
        }

        // Generate WhatsApp link
        $whatsAppLink = sprintf('https://wa.me/%s', ltrim($formattedNumber, '+'));
        $logger->debug('WhatsApp link: ' . $whatsAppLink);

        // Generate QR code
        try {
            $qrCode = new EndroidQrCode($whatsAppLink); // Use the alias EndroidQrCode

            $writer = new PngWriter();
            $result = $writer->write(
                $qrCode,
                null, // Logo path (optional, null if not used)
                null, // Label (optional, null if not used)
                [
                    'size' => 200, // Set the size here
                    'margin' => 10, // Set the margin here
                ]
            );

            $logger->info('QR code generated successfully for CIN: ' . $cin);
            return new StreamedResponse(
                function () use ($result) {
                    echo $result->getString();
                },
                Response::HTTP_OK,
                [
                    'Content-Type' => $result->getMimeType(),
                    'Content-Disposition' => 'inline; filename="whatsapp-qr-' . $cin . '.png"',
                ]
            );
        } catch (\Exception $e) {
            $logger->error('Failed to generate QR code', [
                'cin' => $cin,
                'error' => $e->getMessage(),
            ]);
            $placeholderPath = $this->getParameter('kernel.project_dir') . '/public/images/qr-placeholder.png';
            if (!file_exists($placeholderPath)) {
                $logger->error('Placeholder image not found at: ' . $placeholderPath);
                return new Response('QR Code unavailable (generation failed)', Response::HTTP_OK);
            }
            return new Response(
                file_get_contents($placeholderPath),
                Response::HTTP_OK,
                ['Content-Type' => 'image/png']
            );
        }
    }
    #[Route('/dashboard', name: 'app_admin_dashboard')]
    public function dashboard(
        UtilisateurRepository $utilisateurRepository,
        PaginatorInterface $paginator,
        Request $request
    ): Response {

        $roleFilter = $request->query->get('role');
        $blockedFilter = $request->query->get('blocked');

        $queryBuilder = $utilisateurRepository->createQueryBuilder('u');

        if ($roleFilter) {
            $queryBuilder->andWhere('u.role = :role')
                ->setParameter('role', RoleUtilisateur::from($roleFilter));
        }

        if ($blockedFilter !== null) {
            $queryBuilder->andWhere('u.blocked = :blocked')
                ->setParameter('blocked', (bool)$blockedFilter);
        }

        $queryBuilder->orderBy('u.nom', 'ASC');

        $utilisateurs = $paginator->paginate(
            $queryBuilder->getQuery(),
            $request->query->getInt('page', 1),
            5
        );

        return $this->render('admin/dashboard.html.twig', [
            'utilisateur' => $utilisateurs,
            'roleFilter' => $roleFilter,
            'blockedFilter' => $blockedFilter,
        ]);
    }

    #[Route(
        '/{cin}', 
        name: 'app_dashboard_detailsUtilisateur',
        requirements: ['cin' => '\d{8}'],
        defaults: ['cin' => null],
        methods: ['GET']
    )]
    public function detailsUtilisateur(Utilisateur $utilisateur = null): Response
    {
        if (!$utilisateur && $this->getUser()) {
            $utilisateur = $this->getUser();
        }
    
        if (!$utilisateur) {
            throw $this->createNotFoundException('User not found');
        }
    
        return $this->render('admin/detailsUtilisateur.html.twig', [
            'utilisateur' => $utilisateur,
        ]);
    }

    #[Route('/statistique', name: 'app_admin_statistique')]
    public function statistique(): Response
    {
        
        return $this->render('admin/statistique.html.twig');
    }

    #[Route('/api/user-stats', name: 'app_admin_api_user_stats')]
public function getUserStats(UtilisateurRepository $utilisateurRepository): JsonResponse
{

    $userStats = $utilisateurRepository->getUserCountByRole();

    $labels = ['Étudiant', 'Transporteur', 'Propriétaire'];
    $data = [0, 0, 0];
    $colors = ['#f35525', '#4e73df', '#1cc88a'];

    foreach ($userStats as $stat) {
        if (!isset($stat['role'], $stat['count'])) {
            continue;
        }
        $role = strtolower($stat['role']);
        if ($role === 'étudiant') {
            $data[0] = (int) $stat['count'];
        } elseif ($role === 'transporteur') {
            $data[1] = (int) $stat['count'];
        } elseif ($role === 'propriétaire') {
            $data[2] = (int) $stat['count'];
        }
    }

    return $this->json([
        'labels' => $labels,
        'data' => $data,
        'colors' => $colors
    ]);
}
    #[Route('/debug/user-stats', name: 'app_debug_user_stats')]
public function debugUserStats(UtilisateurRepository $utilisateurRepository): JsonResponse
{
    $stats = $utilisateurRepository->getUserCountByRole();
    return $this->json($stats);
}
#[Route('/api/emotion-stats', name: 'app_admin_api_emotion_stats')]
public function getEmotionStats(Request $request, UtilisateurRepository $utilisateurRepository, LoggerInterface $logger): JsonResponse
{
    $roleFilter = $request->query->get('role');

    $queryBuilder = $utilisateurRepository->createQueryBuilder('u')
        ->select('u.satisfactionEmotion AS emotion, COUNT(u.cin) AS count')
        ->groupBy('u.satisfactionEmotion');

    if ($roleFilter) {
        $queryBuilder->andWhere('u.role = :role')
            ->setParameter('role', $roleFilter);
    }

    $emotionStats = $queryBuilder->getQuery()->getResult();

    $logger->info('Raw emotion stats', ['stats' => $emotionStats, 'roleFilter' => $roleFilter]);

    $labels = [];
    $data = [];
    $colors = [];

    $emotionColors = [
        'happy' => '#f35525',
        'sad' => '#4e73df',
        'neutral' => '#1cc88a',
        'angry' => '#e74a3b',
        'surprised' => '#f6c23e',
        'unknown' => '#6c757d',
    ];

    foreach ($emotionStats as $stat) {
        $emotion = strtolower($stat['emotion'] ?? 'unknown');
        $count = (int)$stat['count'];
        if ($count > 0) {
            $labels[] = ucfirst($emotion ?: 'Inconnu');
            $data[] = $count;
            $colors[] = $emotionColors[$emotion] ?? '#6c757d';
            $logger->debug('Processing emotion', ['emotion' => $emotion, 'count' => $count]);
        }
    }

    if (empty($data)) {
        $labels = ['Aucune donnée'];
        $data = [1];
        $colors = ['#6c757d'];
    }

    $logger->info('Processed emotion stats', ['labels' => $labels, 'data' => $data]);
    return $this->json([
        'labels' => $labels,
        'data' => $data,
        'colors' => $colors,
    ]);
}
    #[Route('/parametre', name: 'app_admin_parametre')]
    public function parametre(ActivityLogRepository $activityLogRepository): Response
    {

        $user = $this->getUser();
        if (!$user->getTheme() || !in_array($user->getTheme(), ['light', 'dark', 'custom'])) {
            $user->setTheme('light');
            $this->entityManager->persist($user);
            $this->entityManager->flush();
        }

        $activityLogs = $activityLogRepository->findRecentByUser($user->getCin());

        return $this->render('admin/parametre.html.twig', [
            'activityLogs' => $activityLogs,
        ]);
    }

    #[Route('/parametre/theme', name: 'app_admin_parametre_theme', methods: ['POST'])]
    public function updateTheme(
        Request $request,
        EntityManagerInterface $em,
        ActivityLogRepository $activityLogRepository,
        SessionInterface $session,
        UtilisateurRepository $utilisateurRepository
    ): Response {

        $user = $this->getUser();
        $theme = $request->request->get('theme');

        if (!in_array($theme, ['light', 'dark', 'custom'])) {
            $this->addFlash('error', 'Thème invalide.');
            return $this->redirectToRoute('app_admin_parametre');
        }

        $user = $utilisateurRepository->find($user->getCin());
        $user->setTheme($theme);
        $em->persist($user);
        $em->flush();

        $log = new ActivityLog();
        $log->setUser($user);
        $log->setAction('Thème modifié');
        $log->setDetails(sprintf('Nouveau thème : %s', $theme));
        $em->persist($log);
        $em->flush();

        $token = new UsernamePasswordToken($user, 'main', $user->getRoles());
        $this->tokenStorage->setToken($token);
        $session->set('_security_main', serialize($token));
        $session->migrate(true);

        $this->addFlash('success', 'Thème mis à jour avec succès.');
        return $this->redirectToRoute('app_admin_parametre');
    }

    #[Route('/parametre/password', name: 'app_admin_parametre_password', methods: ['POST'])]
    public function updatePassword(
        Request $request,
        EntityManagerInterface $em,
        SessionInterface $session,
        UtilisateurRepository $utilisateurRepository
    ): Response {

        $user = $this->getUser();
        $oldPassword = $request->request->get('oldPassword');
        $newPassword = $request->request->get('newPassword');
        $confirmPassword = $request->request->get('confirmPassword');

        if ($newPassword !== $confirmPassword) {
            $this->addFlash('error', 'Les nouveaux mots de passe ne correspondent pas.');
            return $this->redirectToRoute('app_admin_parametre');
        }

        $user = $utilisateurRepository->find($user->getCin());

        if (!password_verify($oldPassword, $user->getMdp())) {
            $this->addFlash('error', 'L\'ancien mot de passe est incorrect.');
            return $this->redirectToRoute('app_admin_parametre');
        }

        $user->setMdp(password_hash($newPassword, PASSWORD_BCRYPT));
        $em->persist($user);
$em->flush();

        $log = new ActivityLog();
        $log->setUser($user);
        $log->setAction('Mot de passe modifié');
        $log->setDetails('Vous avez modifié votre mot de passe.');
        $em->persist($log);
        $em->flush();

        $token = new UsernamePasswordToken($user, 'main', $user->getRoles());
        $this->tokenStorage->setToken($token);
        $session->set('_security_main', serialize($token));
        $session->migrate(true);

        $this->addFlash('success', 'Mot de passe mis à jour avec succès.');
        return $this->redirectToRoute('app_admin_parametre');
    }
    #[Route('/admin/profile/{cin}/modifier', name: 'app_admin_modifier_profile', methods: ['GET', 'POST'])]
    public function modifierProfile(
        Request $request,
        Utilisateur $utilisateur,
        EntityManagerInterface $entityManager,
        EntityManagerInterface $em,
        SluggerInterface $slugger
    ): Response {
        // Ensure the user is authorized to edit their own profile
        if ($this->getUser()->getCin() !== $utilisateur->getCin()) {
            $this->addFlash('error', 'Vous ne pouvez modifier que votre propre profil.');
            return $this->redirectToRoute('app_admin_parametre');
        }

        // Debug initial load
        dump('Initial load');

        // Handle form submission
        if ($request->isMethod('POST')) {
            dump('Form submitted');

            // Get form data
            $nom = $request->request->get('nom');
            $prenom = $request->request->get('prenom');
            $email = $request->request->get('email');
            $numTel = $request->request->get('numTel');
            $imageFile = $request->files->get('imageFile');

            // Basic validation
            if (empty($nom) || empty($prenom) || empty($email)) {
                dump('Validation failed: Required fields missing');
                $this->addFlash('error', 'Les champs nom, prénom et email sont obligatoires.');
                return $this->redirectToRoute('app_admin_parametre');
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                dump('Validation failed: Invalid email');
                $this->addFlash('error', 'L\'adresse email est invalide.');
                return $this->redirectToRoute('app_admin_parametre');
            }

            // Update user entity
            $utilisateur->setNom($nom);
            $utilisateur->setPrenom($prenom);
            $utilisateur->setEmail($email);
            $utilisateur->setNumTel($numTel ?: null); // Allow empty phone number

            // Handle image upload
            if ($imageFile) {
                dump('Image file detected');

                // Generate new filename
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();
                dump('New filename: '.$newFilename);

                try {
                    // Move the new image to the directory
                    $imageFile->move(
                        $this->getParameter('images_directory'),
                        $newFilename
                    );

                    // Delete old image if it exists
                    if ($utilisateur->getImage()) {
                        $oldImagePath = $this->getParameter('images_directory').'/'.$utilisateur->getImage();
                        if (file_exists($oldImagePath)) {
                            unlink($oldImagePath);
                            dump('Old image deleted');
                        }
                    }

                    // Set the new image filename
                    $utilisateur->setImage($newFilename);
                    dump('Image uploaded successfully');
                } catch (FileException $e) {
                    dump('Image upload failed: '.$e->getMessage());
                    $this->addFlash('error', 'Échec du téléchargement de l\'image : ' . $e->getMessage());
                    return $this->redirectToRoute('app_admin_parametre');
                }
            }

            // Persist changes to the database
            $entityManager->flush();
            dump('Profile updated successfully');
            $this->addFlash('success', 'Profil mis à jour avec succès.');
            $log = new ActivityLog();
        $log->setUser($utilisateur);
        $log->setAction('Profile modifié');
        $log->setDetails('Vous avez modifié votre profile.');
        $em->persist($log);
        $em->flush();

            return $this->redirectToRoute('app_admin_parametre');
        }

        // Render the parameters page (GET request)
        return $this->render('admin/parametre.html.twig', [
            'utilisateur' => $utilisateur,
        ]);
    }
    #[Route('/user/report', name: 'app_admin_user_report', methods: ['POST'])]
public function reportUser(
    Request $request,
    UtilisateurRepository $utilisateurRepository,
    MailerInterface $mailer,
    LoggerInterface $logger
): JsonResponse {

    try {
        $data = json_decode($request->getContent(), true);
        $logger->debug('Report request data', ['data' => $data]);

        $userId = $data['userId'] ?? null;
        $userEmail = $data['userEmail'] ?? null;
        $reason = $data['reason'] ?? null;

        if (!$userId || !$userEmail || !$reason) {
            $logger->warning('Missing report data', ['data' => $data]);
            return $this->json(['success' => false, 'message' => 'Données manquantes'], 400);
        }

        $user = $utilisateurRepository->find($userId);
        if (!$user) {
            $logger->warning('User not found', ['userId' => $userId]);
            return $this->json(['success' => false, 'message' => 'Utilisateur non trouvé'], 404);
        }
        if ($user->getEmail() !== $userEmail) {
            $logger->warning('Email mismatch', ['userId' => $userId, 'providedEmail' => $userEmail, 'actualEmail' => $user->getEmail()]);
            return $this->json(['success' => false, 'message' => 'Email incorrect'], 400);
        }

        // Send email
        try {
            $email = (new Email())
                ->from($_ENV['MAILER_FROM'] ?? 'studar21@gmail.com')
                ->to($userEmail)
                ->subject('Avertissement : Signalement de votre compte')
                ->html("<p>Bonjour {$user->getNom()},</p><p>Votre compte a été signalé pour la raison suivante :</p><p><strong>{$reason}</strong></p><p>Veuillez contacter l'administrateur pour plus d'informations.</p><p>Cordialement,<br>L'équipe Studar</p>");

            $mailer->send($email);
            $logger->info('Report email sent', ['to' => $userEmail, 'dsn' => $_ENV['MAILER_DSN'] ?? 'not set']);
        } catch (\Exception $e) {
            $logger->error('Failed to send report email', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'dsn' => $_ENV['MAILER_DSN'] ?? 'not set'
            ]);
            return $this->json(['success' => false, 'message' => 'Erreur lors de l\'envoi de l\'email: ' . $e->getMessage()], 500);
        }

        // Log the report action
        try {
            $log = new ActivityLog();
            $log->setUser($this->getUser());
            $log->setAction('Signalement utilisateur');
            $log->setDetails(sprintf('Utilisateur %s signalé pour : %s', $userId, 'reason'));
            $this->entityManager->persist($log);
            $this->entityManager->flush();
            $logger->info('Report logged', ['userId' => $userId]);
        } catch (\Exception $e) {
            $logger->error('Failed to log report', ['message' => $e->getMessage()]);
            return $this->json(['success' => false, 'message' => 'Erreur lors de l\'enregistrement du log: ' . $e->getMessage()], 500);
        }

        return $this->json(['success' => true, 'message' => 'Signalement envoyé avec succès']);
    } catch (\Exception $e) {
        $logger->error('Error in reportUser', [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'dsn' => $_ENV['MAILER_DSN'] ?? 'not set'
        ]);
        return $this->json(['success' => false, 'message' => 'Erreur serveur lors du signalement: ' . $e->getMessage()], 500);
    }
}
#[Route(
    '/{cin}', 
    name: 'app_admin_adminProfile',
    requirements: ['cin' => '\d{8}'], // Requires exactly 8 digits
    defaults: ['cin' => null], // Make it optional
    methods: ['GET']
)]
public function show(Utilisateur $utilisateur = null): Response
{
    // If no CIN provided and user is logged in, show their profile
    if (!$utilisateur && $this->getUser()) {
        $utilisateur = $this->getUser();
    }

    if (!$utilisateur) {
        throw $this->createNotFoundException('User not found');
    }

    return $this->render('admin/adminProfile.html.twig', [
        'utilisateur' => $utilisateur,
    ]);
}
#[Route('/user/{cin}/toggle-block', name: 'app_admin_user_toggle_block', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function toggleBlock(string $cin, UtilisateurRepository $utilisateurRepository): JsonResponse
    {
        $user = $utilisateurRepository->findOneBy(['cin' => $cin]);
        if (!$user) {
            return new JsonResponse(['success' => false, 'message' => 'Utilisateur non trouvé'], 404);
        }
    
        $user->setBlocked(!$user->isBlocked());
        $utilisateurRepository->getEntityManager()->flush();
    
        return new JsonResponse(['success' => true, 'blocked' => $user->isBlocked()]);
    }
    #[Route('/parametre/twofa', name: 'app_admin_parametre_twofa', methods: ['POST'])]
    public function updateTwoFactor(
        Request $request,
        EntityManagerInterface $em,
        ActivityLogRepository $activityLogRepository,
        UtilisateurRepository $utilisateurRepository,
        LoggerInterface $logger
    ): Response {
        $user = $this->getUser();
        if (!$user) {
            $this->addFlash('error', 'Utilisateur non connecté.');
            return $this->redirectToRoute('app_admin_parametre');
        }

        $user = $utilisateurRepository->find($user->getCin());
        $twoFactorEnabled = $request->request->get('twofa_enabled') === 'on';

        if ($twoFactorEnabled && !$user->getTwoFactorSecret()) {
            $totp = TOTP::create();
            $user->setTwoFactorSecret($totp->getSecret());
            $logger->info('Generated new 2FA secret for user', ['cin' => $user->getCin()]);
        } elseif (!$twoFactorEnabled) {
            $user->setTwoFactorSecret(null);
            $logger->info('2FA disabled for user', ['cin' => $user->getCin()]);
        }

        $user->setIsTwoFactorEnabled($twoFactorEnabled);
        $em->persist($user);
        $em->flush();

       		// Log the action
        $log = new ActivityLog();
        $log->setUser($user);
        $log->setAction('2FA modifié');
        $log->setDetails($twoFactorEnabled ? '2FA activé' : '2FA désactivé');
        $em->persist($log);
        $em->flush();

        $this->addFlash('success', 'Paramètres 2FA mis à jour avec succès.');
        return $this->redirectToRoute('app_admin_parametre');
    }

    #[Route('/twofa/qr-code/{cin}', name: 'app_admin_twofa_qr_code', methods: ['GET'])]
    public function generateTwoFactorQrCode(
        string $cin,
        UtilisateurRepository $utilisateurRepository,
        LoggerInterface $logger
    ): Response {
        $user = $utilisateurRepository->findOneBy(['cin' => $cin]);
        if (!$user || !$user->getTwoFactorSecret()) {
            $logger->error('User or 2FA secret not found for CIN: ' . $cin);
            throw $this->createNotFoundException('Utilisateur ou secret 2FA non trouvé');
        }

        $totp = TOTP::create($user->getTwoFactorSecret());
        $totp->setLabel($user->getEmail());
        $totp->setIssuer('Studar');
        $provisioningUri = $totp->getProvisioningUri();

        try {
            $qrCode = new EndroidQrCode($provisioningUri);
            $writer = new PngWriter();
            $result = $writer->write(
                $qrCode,
                null,
                null,
                [
                    'size' => 200,
                    'margin' => 10,
                ]
            );

            $logger->info('2FA QR code generated for CIN: ' . $cin);
            return new StreamedResponse(
                function () use ($result) {
                    echo $result->getString();
                },
                Response::HTTP_OK,
                [
                    'Content-Type' => $result->getMimeType(),
                    'Content-Disposition' => 'inline; filename="2fa-qr-' . $cin . '.png"',
                ]
            );
        } catch (\Exception $e) {
            $logger->error('Failed to generate 2FA QR code', [
                'cin' => $cin,
                'error' => $e->getMessage(),
            ]);
            return new Response('Erreur lors de la génération du QR code', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
   
}