<?php

namespace App\Repository;

use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Webauthn\PublicKeyCredentialSource;
use Webauthn\Bundle\Repository\PublicKeyCredentialSourceRepositoryInterface;

class PublicKeyCredentialSourceRepository implements PublicKeyCredentialSourceRepositoryInterface
{
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function findOneByCredentialId(string $credentialId): ?PublicKeyCredentialSource
    {
        $users = $this->entityManager->getRepository(Utilisateur::class)->findAll();
        foreach ($users as $user) {
            $credentials = $user->getWebauthnCredentials() ?? [];
            foreach ($credentials as $credential) {
                if ($credential['credentialId'] === base64_encode($credentialId)) {
                    return PublicKeyCredentialSource::createFromArray($credential);
                }
            }
        }
        return null;
    }

    public function findAllForUserEntity(\Webauthn\PublicKeyCredentialUserEntity $userEntity): array
    {
        $user = $this->entityManager->getRepository(Utilisateur::class)
            ->findOneBy(['userHandle' => $userEntity->getId()]);
        if (!$user) {
            return [];
        }

        $credentials = $user->getWebauthnCredentials() ?? [];
        return array_map(fn($credential) => PublicKeyCredentialSource::createFromArray($credential), $credentials);
    }

    public function saveCredentialSource(PublicKeyCredentialSource $credentialSource): void
    {
        $user = $this->entityManager->getRepository(Utilisateur::class)
            ->findOneBy(['userHandle' => $credentialSource->getUserHandle()]);
        if (!$user) {
            throw new \RuntimeException('User not found');
        }

        $credentials = $user->getWebauthnCredentials() ?? [];
        $credentials[] = $credentialSource->toArray();
        $user->setWebauthnCredentials($credentials);

        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }
}