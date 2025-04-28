<?php

namespace App\Controller\Admin;

use App\Entity\ActivityLog;
use App\Entity\Utilisateur;
use App\Enums\RoleUtilisateur;
use App\Repository\ActivityLogRepository;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

#[Route('/admin')]
class DashboardController extends AbstractController
{
    private $entityManager;
    private $tokenStorage;
    private $logger;
    private $mailerFrom;

    public function __construct(
        EntityManagerInterface $entityManager,
        TokenStorageInterface $tokenStorage,
        LoggerInterface $logger,
        string $mailerFrom
    ) {
        $this->entityManager = $entityManager;
        $this->tokenStorage = $tokenStorage;
        $this->logger = $logger;
        $this->mailerFrom = $mailerFrom;
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

    #[Route('/user/{cin}/toggle-block', name: 'app_admin_user_toggle_block', methods: ['POST'])]
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
    #[Route('/dashboard', name: 'app_admin_dashboard')]
    public function dashboard(
        UtilisateurRepository $utilisateurRepository,
        PaginatorInterface $paginator,
        Request $request
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

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
        name: 'app_admin_user_profile',
        requirements: ['cin' => '\d{8}'],
        defaults: ['cin' => null],
        methods: ['GET']
    )]
    public function userProfile(?Utilisateur $utilisateur = null): Response
    {
        if (!$utilisateur && $this->getUser()) {
            $utilisateur = $this->getUser();
        }

        if (!$utilisateur instanceof Utilisateur) {
            throw $this->createNotFoundException('User not found');
        }

        return $this->render('admin/adminProfile.html.twig', [
            'utilisateur' => $utilisateur,
        ]);
    }

    #[Route('/statistique', name: 'app_admin_statistique')]
    public function statistique(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('admin/statistique.html.twig');
    }

    #[Route('/api/user-stats', name: 'app_admin_api_user_stats')]
    public function getUserStats(UtilisateurRepository $utilisateurRepository): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

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

    #[Route('/parametre', name: 'app_admin_parametre')]
    public function parametre(ActivityLogRepository $activityLogRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $user = $this->getUser();
        if (!$user instanceof Utilisateur) {
            throw $this->createAccessDeniedException('User not authenticated');
        }

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
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $user = $this->getUser();
        if (!$user instanceof Utilisateur) {
            throw $this->createAccessDeniedException('User not authenticated');
        }

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
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $user = $this->getUser();
        if (!$user instanceof Utilisateur) {
            throw $this->createAccessDeniedException('User not authenticated');
        }

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
        $log->setDetails('L\'utilisateur a modifié son mot de passe.');
        $em->persist($log);
        $em->flush();

        $token = new UsernamePasswordToken($user, 'main', $user->getRoles());
        $this->tokenStorage->setToken($token);
        $session->set('_security_main', serialize($token));
        $session->migrate(true);

        $this->addFlash('success', 'Mot de passe mis à jour avec succès.');
        return $this->redirectToRoute('app_admin_parametre');
    }

    #[Route('/user/report', name: 'app_admin_user_report', methods: ['POST'])]
    public function reportUser(
        Request $request,
        UtilisateurRepository $utilisateurRepository,
        MailerInterface $mailer
    ): JsonResponse {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        try {
            $data = json_decode($request->getContent(), true);
            $this->logger->debug('Report request data', ['data' => $data]);

            $userId = $data['userId'] ?? null;
            $userEmail = $data['userEmail'] ?? null;
            $reason = $data['reason'] ?? null;

            if (!$userId || !$userEmail || !$reason) {
                $this->logger->warning('Missing report data', ['data' => $data]);
                return $this->json(['success' => false, 'message' => 'Données manquantes'], 400);
            }

            $user = $utilisateurRepository->find($userId);
            if (!$user) {
                $this->logger->warning('User not found', ['userId' => $userId]);
                return $this->json(['success' => false, 'message' => 'Utilisateur non trouvé'], 404);
            }
            if ($user->getEmail() !== $userEmail) {
                $this->logger->warning('Email mismatch', [
                    'userId' => $userId,
                    'providedEmail' => $userEmail,
                    'actualEmail' => $user->getEmail()
                ]);
                return $this->json(['success' => false, 'message' => 'Email incorrect'], 400);
            }

            // Send email
            try {
                $email = (new Email())
                    ->from($this->mailerFrom)
                    ->to($userEmail)
                    ->subject('Avertissement : Signalement de votre compte')
                    ->html("<p>Bonjour {$user->getNom()},</p><p>Votre compte a été signalé pour la raison suivante :</p><p><strong>{$reason}</strong></p><p>Veuillez contacter l'administrateur pour plus d'informations.</p><p>Cordialement,<br>L'équipe Studar</p>");

                $mailer->send($email);
                $this->logger->info('Report email sent', ['to' => $userEmail]);
            } catch (\Exception $e) {
                $this->logger->error('Failed to send report email', [
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                return $this->json(['success' => false, 'message' => 'Erreur lors de l\'envoi de l\'email: ' . $e->getMessage()], 500);
            }

            // Log the report action
            try {
                $log = new ActivityLog();
                $log->setUser($this->getUser());
                $log->setAction('Signalement utilisateur');
                $log->setDetails(sprintf('Utilisateur %s signalé pour : %s', $userId, $reason));
                $this->entityManager->persist($log);
                $this->entityManager->flush();
                $this->logger->info('Report logged', ['userId' => $userId]);
            } catch (\Exception $e) {
                $this->logger->error('Failed to log report', ['message' => $e->getMessage()]);
                return $this->json(['success' => false, 'message' => 'Erreur lors de l\'enregistrement du log: ' . $e->getMessage()], 500);
            }

            return $this->json(['success' => true, 'message' => 'Signalement envoyé avec succès']);
        } catch (\Exception $e) {
            $this->logger->error('Error in reportUser', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->json(['success' => false, 'message' => 'Erreur serveur lors du signalement: ' . $e->getMessage()], 500);
        }
    }
}