<?php

namespace App\Repository;

use App\Entity\Logement;
use App\Enums\RoleUtilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @extends ServiceEntityRepository<Logement>
 */
class LogementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Logement::class);
    }

    public function findNearby(
        ?string $type,
        ?float $maxPrice,
        ?int $rooms,
        ?float $lat,
        ?float $lng,
        int $radius
    ): array {
        if ($type === null && $maxPrice === null && $rooms === null) {
            // No filters applied, return all results
            return $this->findAll();
        }

        $qb = $this->createQueryBuilder('l')
            ->andWhere('l.type = COALESCE(:type, l.type)')
            ->andWhere('l.prix <= COALESCE(:maxPrice, l.prix)')
            ->andWhere('l.nbrChambre = COALESCE(:rooms, l.nbrChambre)')
            ->setParameter('type', $type)
            ->setParameter('maxPrice', $maxPrice)
            ->setParameter('rooms', $rooms);

        return $qb->getQuery()->getResult();
    }
    public function findAllSortedByIdDesc(): array
    {
        return $this->createQueryBuilder('l')
            ->orderBy('l.id', 'DESC') // Sort by ID, most recent first
            ->getQuery()
            ->getResult();
    }
    public function findByUserRole(?UserInterface $user): array
    {
        // Handle unauthenticated users
        if (!$user) {
            return $this->createQueryBuilder('l')
                ->orderBy('l.id', 'DESC')
                ->getQuery()
                ->getResult();
        }
    
        // Get the user's roles (already an array)
        $userRoles = $user->getRoles();
    
        // Propriétaire: Fetch only their own logements
        if (in_array(RoleUtilisateur::PROPRIETAIRE->value, $userRoles)) {
            return $this->createQueryBuilder('l')
                ->where('l.utilisateurCin = :userCin')
                // Utilisez soit getCin() soit getUserIdentifier() selon votre implémentation
                ->setParameter('userCin', $user->getUserIdentifier()) // ou $user->getUserIdentifier()
                ->orderBy('l.id', 'DESC')
                ->getQuery()
                ->getResult();
        }
    
        // Étudiant, Admin, or Transporteur: Fetch all logements
        $allowedRoles = [
            RoleUtilisateur::ETUDIANT->value,
            RoleUtilisateur::ADMIN->value,
            RoleUtilisateur::TRANSPORTEUR->value
        ];
        
        if (array_intersect($allowedRoles, $userRoles)) {
            return $this->createQueryBuilder('l')
                ->orderBy('l.id', 'DESC')
                ->getQuery()
                ->getResult();
        }
    
        // Default: Return empty array if role doesn't match
        return [];
    }
    //    /**
//     * @return Logement[] Returns an array of Logement objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('l')
//            ->andWhere('l.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('l.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Logement
//    {
//        return $this->createQueryBuilder('l')
//            ->andWhere('l.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
