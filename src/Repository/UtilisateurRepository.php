<?php

namespace App\Repository;

use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\User\UserInterface;
use Webauthn\Bundle\Repository\PublicKeyCredentialUserEntityRepositoryInterface;
use Webauthn\PublicKeyCredentialUserEntity;

/**
 * @extends ServiceEntityRepository<Utilisateur>
 */
class UtilisateurRepository extends ServiceEntityRepository implements PublicKeyCredentialUserEntityRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Utilisateur::class);
    }

    public function loadUserByIdentifier(string $identifier): ?UserInterface
    {
        return $this->createQueryBuilder('u')
            ->where('u.email = :email')
            ->setParameter('email', $identifier)
            ->getQuery()
            ->getOneOrNullResult();
    }

    // public function getUserCountByRole(): array
    // {
    //     return $this->createQueryBuilder('u')
    //         ->select('u.role, COUNT(u.id) as count')
    //         ->groupBy('u.role')
    //         ->getQuery()
    //         ->getResult();
    // }

    public function findOneByUserHandle(string $userHandle): ?PublicKeyCredentialUserEntity
    {
        $user = $this->createQueryBuilder('u')
            ->where('u.userHandle = :userHandle')
            ->setParameter('userHandle', $userHandle)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$user) {
            return null;
        }

        return new PublicKeyCredentialUserEntity(
            $user->getEmail(),
            $user->getUserHandle(),
            $user->getDisplayName(),
            $user->getCin()
        );
    }

    public function findOneByUsername(string $username): ?PublicKeyCredentialUserEntity
    {
        $user = $this->createQueryBuilder('u')
            ->where('u.email = :email')
            ->setParameter('email', $username)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$user) {
            return null;
        }

        return new PublicKeyCredentialUserEntity(
            $user->getEmail(),
            $user->getUserHandle(),
            $user->getDisplayName(),
            $user->getCin()
        );
    }

    public function saveUserEntity(PublicKeyCredentialUserEntity $userEntity): void
    {
        $user = $this->createQueryBuilder('u')
            ->where('u.userHandle = :userHandle')
            ->setParameter('userHandle', $userEntity->getId())
            ->getQuery()
            ->getOneOrNullResult();

        if (!$user) {
            throw new \RuntimeException('User not found; cannot create new users via WebAuthn');
        }

        $user->setEmail($userEntity->getName());
        $user->setNom($userEntity->getDisplayName());

        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    public function getUserCountByRole(): array
{
    $results = $this->createQueryBuilder('u')
        ->select('u.role as role, COUNT(u.cin) as count')
        ->groupBy('u.role')
        ->getQuery()
        ->getResult();

    // Convert enum objects to strings
    return array_map(function ($stat) {
        return [
            'role' => $stat['role'] instanceof \App\Enums\RoleUtilisateur ? $stat['role']->value : $stat['role'],
            'count' => $stat['count']
        ];
    }, $results);
}
}