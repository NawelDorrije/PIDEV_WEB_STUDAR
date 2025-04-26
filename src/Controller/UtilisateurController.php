<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Enums\RoleUtilisateur;
use App\Form\UtilisateurType;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Form\FormError;
use App\Form\UtilisateurEditType;
use Psr\Log\LoggerInterface;  // Add this line with other use statements
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
#[Route('/utilisateur')]
final class UtilisateurController extends AbstractController
{
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
        // Check for duplicate CIN
        if ($utilisateurRepository->findOneBy(['cin' => $utilisateur->getCin()])) {
            $form->get('cin')->addError(new FormError('Ce CIN est déjà utilisé'));
            return $this->render('utilisateur/new.html.twig', [
                'form' => $form->createView(),
            ]);
        }

        // Check for duplicate email
        if ($utilisateurRepository->findOneBy(['email' => $utilisateur->getEmail()])) {
            $form->get('email')->addError(new FormError('Cet email est déjà utilisé'));
            return $this->render('utilisateur/new.html.twig', [
                'form' => $form->createView(),
            ]);
        }

        // Handle image upload
        $imageFile = $form->get('imageFile')->getData();
        if ($imageFile) {
            $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
            $safeFilename = $slugger->slug($originalFilename);
            $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();

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

        // Hash the password
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
    
    #[Route(
        '/{cin}', 
        name: 'app_utilisateur_show',
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
    
        return $this->render('utilisateur/show.html.twig', [
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
        dump('Initial load'); // Debug point 1
        
        $form = $this->createForm(UtilisateurEditType::class, $utilisateur);
        $form->handleRequest($request);
    
        if ($form->isSubmitted()) {
            dump('Form submitted'); // Debug point 2
            dump($form->getData()); // See what data was submitted
            
            // Handle image upload
            $imageFile = $form->get('imageFile')->getData();
            if ($imageFile) {
                dump('Image file detected'); // Debug point 2.1
                
                // Delete old image if exists
                if ($utilisateur->getImage()) {
                    $oldImagePath = $this->getParameter('images_directory').'/'.$utilisateur->getImage();
                    if (file_exists($oldImagePath)) {
                        unlink($oldImagePath);
                        dump('Old image deleted'); // Debug point 2.2
                    }
                }
    
                // Generate new filename
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();
                dump('New filename: '.$newFilename); // Debug point 2.3
    
                try {
                    $imageFile->move(
                        $this->getParameter('images_directory'),
                        $newFilename
                    );
                    $utilisateur->setImage($newFilename);
                    dump('Image uploaded successfully'); // Debug point 2.4
                } catch (FileException $e) {
                    $this->addFlash('error', 'Failed to upload image');
                    dump('Image upload failed: '.$e->getMessage()); // Debug point 2.5
                }
            }
    
            if ($form->isValid()) {
                dump('Form is valid'); // Debug point 3
                $entityManager->flush();
                $this->addFlash('success', 'Profile updated successfully');
                return $this->redirectToRoute('app_utilisateur_show', ['cin' => $utilisateur->getCin()]);
            } else {
                dump('Form errors:', $form->getErrors(true)); // Debug point 4
            }
        }
    
        return $this->render('utilisateur/edit.html.twig', [
            'utilisateur' => $utilisateur,
            'form' => $form->createView(),
        ]);
    }
    // #[Route('/{cin}/edit', name: 'app_utilisateur_edit', methods: ['GET', 'POST'])]
    // public function edit(
    //     Request $request, 
    //     Utilisateur $utilisateur, 
    //     EntityManagerInterface $entityManager
    // ): Response {
    //     dump('Initial load'); // Debug point 1
        
    //     $form = $this->createForm(UtilisateurEditType::class, $utilisateur);
    //     $form->handleRequest($request);
    
    //     if ($form->isSubmitted()) {
    //         dump('Form submitted'); // Debug point 2
    //         dump($form->getData()); // See what data was submitted
            
    //         if ($form->isValid()) {
    //             dump('Form is valid'); // Debug point 3
    //             $entityManager->flush();
    //             $this->addFlash('success', 'Profile updated successfully');
    //             return $this->redirectToRoute('app_utilisateur_show', ['cin' => $utilisateur->getCin()]);
    //         } else {
    //             dump('Form errors:', $form->getErrors(true)); // Debug point 4
    //         }
    //     }
    
    //     return $this->render('utilisateur/edit.html.twig', [
    //         'utilisateur' => $utilisateur,
    //         'form' => $form->createView(),
    //     ]);
    // }
   


    #[Route('/{cin}', name: 'app_utilisateur_delete', methods: ['POST'])]
    public function delete(Request $request, Utilisateur $utilisateur, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$utilisateur->getCin(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($utilisateur);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_utilisateur_index', [], Response::HTTP_SEE_OTHER);
    }

//     #[Route('/signin', name: 'app_utilisateur_signin')]
// public function signin(AuthenticationUtils $authenticationUtils): Response
// {
//     // If user is already authenticated and has ROLE_ADMIN, redirect to dashboard
//     if ($this->isGranted('ADMIN')) {
//         return $this->redirectToRoute('app_dashboard');
//     }
//     // If user is authenticated but not admin, redirect to home
//     elseif ($this->isGranted('IS_AUTHENTICATED_FULLY')) {
//         return $this->redirectToRoute('app_home');
//     }
    
//     return $this->render('utilisateur/signin.html.twig', [
//         'last_username' => $authenticationUtils->getLastUsername(),
//         'error' => $authenticationUtils->getLastAuthenticationError()
//     ]);
// }
// src/Controller/UtilisateurController.php
#[Route('/signin', name: 'app_utilisateur_signin')]
public function signin(AuthenticationUtils $authenticationUtils): Response
{
    // If user is already logged in, redirect based on role
    if ($this->getUser()) {
        return match($this->getUser()->getRole()) {
            RoleUtilisateur::ADMIN => $this->redirectToRoute('app_admin_dashboard'),
            default => $this->redirectToRoute('app_home')
        };
    }

    return $this->render('utilisateur/signin.html.twig', [
        'last_username' => $authenticationUtils->getLastUsername(),
        'error' => $authenticationUtils->getLastAuthenticationError()
    ]);
}
// In your UtilisateurController
#[Route('/dashboard', name: 'app_admin_dashboard')]
public function dashboard(): Response
{
    $this->denyAccessUnlessGranted('ROLE_ADMIN');
    
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

            // Generate 4-digit reset code
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

                // Store email in session for verification
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
}