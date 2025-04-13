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
        EntityManagerInterface $entityManager,  // Corrected type-hint
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

    // src/Security/GoogleAuthenticator.php
// src/Security/GoogleAuthenticator.php
public function authenticate(Request $request): Passport
{
    $client = $this->clientRegistry->getClient('google');
    $accessToken = $this->fetchAccessToken($client);

    return new SelfValidatingPassport(
        new UserBadge($accessToken->getToken(), function() use ($accessToken, $client) {
            /** @var GoogleUser $googleUser */
            $googleUser = $client->fetchUserFromToken($accessToken);
            
            $user = $this->entityManager->getRepository(Utilisateur::class)
                ->findOneBy(['email' => $googleUser->getEmail()]);

            if (!$user) {
                throw new CustomUserMessageAuthenticationException(
                    'Vous n\'avez pas un compte avec cette email. Veuillez vous inscrire d\'abord.'
                );
            }

            return $user;
        })
    );
}
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return new RedirectResponse($this->urlGenerator->generate('app_home'));
    }

    // public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    // {
    //     if ($exception instanceof CustomUserMessageAuthenticationException) {
    //         $request->getSession()->set(Security::AUTHENTICATION_ERROR, $exception);
    //         return new RedirectResponse($this->urlGenerator->generate('app_utilisateur_signin'));
    //     }
        
    //     return new Response(
    //         strtr($exception->getMessageKey(), $exception->getMessageData()),
    //         Response::HTTP_UNAUTHORIZED
    //     );
    // }
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
{
    if ($exception instanceof CustomUserMessageAuthenticationException) {
        $request->getSession()->getFlashBag()->add('error', $exception->getMessageKey());
    }
    return new RedirectResponse($this->urlGenerator->generate('app_utilisateur_signin'));
}
}