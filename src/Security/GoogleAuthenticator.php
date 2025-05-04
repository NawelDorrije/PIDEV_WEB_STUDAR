<?php
namespace App\Security;

use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Utilisateur;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use League\OAuth2\Client\Provider\GoogleUser;

class GoogleAuthenticator extends OAuth2Authenticator
{
    private $clientRegistry;
    private $entityManager;
    private $urlGenerator;

    public function __construct(
        ClientRegistry $clientRegistry,
        EntityManagerInterface $entityManager,
        UrlGeneratorInterface $urlGenerator
    ) {
        $this->clientRegistry = $clientRegistry;
        $this->entityManager = $entityManager;
        $this->urlGenerator = $urlGenerator;
    }

    public function supports(Request $request): ?bool
    {
        return $request->attributes->get('_route') === 'connect_google_check';
    }

    public function authenticate(Request $request): Passport
    {
        $client = $this->clientRegistry->getClient('google');
        $accessToken = $this->fetchAccessToken($client);

        return new SelfValidatingPassport(
            new UserBadge($accessToken->getToken(), function() use ($accessToken, $client, $request) {
                /** @var GoogleUser $googleUser */
                $googleUser = $client->fetchUserFromToken($accessToken);
                
                $user = $this->entityManager->getRepository(Utilisateur::class)
                    ->findOneBy(['email' => $googleUser->getEmail()]);

                if ($user) {
                    return $user;
                }

                $request->getSession()->set('google_user_data', [
                    'email' => $googleUser->getEmail(),
                    'firstName' => $googleUser->getFirstName(),
                    'lastName' => $googleUser->getLastName()
                ]);

                throw new CustomUserMessageAuthenticationException(
                    'complete_registration'
                );
            })
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $user = $token->getUser();

        $request->getSession()->getFlashBag()->add(
            'success-auto-fade',
            'Connexion réussie avec Google'
        );

        $session = $request->getSession();
        if ($user instanceof Utilisateur && $user->isTwoFactorEnabled() && !$session->get('two_factor_verified', false)) {
            return new RedirectResponse($this->urlGenerator->generate('app_utilisateur_two_factor_check'));
        }

        return new RedirectResponse($this->urlGenerator->generate(
            $user->getRole() === 'ROLE_ADMIN' ? 'app_admin_dashboard' : 'app_home'
        ));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        if ($exception->getMessageKey() === 'complete_registration') {
            return new RedirectResponse($this->urlGenerator->generate('app_utilisateur_complete_registration'));
        }
        
        $request->getSession()->getFlashBag()->add('error', $exception->getMessageKey());
        return new RedirectResponse($this->urlGenerator->generate('app_utilisateur_signin'));
    }
}