<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use App\Entity\Utilisateur;
use OTPHP\TOTP;
use App\Enums\RoleUtilisateur;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class SecurityController extends AbstractController
{
    #[Route('/logout', name: 'app_logout', methods: ['GET'])]
    public function logout(): void
    {
        // This method can be empty - it will be intercepted by the logout key on your firewall
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
   // Likely in src/Controller/SecurityController.php
   #[Route('/2fa/check', name: 'app_two_factor_check', methods: ['GET', 'POST'])]
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
           if ($code === null || $code === '') {
               $error = 'Veuillez entrer un code de vérification.';
           } else {
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
       }

       return $this->render('utilisateur/two_factor.html.twig', [
           'error' => $error,
       ]);
   }
}