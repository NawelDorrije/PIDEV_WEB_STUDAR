<?php

namespace App\Security;

use App\Enums\RoleUtilisateur;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;


class AuthenticationSuccessHandler implements AuthenticationSuccessHandlerInterface
{
     private $urlGenerator;

     public function __construct(UrlGeneratorInterface $urlGenerator)
     {
         $this->urlGenerator = $urlGenerator;
     }

     public function onAuthenticationSuccess(Request $request, TokenInterface $token): RedirectResponse
     {
         $user = $token->getUser();
        
         if (in_array(RoleUtilisateur::ADMIN, $user->getRoles())) {
             return new RedirectResponse($this->urlGenerator->generate('app_admin_dashboard'));
         }
        
         return new RedirectResponse($this->urlGenerator->generate('app_home'));
     }
}