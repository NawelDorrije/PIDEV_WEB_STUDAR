<?php
namespace App\Controller;

use App\Entity\Utilisateur;
use App\Form\CompleteRegistrationType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class RegistrationController extends AbstractController
{
    #[Route('/complete-registration', name: 'app_utilisateur_complete_registration')]
    public function completeRegistration(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        SessionInterface $session
    ): Response {
        $googleData = $session->get('google_user_data');
        
        if (!$googleData) {
            $this->addFlash('error', 'Veuillez d\'abord vous connecter avec Google');
            return $this->redirectToRoute('app_utilisateur_new');
        }
    
        $user = new Utilisateur();
        $user->setEmail($googleData['email'] ?? '');
        $user->setPrenom($googleData['firstName'] ?? '');
        $user->setNom($googleData['lastName'] ?? '');
        $user->setPassword($passwordHasher->hashPassword($user, bin2hex(random_bytes(16))));
    
        $form = $this->createForm(CompleteRegistrationType::class, $user);
        $form->handleRequest($request);
    
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $entityManager->persist($user);
                $entityManager->flush();
                
                $session->remove('google_user_data');
                
                // Use the same addFlash method for consistency
                $this->addFlash('success', 'Votre compte a été créé avec succès');
                
                return $this->redirectToRoute('app_utilisateur_signin');
                
            } catch (\Exception $e) {
                $this->addFlash('error', 'Une erreur est survenue lors de l\'inscription');
            }
        }
    
        return $this->render('utilisateur/complete_registration.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}