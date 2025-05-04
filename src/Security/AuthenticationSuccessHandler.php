<?php

namespace App\Security;

use App\Entity\Utilisateur;
use App\Enums\RoleUtilisateur;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Psr\Log\LoggerInterface;

class AuthenticationSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    private $urlGenerator;
    private $logger;

    public function __construct(UrlGeneratorInterface $urlGenerator, LoggerInterface $logger)
    {
        $this->urlGenerator = $urlGenerator;
        $this->logger = $logger;
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): ?Response
    {
        $user = $token->getUser();
        $isTwoFactorEnabled = $user instanceof Utilisateur ? $user->isTwoFactorEnabled() : false;
        $session = $request->getSession();
        $twoFactorVerified = $session->get('two_factor_verified', false);

        $this->logger->info('AuthenticationSuccessHandler onAuthenticationSuccess', [
            'user' => $user->getEmail(),
            'instanceofUtilisateur' => $user instanceof Utilisateur,
            'isTwoFactorEnabled' => $isTwoFactorEnabled,
            'session_two_factor_verified' => $twoFactorVerified,
            'redirectTo' => $isTwoFactorEnabled && !$twoFactorVerified ? 'app_utilisateur_two_factor_check' : ($user->getRole() === RoleUtilisateur::ADMIN ? 'app_admin_dashboard' : 'app_home'),
        ]);

        if ($isTwoFactorEnabled && !$twoFactorVerified) {
            $this->logger->info('AuthenticationSuccessHandler redirecting to 2FA check');
            return new RedirectResponse($this->urlGenerator->generate('app_utilisateur_two_factor_check'));
        }

        $redirectUrl = $user->getRole() === RoleUtilisateur::ADMIN
            ? $this->urlGenerator->generate('app_admin_dashboard')
            : $this->urlGenerator->generate('app_home');
        $this->logger->info('AuthenticationSuccessHandler redirecting to role-based URL', ['redirectUrl' => $redirectUrl]);
        return new RedirectResponse($redirectUrl);
    }
}