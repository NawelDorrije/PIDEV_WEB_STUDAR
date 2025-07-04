<?php

namespace App\Repository;

use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\User\UserInterface;
use Webauthn\PublicKeyCredentialUserEntity;
use App\Entity\Message;

/**
 * @extends ServiceEntityRepository<Utilisateur>
 */
class UtilisateurRepository extends ServiceEntityRepository 
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

    public function findByCin(string $cin): ?Utilisateur
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.cin = :cin')
            ->setParameter('cin', $cin)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$user) {
            return null;
        }

        // return new PublicKeyCredentialUserEntity(
        //     $user->getEmail(),
        //     $user->getUserHandle(),
        //     $user->getDisplayName(),
        //     $user->getCin()
        // );
    }

    public function findOneByUsername(string $username)
    {
        $user = $this->createQueryBuilder('u')
            ->where('u.email = :email')
            ->setParameter('email', $username)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$user) {
            return null;
        }

        // return new PublicKeyCredentialUserEntity(
        //     $user->getEmail(),
        //     $user->getUserHandle(),
        //     $user->getDisplayName(),
        //     $user->getCin()
        // );
    }

    // public function saveUserEntity(PublicKeyCredentialUserEntity $userEntity): void
    // {
    //     $user = $this->createQueryBuilder('u')
    //         ->where('u.userHandle = :userHandle')
    //         ->setParameter('userHandle', $userEntity->getCin())
    //         ->getQuery()
    //         ->getOneOrNullResult();

    //     if (!$user) {
    //         throw new \RuntimeException('User not found; cannot create new users via WebAuthn');
    //     }

    //     $user->setEmail($userEntity->getName());
    //     $user->setNom($userEntity->getDisplayName());

    //     $this->getEntityManager()->persist($user);
    //     $this->getEntityManager()->flush();
    // }

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

// alaaa focntion 
    /**@param Utilisateur $currentUser
     * @return array
     */
    public function findAllWithLastMessage(Utilisateur $currentUser): array
    {
        $qb = $this->createQueryBuilder('u')
            ->where('u != :currentUser')
            ->setParameter('currentUser', $currentUser);

        $users = $qb->getQuery()->getResult();

        $entityManager = $this->getEntityManager();
        $messageRepository = $entityManager->getRepository(Message::class);

        $result = [];
        foreach ($users as $user) {
            $lastMessage = $messageRepository->createQueryBuilder('m')
                ->where('(m.senderCin = :currentUser AND m.receiverCin = :user) OR (m.senderCin = :user AND m.receiverCin = :currentUser)')
                ->setParameter('currentUser', $currentUser)
                ->setParameter('user', $user)
                ->orderBy('m.timestamp', 'DESC')
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            $result[] = [
                'user' => $user,
                'lastMessage' => $lastMessage,
            ];
        }

        return $result;
    }
}