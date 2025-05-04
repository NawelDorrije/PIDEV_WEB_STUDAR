<?php
namespace App\Security;

use App\Entity\Utilisateur;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class TwoFactorListener
{
    private $tokenStorage;
    private $urlGenerator;

    public function __construct(TokenStorageInterface $tokenStorage, UrlGeneratorInterface $urlGenerator)
    {
        $this->tokenStorage = $tokenStorage;
        $this->urlGenerator = $urlGenerator;
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $currentRoute = $request->attributes->get('_route');
        $session = $request->getSession();
    
        $this->logger->info('TwoFactorListener triggered', [
            'currentRoute' => $currentRoute,
            'session_two_factor_verified' => $session->get('two_factor_verified', false),
        ]);
    
        if (in_array($currentRoute, ['app_utilisateur_two_factor_check', 'app_utilisateur_signin', 'app_logout', 'app_debug_2fa'])) {
            $this->logger->info('Skipping 2FA check for route', ['route' => $currentRoute]);
            return;
        }
    
        $token = $this->tokenStorage->getToken();
        if (!$token) {
            $this->logger->info('No token found, skipping 2FA check');
            return;
        }
    
        $user = $token->getUser();
        if ($user instanceof Utilisateur && $user->isTwoFactorEnabled()) {
            $this->logger->info('User has 2FA enabled', [
                'user' => $user->getEmail(),
                'isTwoFactorEnabled' => $user->isTwoFactorEnabled(),
                'session_two_factor_verified' => $session->get('two_factor_verified', false),
            ]);
    
            if (!$session->get('two_factor_verified', false)) {
                $this->logger->info('2FA not verified, redirecting to app_utilisateur_two_factor_check');
                $event->setResponse(new RedirectResponse($this->urlGenerator->generate('app_utilisateur_two_factor_check')));
            } else {
                $this->logger->info('2FA already verified, no redirect needed');
            }
        }
    }
}