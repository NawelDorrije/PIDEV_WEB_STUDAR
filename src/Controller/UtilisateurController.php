<?php

namespace App\Controller;

use App\Entity\ActivityLog;
use App\Entity\Utilisateur;
use Endroid\QrCode\QrCode as EndroidQrCode; // Use the alias
use Endroid\QrCode\Writer\PngWriter;
use App\Enums\RoleUtilisateur;
use App\Form\UtilisateurEditType;
use App\Form\UtilisateurType;
use App\Repository\ActivityLogRepository;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Form\FormError;
use OTPHP\TOTP;

#[Route('/utilisateur')]
final class UtilisateurController extends AbstractController
{
    private $tokenStorage;
    private $entityManager;
    private $logger;

    public function __construct(
        EntityManagerInterface $entityManager,
        TokenStorageInterface $tokenStorage,
        LoggerInterface $logger
    ) {
        $this->entityManager = $entityManager;
        $this->tokenStorage = $tokenStorage;
        $this->logger = $logger;
    }

    #[Route(name: 'app_utilisateur_index', methods: ['GET'])]
    public function index(UtilisateurRepository $utilisateurRepository): Response
    {
        return $this->render('utilisateur/index.html.twig', [
            'utilisateurs' => $utilisateurRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_utilisateur_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        UtilisateurRepository $utilisateurRepository,
        SluggerInterface $slugger
    ): Response {
        $utilisateur = new Utilisateur();
        $form = $this->createForm(UtilisateurType::class, $utilisateur);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($utilisateurRepository->findOneBy(['cin' => $utilisateur->getCin()])) {
                $form->get('cin')->addError(new FormError('Ce CIN est déjà utilisé'));
                return $this->render('utilisateur/new.html.twig', [
                    'form' => $form->createView(),
                ]);
            }

            if ($utilisateurRepository->findOneBy(['email' => $utilisateur->getEmail()])) {
                $form->get('email')->addError(new FormError('Cet email est déjà utilisé'));
                return $this->render('utilisateur/new.html.twig', [
                    'form' => $form->createView(),
                ]);
            }

            $imageFile = $form->get('imageFile')->getData();
            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();

                try {
                    $imageFile->move(
                        $this->getParameter('images_directory'),
                        $newFilename
                    );
                    $utilisateur->setImage($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Une erreur est survenue lors de l\'upload de l\'image');
                    return $this->redirectToRoute('app_utilisateur_new');
                }
            }

            $hashedPassword = $passwordHasher->hashPassword(
                $utilisateur,
                $form->get('mdp')->getData()
            );
            $utilisateur->setMdp($hashedPassword);
            $utilisateur->setCreatedAt(new \DateTimeImmutable());

            $entityManager->persist($utilisateur);
            $entityManager->flush();

            $this->addFlash('success', 'Inscription réussie!');
            return $this->redirectToRoute('app_utilisateur_signin');
        }

        return $this->render('utilisateur/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/signin', name: 'app_utilisateur_signin')]
    public function signin(AuthenticationUtils $authenticationUtils, SessionInterface $session): Response
    {
        $user = $this->getUser();
        if ($user) {
            $this->logger->info('User already logged in, checking 2FA', [
                'user' => $user->getEmail(),
                'role' => $user->getRole(),
                'isTwoFactorEnabled' => $user instanceof Utilisateur ? $user->isTwoFactorEnabled() : false,
            ]);
    
            // Check if 2FA is enabled and not yet verified
            if ($user instanceof Utilisateur && $user->isTwoFactorEnabled() && !$session->get('two_factor_verified', false)) {
                $this->logger->info('2FA required, redirecting to 2FA check');
                return $this->redirectToRoute('app_utilisateur_two_factor_check');
            }
    
            // If 2FA is not required or has been verified, redirect based on role
            $this->logger->info('Redirecting based on role', [
                'user' => $user->getEmail(),
                'role' => $user->getRole(),
            ]);
            return match ($user->getRole()) {
                RoleUtilisateur::ADMIN => $this->redirectToRoute('app_admin_dashboard'),
                default => $this->redirectToRoute('app_home')
            };
        }
    
        $error = $authenticationUtils->getLastAuthenticationError();
        $this->logger->info('Rendering signin page', [
            'last_username' => $authenticationUtils->getLastUsername(),
            'error' => $error ? $error->getMessage() : null,
        ]);
    
        return $this->render('utilisateur/signin.html.twig', [
            'last_username' => $authenticationUtils->getLastUsername(),
            'error' => $error,
        ]);
    }

