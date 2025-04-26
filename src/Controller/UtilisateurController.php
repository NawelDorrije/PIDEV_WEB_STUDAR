<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Form\UtilisateurType;
use App\Repository\LogementRepository;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Form\FormError;
use App\Form\UtilisateurEditType;
use Psr\Log\LoggerInterface;  // Add this line with other use statements
#[Route('/utilisateur')]
final class UtilisateurController extends AbstractController
{
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }
    #[Route(name: 'app_utilisateur_index', methods: ['GET'])]
    public function index(UtilisateurRepository $utilisateurRepository): Response
    {
        return $this->render('utilisateur/index.html.twig', [
            'utilisateurs' => $utilisateurRepository->findAll(),
        ]);
    }
    // #[Route('/new', name: 'app_utilisateur_new', methods: ['GET', 'POST'])]
    // public function new(
    //     Request $request, 
    //     EntityManagerInterface $entityManager,
    //     UserPasswordHasherInterface $passwordHasher  // Add this dependency
    // ): Response {
    //     $utilisateur = new Utilisateur();
    //     $form = $this->createForm(UtilisateurType::class, $utilisateur);
    //     $form->handleRequest($request);
    
    //     if ($form->isSubmitted() && $form->isValid()) {
    //         // Hash the plain password before storing it
    //         $hashedPassword = $passwordHasher->hashPassword(
    //             $utilisateur,
    //             $form->get('mdp')->getData()  // Get the plain password from the form
    //         );
            
    //         $utilisateur->setMdp($hashedPassword);  // Set the hashed password
            
    //         // Set creation date
    //         $utilisateur->setCreatedAt(new \DateTimeImmutable());
            
    //         $entityManager->persist($utilisateur);
    //         $entityManager->flush();
    
    //         // Add flash message for success
    //         $this->addFlash('success', 'Inscription réussie! Vous pouvez maintenant vous connecter.');
    
    //         return $this->redirectToRoute('app_utilisateur_new', [], Response::HTTP_SEE_OTHER);
    //     }
    
    //     return $this->render('utilisateur/new.html.twig', [
    //         'utilisateur' => $utilisateur,
    //         'form' => $form,
    //     ]);
    // }
    #[Route('/new', name: 'app_utilisateur_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request, 
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        UtilisateurRepository $utilisateurRepository
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
                // Hash the plain password before storing it
                $hashedPassword = $passwordHasher->hashPassword(
                    $utilisateur,
                    $form->get('mdp')->getData()  // Get the plain password from the form
                );
                
                $utilisateur->setMdp($hashedPassword);  // Set the hashed password
                
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
    // #[Route(
    //     '/{cin}', 
    //     name: 'app_utilisateur_show',
    //     requirements: ['cin' => '\d{8}'], // Requires exactly 8 digits
    //     methods: ['GET']
    // )]    public function show(Utilisateur $utilisateur): Response
    // {
    //     return $this->render('utilisateur/show.html.twig', [
    //         'utilisateur' => $utilisateur,
    //     ]);
    // }
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
        EntityManagerInterface $entityManager
    ): Response {
        dump('Initial load'); // Debug point 1
        
        $form = $this->createForm(UtilisateurEditType::class, $utilisateur);
        $form->handleRequest($request);
    
        if ($form->isSubmitted()) {
            dump('Form submitted'); // Debug point 2
            dump($form->getData()); // See what data was submitted
            
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
    //     #[Route('/{cin}/edit', name: 'app_utilisateur_edit', methods: ['GET', 'POST'])]
// public function edit(
//     Request $request, 
//     Utilisateur $utilisateur, 
//     EntityManagerInterface $entityManager
// ): Response 
// {
//     // Create the edit-specific form (without password field)
//     $form = $this->createForm(UtilisateurEditType::class, $utilisateur);
//     $form->handleRequest($request);

//     if ($form->isSubmitted() && $form->isValid()) {
//         // The password remains unchanged since it's not in this form
//         $entityManager->flush();

//         $this->addFlash('success', 'Profile updated successfully');
//         return $this->redirectToRoute('app_utilisateur_show', [
//             'cin' => $utilisateur->getCin()
//         ]);
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

    // #[Route('/signin', name: 'app_utilisateur_signin')]
    // public function signin(AuthenticationUtils $authenticationUtils): Response
    // {
    //     if ($this->getUser()) {
    //         return $this->redirectToRoute('app_home');
    //     }
    
    //     return $this->render('utilisateur/signin.html.twig', [
    //         'last_username' => $authenticationUtils->getLastUsername(),
    //         'error' => $authenticationUtils->getLastAuthenticationError()
    //     ]);
    // }
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
// #[Route('/api/users', name: 'app_utilisateur_api_users', methods: ['GET'])]
// public function getUsersApi(EntityManagerInterface $em): JsonResponse
// {
//     if (!$this->getUser()) {
//         $this->logger->warning('User not authenticated when accessing app_utilisateur_api_users', [
//             'user' => $this->getUser() ? $this->getUser()->getUserIdentifier() : 'null',
//         ]);
//         return $this->json(['error' => 'Authentication required'], 401);
//     }

//     $users = $em->getRepository(Utilisateur::class)->findAll();
//     $this->logger->info('Users retrieved for API', [
//         'count' => count($users),
//     ]);

//     $userData = array_map(fn($user) => [
//         'id' => $user->getId(),
//         'name' => $user->getNom(),
//         'email' => $user->getEmail(),
//     ], $users);

//     $this->logger->info('User data prepared for API', [
//         'userData' => $userData,
//     ]);

//     return $this->json($userData);
// }
}
