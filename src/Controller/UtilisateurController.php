<?php

namespace App\Controller;

use App\Entity\Utilisateur;
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

    if ($form->isSubmitted()) {
        // Check for duplicate CIN before validation
        if ($utilisateurRepository->findOneBy(['cin' => $utilisateur->getCin()])) {
            $form->get('cin')->addError(new FormError('Ce CIN est déjà utilisé'));
        }

        // Check for duplicate email before validation
        if ($utilisateurRepository->findOneBy(['email' => $utilisateur->getEmail()])) {
            $form->get('email')->addError(new FormError('Cet email est déjà utilisé'));
        }

        if ($form->isValid()) {
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

            // Hash the plain password before storing it
            $hashedPassword = $passwordHasher->hashPassword(
                $utilisateur,
                $form->get('mdp')->getData()
            );
            
            $utilisateur->setMdp($hashedPassword);
            
            // Set creation date
            $utilisateur->setCreatedAt(new \DateTimeImmutable());
            
            $entityManager->persist($utilisateur);
            $entityManager->flush();
            
            $this->addFlash('success', 'Inscription réussie!');
            return $this->redirectToRoute('app_utilisateur_new');
        }
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

   
    #[Route('/signin', name: 'app_utilisateur_signin')]
public function signin(AuthenticationUtils $authenticationUtils): Response
{
    // Redirect only if user is fully authenticated (not just remembered)
    if ($this->isGranted('IS_AUTHENTICATED_FULLY')) {
        return $this->redirectToRoute('app_home');
    }
    
    return $this->render('utilisateur/signin.html.twig', [
        'last_username' => $authenticationUtils->getLastUsername(),
        'error' => $authenticationUtils->getLastAuthenticationError()
    ]);
}

#[Route('/forgotPassword', name: 'app_utilisateur_forgotPassword', methods: ['GET', 'POST'])]
public function forgotPassword(
    Request $request,
    UtilisateurRepository $utilisateurRepository,
    EntityManagerInterface $entityManager,
    MailerInterface $mailer,
    LoggerInterface $logger
): Response {
    // Handle GET request (coming from signin page)
    if ($request->isMethod('GET')) {
        $email = $request->query->get('email');
        
        if (empty($email)) {
            $this->addFlash('error', 'Veuillez saisir votre email');
            return $this->redirectToRoute('app_utilisateur_signin');
        }
        
        // Store email in session and render the forgot password page
        $request->getSession()->set('reset_email', $email);
        return $this->render('utilisateur/forgotPassword.html.twig', [
            'email' => $email
        ]);
    }
    
    // Handle POST request (from forgot password form)
    if ($request->isMethod('POST')) {
        $email = $request->getSession()->get('reset_email');
        
        if (empty($email)) {
            $this->addFlash('error', 'Session expirée, veuillez recommencer');
            return $this->redirectToRoute('app_utilisateur_signin');
        }

        $user = $utilisateurRepository->findOneBy(['email' => $email]);
        if (!$user) {
            $this->addFlash('error', 'Aucun compte associé à cet email');
            return $this->redirectToRoute('app_utilisateur_signin');
        }

        // Generate and save reset code
        $resetCode = str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        $user->setResetCode($resetCode);
        $user->setResetCodeExpiresAt(new \DateTimeImmutable('+1 hour')); // Code expires in 1 hour
        $entityManager->flush();

        try {
            // Send email with reset code
            $email = (new TemplatedEmail())
                ->from(new Address('studar21@gmail.com', 'Studar'))
                ->to($user->getEmail())
                ->subject('Réinitialisation de votre mot de passe')
                ->htmlTemplate('emails/reset_password.html.twig')
                ->context([
                    'resetCode' => $resetCode,
                    'expiration_date' => new \DateTime('+1 hour')
                ]);

            $mailer->send($email);
            
            // Redirect to verification page
            return $this->redirectToRoute('app_utilisateur_verify_reset_code');
            
        } catch (\Exception $e) {
            $logger->error('Erreur d\'envoi d\'email: '.$e->getMessage());
            $this->addFlash('error', 'Erreur lors de l\'envoi du code');
            return $this->redirectToRoute('app_utilisateur_signin');
        }
    }
}


// #[Route('/verify_reset_code', name: 'app_utilisateur_verify_reset_code', methods: ['GET', 'POST'])]
// public function verifyResetCode(
//     Request $request,
//     UtilisateurRepository $utilisateurRepository
// ): Response {
//     $email = $request->getSession()->get('reset_email');
    
//     if (!$email) {
//         $this->addFlash('error', 'Session expirée, veuillez recommencer');
//         return $this->redirectToRoute('app_utilisateur_signin');
//     }
    
//     $user = $utilisateurRepository->findOneBy(['email' => $email]);
//     if (!$user) {
//         $this->addFlash('error', 'Utilisateur non trouvé');
//         return $this->redirectToRoute('app_utilisateur_signin');
//     }
    
//     if ($request->isMethod('POST')) {
//         $submittedCode = $request->request->get('reset_code');
        
//         if (!$user->getResetCode() || $user->getResetCode() !== $submittedCode) {
//             $this->addFlash('error', 'Code incorrect');
//             return $this->redirectToRoute('app_utilisateur_verify_reset_code');
//         }
        
//         // Code valide, passe à la réinitialisation
//         $request->getSession()->set('reset_verified', true);
//         return $this->redirectToRoute('app_reset_password');
//     }
    
//     return $this->render('utilisateur/verify_reset_code.html.twig');
// }
// #[Route('/reset-password', name: 'app_reset_password', methods: ['GET', 'POST'])]
// public function resetPassword(
//     Request $request,
//     UtilisateurRepository $utilisateurRepository,
//     EntityManagerInterface $entityManager,
//     UserPasswordHasherInterface $passwordHasher
// ): Response {
//     $email = $request->getSession()->get('reset_email');
//     $verified = $request->getSession()->get('reset_verified');
    
//     if (!$email || !$verified) {
//         $this->addFlash('error', 'Session expirée, veuillez recommencer');
//         return $this->redirectToRoute('app_utilisateur_signin');
//     }
    
//     $user = $utilisateurRepository->findOneBy(['email' => $email]);
//     if (!$user) {
//         $this->addFlash('error', 'Utilisateur non trouvé');
//         return $this->redirectToRoute('app_utilisateur_signin');
//     }
    
//     if ($request->isMethod('POST')) {
//         $newPassword = $request->request->get('new_password');
        
//         // Hash the new password
//         $hashedPassword = $passwordHasher->hashPassword($user, $newPassword);
//         $user->setMdp($hashedPassword);
//         $user->setResetCode(null); // Clear reset code
//         $entityManager->flush();
        
//         // Clear session
//         $request->getSession()->remove('reset_email');
//         $request->getSession()->remove('reset_verified');
        
//         $this->addFlash('success', 'Mot de passe mis à jour avec succès');
//         return $this->redirectToRoute('app_utilisateur_signin');
//     }
    
//     return $this->render('utilisateur/reset_password.html.twig');
// }
}