    #[Route('/two-factor-check', name: 'app_utilisateur_two_factor_check', methods: ['GET', 'POST'])]
    public function twoFactorCheck(
        Request $request,
        TokenStorageInterface $tokenStorage,
        SessionInterface $session
    ): Response {
        $user = $this->getUser();
        if (!$user || !$user instanceof Utilisateur || !$user->isTwoFactorEnabled()) {
            return $this->redirectToRoute('app_utilisateur_signin');
        }
    
        $error = null;
        if ($request->isMethod('POST')) {
            $code = $request->request->get('totp_code');
            $totp = TOTP::create($user->getTwoFactorSecret());
    
            if ($totp->verify($code)) {
                $session->set('two_factor_verified', true);
                $token = new UsernamePasswordToken($user, 'main', $user->getRoles());
                $tokenStorage->setToken($token);
                $session->set('_security_main', serialize($token));
                $session->migrate(true);
                return $this->redirectToRoute(
                    $user->getRole() === RoleUtilisateur::ADMIN
                        ? 'app_admin_dashboard'
                        : 'app_home'
                );
            }
    
            $error = 'Code invalide. Veuillez réessayer.';
        }
    
        return $this->render('utilisateur/two_factor.html.twig', [
            'error' => $error,
        ]);
    }

    #[Route('/get-image-by-email', name: 'app_get_image_by_email', methods: ['POST'])]
    public function getImageByEmail(Request $request, UtilisateurRepository $utilisateurRepository): JsonResponse
    {
        $email = $request->request->get('email');
        if (!$email) {
            return new JsonResponse(['success' => false, 'message' => 'Email manquant'], 400);
        }

        $user = $utilisateurRepository->findOneBy(['email' => $email]);
        if (!$user || !$user->getImage()) {
            return new JsonResponse(['success' => false, 'message' => 'Utilisateur ou image non trouvé'], 404);
        }

        $imagePath = '/uploads/images/' . $user->getImage();
        return new JsonResponse(['success' => true, 'imagePath' => $imagePath]);
    }

    #[Route('/facial-auth', name: 'app_facial_auth', methods: ['POST'])]
    public function facialAuth(
        Request $request,
        UtilisateurRepository $utilisateurRepository,
        TokenStorageInterface $tokenStorage,
        SessionInterface $session
    ): JsonResponse {
        $email = $request->request->get('email');
        if (!$email) {
            return new JsonResponse(['success' => false, 'message' => 'Email manquant'], 400);
        }

        $user = $utilisateurRepository->findOneBy(['email' => $email]);
        if (!$user) {
            return new JsonResponse(['success' => false, 'message' => 'Utilisateur non trouvé'], 404);
        }

        $token = new UsernamePasswordToken($user, 'main', $user->getRoles());
        $tokenStorage->setToken($token);
        $session->set('_security_main', serialize($token));
        $session->migrate(true);

        return new JsonResponse([
            'success' => true,
            'redirect' => $user->getRole() === RoleUtilisateur::ADMIN
                ? $this->generateUrl('app_admin_dashboard')
                : $this->generateUrl('app_home')
        ]);
    }

    #[Route('/{cin}', name: 'app_utilisateur_show', requirements: ['cin' => '\d{8}'], defaults: ['cin' => null], methods: ['GET'])]
    public function show(Utilisateur $utilisateur = null): Response
    {
        if (!$utilisateur && $this->getUser()) {
            $utilisateur = $this->getUser();
        }

        if (!$utilisateur) {
            throw $this->createNotFoundException('User not found');
        }

        return $this->render('utilisateur/show.html.twig', [
            'utilisateur' => $utilisateur,
        ]);
    }

    #[Route('/parametre', name: 'app_utilisateur_parametre')]
    public function parametre(ActivityLogRepository $activityLogRepository): Response
    {
        $user = $this->getUser();
        if (!$user->getTheme() || !in_array($user->getTheme(), ['light', 'dark', 'custom'])) {
            $user->setTheme('light');
            $this->entityManager->persist($user);
            $this->entityManager->flush();
        }
        $activityLogs = $activityLogRepository->findRecentByUser($user->getCin());


        return $this->render('utilisateur/parametre.html.twig', [
            'activityLogs' => $activityLogs,
        ]);
    }

