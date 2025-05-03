<?php

namespace App\Security;

use App\Entity\Utilisateur;
use App\Enums\RoleUtilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class AppAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public const LOGIN_ROUTE = 'app_utilisateur_signin';

    private EntityManagerInterface $entityManager;
    private UrlGeneratorInterface $urlGenerator;
    private LoggerInterface $logger;

    public function __construct(
        EntityManagerInterface $entityManager,
        UrlGeneratorInterface $urlGenerator,
        LoggerInterface $logger
    ) {
        $this->entityManager = $entityManager;
        $this->urlGenerator = $urlGenerator;
        $this->logger = $logger;
    }

    public function supports(Request $request): bool
    {
        $supports = $request->attributes->get('_route') === self::LOGIN_ROUTE && $request->isMethod('POST');
        $this->logger->info('AppAuthenticator supports check', [
            'route' => $request->attributes->get('_route'),
            'method' => $request->getMethod(),
            'supports' => $supports,
        ]);
        return $supports;
    }

    protected function getLoginUrl(Request $request): string
    {
        $this->logger->info('AppAuthenticator getLoginUrl called');
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }

    public function authenticate(Request $request): Passport
    {
        $email = $request->request->get('email', '');
        $request->getSession()->set('_security.last_username', $email);

        $this->logger->info('AppAuthenticator authenticate', [
            'email' => $email,
        ]);

        return new Passport(
            new UserBadge($email),
            new PasswordCredentials($request->request->get('password', '')),
            [
                new CsrfTokenBadge('authenticate', $request->request->get('_csrf_token')),
                new RememberMeBadge(),
            ]
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $user = $token->getUser();
        $this->logger->info('AppAuthenticator onAuthenticationSuccess', [
            'user' => $user->getEmail(),
            'isTwoFactorEnabled' => $user instanceof Utilisateur ? $user->isTwoFactorEnabled() : false,
        ]);

        if ($user instanceof Utilisateur && $user->isTwoFactorEnabled()) {
            return new RedirectResponse($this->urlGenerator->generate('app_two_factor_check'));
        }

        if ($targetPath = $this->getTargetPath($request->getSession(), $firewallName)) {
            return new RedirectResponse($targetPath);
        }

        return new RedirectResponse(
            $user->getRole() === RoleUtilisateur::ADMIN
                ? $this->urlGenerator->generate('app_admin_dashboard')
                : $this->urlGenerator->generate('app_home')
        );
    }
}