    #[Route('/parametre/theme', name: 'app_utilisateur_parametre_theme', methods: ['POST'])]
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
            return $this->redirectToRoute('app_utilisateur_parametre');
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
        return $this->redirectToRoute('app_utilisateur_parametre');
    }

    #[Route('/parametre/password', name: 'app_utilisateur_parametre_password', methods: ['POST'])]
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
            return $this->redirectToRoute('app_utilisateur_parametre');
        }

        $user = $utilisateurRepository->find($user->getCin());

        if (!password_verify($oldPassword, $user->getMdp())) {
            $this->addFlash('error', 'L\'ancien mot de passe est incorrect.');
            return $this->redirectToRoute('app_utilisateur_parametre');
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
        return $this->redirectToRoute('app_utilisateur_parametre');
    }

    #[Route('/utilisateur/profile/{cin}/modifier', name: 'app_utilisateur_modifier_profile', methods: ['GET', 'POST'])]
    public function modifierProfile(
        Request $request,
        Utilisateur $utilisateur,
        EntityManagerInterface $entityManager,
        EntityManagerInterface $em,
        SluggerInterface $slugger
    ): Response {
        if ($this->getUser()->getCin() !== $utilisateur->getCin()) {
            $this->addFlash('error', 'Vous ne pouvez modifier que votre propre profil.');
            return $this->redirectToRoute('app_utilisateur_parametre');
        }

        if ($request->isMethod('POST')) {
            $nom = $request->request->get('nom');
            $prenom = $request->request->get('prenom');
            $email = $request->request->get('email');
            $numTel = $request->request->get('numTel');
            $imageFile = $request->files->get('imageFile');

            if (empty($nom) || empty($prenom) || empty($email)) {
                $this->addFlash('error', 'Les champs nom, prénom et email sont obligatoires.');
                return $this->redirectToRoute('app_utilisateur_parametre');
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->addFlash('error', 'L\'adresse email est invalide.');
                return $this->redirectToRoute('app_utilisateur_parametre');
            }

            $utilisateur->setNom($nom);
            $utilisateur->setPrenom($prenom);
            $utilisateur->setEmail($email);
            $utilisateur->setNumTel($numTel ?: null);

            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();

                try {
                    $imageFile->move(
                        $this->getParameter('images_directory'),
                        $newFilename
                    );

                    if ($utilisateur->getImage()) {
                        $oldImagePath = $this->getParameter('images_directory') . '/' . $utilisateur->getImage();
                        if (file_exists($oldImagePath)) {
                            unlink($oldImagePath);
                        }
                    }

                    $utilisateur->setImage($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Échec du téléchargement de l\'image : ' . $e->getMessage());
                    return $this->redirectToRoute('app_utilisateur_parametre');
                }
            }

            $entityManager->flush();
            $this->addFlash('success', 'Profil mis à jour avec succès.');
            $log = new ActivityLog();
            $log->setUser($utilisateur);
            $log->setAction('Profile modifié');
            $log->setDetails('Vous avez modifié votre profile.');
            $em->persist($log);
            $em->flush();

            return $this->redirectToRoute('app_utilisateur_parametre');
        }

        return $this->render('utilisateur/parametre.html.twig', [
            'utilisateur' => $utilisateur,
        ]);
    }

    #[Route('/{cin}/edit', name: 'app_utilisateur_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        Utilisateur $utilisateur,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger
    ): Response {
        $form = $this->createForm(UtilisateurEditType::class, $utilisateur);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $imageFile = $form->get('imageFile')->getData();
            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();

                try {
                    $imageFile->move(
                        $this->getParameter('images_directory'),
                        $newFilename
                    );

                    if ($utilisateur->getImage()) {
                        $oldImagePath = $this->getParameter('images_directory') . '/' . $utilisateur->getImage();
                        if (file_exists($oldImagePath)) {
                            unlink($oldImagePath);
                        }
                    }

                    $utilisateur->setImage($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Failed to upload image: ' . $e->getMessage());
                    return $this->render('utilisateur/edit.html.twig', [
                        'utilisateur' => $utilisateur,
                        'form' => $form->createView(),
                    ]);
                }
            }

            $entityManager->flush();
            $this->addFlash('success', 'Profile updated successfully');
            return $this->redirectToRoute('app_utilisateur_show', ['cin' => $utilisateur->getCin()]);
        }

        return $this->render('utilisateur/edit.html.twig', [
            'utilisateur' => $utilisateur,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{cin}', name: 'app_utilisateur_delete', methods: ['POST'])]
    public function delete(Request $request, Utilisateur $utilisateur, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $utilisateur->getCin(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($utilisateur);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_utilisateur_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/dashboard', name: 'app_admin_dashboard')]
    public function dashboard(): Response
    {
        return $this->render('dashboard.html.twig');
    }
    

    #[Route('/forgot-password', name: 'app_forgot_password', methods: ['GET', 'POST'])]
    public function forgotPassword(
        Request $request,
        UtilisateurRepository $utilisateurRepository,
        EntityManagerInterface $entityManager,
        MailerInterface $mailer,
        LoggerInterface $logger
    ): Response {
        if ($request->isMethod('POST')) {
            $email = $request->request->get('email');
            $user = $utilisateurRepository->findOneBy(['email' => $email]);

            if (!$user) {
                $this->addFlash('error', 'Cet email n\'est pas inscrit.');
                return $this->render('utilisateur/forgot_password.html.twig');
            }

            $resetCode = str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            $user->setResetCode($resetCode);
            $user->setResetCodeExpiresAt(new \DateTimeImmutable('+15 minutes'));
            $entityManager->flush();

            try {
                $email = (new Email())
                    ->from(new Address('studar21@gmail.com', 'Studar'))
                    ->to($user->getEmail())
                    ->subject('Réinitialisation de votre mot de passe')
                    ->text(sprintf(
                        "Votre code de réinitialisation est : %s\nCe code expirera dans 15 minutes.",
                        $resetCode
                    ));

                $mailer->send($email);

                $request->getSession()->set('reset_email', $user->getEmail());

                return $this->redirectToRoute('app_verify_reset_code');
            } catch (\Exception $e) {
                $logger->error('Email sending failed: ' . $e->getMessage());
                $this->addFlash('error', 'Erreur lors de l\'envoi du code');
                return $this->render('utilisateur/forgot_password.html.twig');
            }
        }

        return $this->render('utilisateur/forgot_password.html.twig');
    }
    #[Route('/debug-2fa/{email}', name: 'app_debug_2fa', methods: ['GET'])]
public function debug2FA(string $email, UtilisateurRepository $utilisateurRepository): Response
{
    $user = $utilisateurRepository->findOneBy(['email' => $email]);
    if (!$user) {
        return new Response('User not found', 404);
    }
    return new Response('2FA Enabled: ' . ($user->isTwoFactorEnabled() ? 'Yes' : 'No'));
}

    #[Route('/verify-reset-code', name: 'app_verify_reset_code', methods: ['GET', 'POST'])]
    public function verifyResetCode(
        Request $request,
        UtilisateurRepository $utilisateurRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $email = $request->getSession()->get('reset_email');
        if (!$email) {
            $this->addFlash('error', 'Session expirée. Veuillez réessayer.');
            return $this->redirectToRoute('app_forgot_password');
        }

        $user = $utilisateurRepository->findOneBy(['email' => $email]);
        if (!$user) {
            $this->addFlash('error', 'Utilisateur non trouvé');
            return $this->redirectToRoute('app_forgot_password');
        }

        if ($request->isMethod('POST')) {
            $submittedCode = $request->request->get('reset_code');

            if (!$user->getResetCode() || $user->getResetCode() !== $submittedCode) {
                $this->addFlash('error', 'Code incorrect');
                return $this->render('utilisateur/verify_reset_code.html.twig', ['email' => $email]);
            }

            if ($user->getResetCodeExpiresAt() < new \DateTimeImmutable()) {
                $this->addFlash('error', 'Code expiré');
                return $this->redirectToRoute('app_forgot_password');
            }

            return $this->redirectToRoute('app_reset_password');
        }

        return $this->render('utilisateur/verify_reset_code.html.twig', ['email' => $email]);
    }

    #[Route('/reset-password', name: 'app_reset_password', methods: ['GET', 'POST'])]
    public function resetPassword(
        Request $request,
        UtilisateurRepository $utilisateurRepository,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        $email = $request->getSession()->get('reset_email');
        if (!$email) {
            $this->addFlash('error', 'Session expirée. Veuillez réessayer.');
            return $this->redirectToRoute('app_forgot_password');
        }

        $user = $utilisateurRepository->findOneBy(['email' => $email]);
        if (!$user) {
            $this->addFlash('error', 'Utilisateur non trouvé');
            return $this->redirectToRoute('app_forgot_password');
        }

        if ($request->isMethod('POST')) {
            $newPassword = $request->request->get('new_password');
            $confirmPassword = $request->request->get('confirm_password');

            if ($newPassword !== $confirmPassword) {
                $this->addFlash('error', 'Les mots de passe ne correspondent pas');
                return $this->render('utilisateur/reset_password.html.twig');
            }

            if (strlen($newPassword) < 6) {
                $this->addFlash('error', 'Le mot de passe doit contenir au moins 6 caractères');
                return $this->render('utilisateur/reset_password.html.twig');
            }

            $hashedPassword = $passwordHasher->hashPassword($user, $newPassword);
            $user->setMdp($hashedPassword);
            $user->setResetCode(null);
            $user->setResetCodeExpiresAt(null);

            $entityManager->flush();

            $request->getSession()->remove('reset_email');

            $this->addFlash('success', 'Mot de passe mis à jour avec succès');
            return $this->redirectToRoute('app_utilisateur_signin');
        }

        return $this->render('utilisateur/reset_password.html.twig');
    }
    #[Route('/parametre/twofa', name: 'app_utilisateur_parametre_twofa', methods: ['POST'])]
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
            return $this->redirectToRoute('app_utilisateur_parametre');
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
        return $this->redirectToRoute('app_utilisateur_parametre');
    }

    #[Route('/twofa/qr-code/{cin}', name: 'app_utilisateur_twofa_qr_code', methods: ['GET'])]
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