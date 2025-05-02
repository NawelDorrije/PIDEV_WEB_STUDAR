<?php

namespace App\Repository;

use App\Entity\Logement;
use App\Entity\Utilisateur;
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

    /**
 * Get the top 3 logements with the highest shares and emoji interactions.
 *
 * @return array Top 3 Logement objects sorted by total interactions (shares + emojis)
 */
public function getTopThreeLogementsByInteractions(): array
{
    // Récupérer tous les logements
    $logements = $this->createQueryBuilder('l')
        ->orderBy('l.id', 'DESC')
        ->getQuery()
        ->getResult();

    // Tableau pour stocker les logements avec leurs scores
    $logementsWithScores = [];

    foreach ($logements as $logement) {
        // Compter les emojis
        $emojis = $logement->getEmojis() ?? [];
        $emojiCount = 0;

        foreach ($emojis as $emoji) {
            if (in_array($emoji, ['❤️', '👍', '👎'])) {
                $emojiCount++;
            }
        }

        // Ajouter le nombre de partages
        $shareCount = $logement->getShareCount() ?? 0;

        // Calculer le score total (emojis + partages)
        $totalScore = $emojiCount + $shareCount;

        // Stocker le logement avec son score
        $logementsWithScores[] = [
            'logement' => $logement,
            'score' => $totalScore,
        ];
    }

    // Trier les logements par score (du plus grand au plus petit)
    usort($logementsWithScores, function ($a, $b) {
        return $b['score'] <=> $a['score'];
    });

    // Extraire les 3 premiers logements
    $topThree = array_slice($logementsWithScores, 0, 3);

    // Retourner uniquement les objets Logement
    return array_map(function ($item) {
        return $item['logement'];
    }, $topThree);
}
   /**
 * Get the top 7 logements by total interactions and calculate interaction differences.
 *
 * @param Utilisateur|null $user Optional user to filter by (for ROLE_PROPRIETAIRE)
 * @return array Interaction differences between consecutive logements
 */
    public function getLastLogementsInteractions(?Utilisateur $user = null): array
    {
        // Fetch the last 7 logements sorted by ID in descending order
        $qb = $this->createQueryBuilder('l')
            ->orderBy('l.id', 'DESC')
            ->setMaxResults(7);

        if ($user) {
            $qb->andWhere('l.utilisateur_cin = :user')
               ->setParameter('user', $user);
        }

        $logements = $qb->getQuery()->getResult();

        // If fewer than 2 logements, return empty data to avoid calculating differences
        if (count($logements) < 2) {
            return [
                'labels' => [],
                'jadoreDiffs' => [],
                'likesDiffs' => [],
                'dislikesDiffs' => [],
                'sharesDiffs' => [],
            'logements' => [],
            ];
        }

        // Calculate interactions for each logement
        $interactions = [];
        foreach ($logements as $logement) {
            $emojis = $logement->getEmojis() ?? [];
            $jadoreCount = 0;
            $likesCount = 0;
            $dislikesCount = 0;

            foreach ($emojis as $emoji) {
                if ($emoji === '❤️') {
                    $jadoreCount++;
                } elseif ($emoji === '👍') {
                    $likesCount++;
                } elseif ($emoji === '👎') {
                    $dislikesCount++;
                }
            }

            $interactions[] = [
                'id' => $logement->getId(),
                'jadore' => $jadoreCount,
                'likes' => $likesCount,
                'dislikes' => $dislikesCount,
                'shares' => $logement->getShareCount(),
            ];
        }

        // Calculate differences between consecutive logements
        $labels = [];
        $jadoreDiffs = [];
        $likesDiffs = [];
        $dislikesDiffs = [];
        $sharesDiffs = [];

        for ($i = 0; $i < count($interactions) - 1; $i++) {
            $current = $interactions[$i];
            $next = $interactions[$i + 1];

            $labels[] = "Logement {$current['id']} → {$next['id']}";
            $jadoreDiffs[] = $current['jadore'] - $next['jadore'];
            $likesDiffs[] = $current['likes'] - $next['likes'];
            $dislikesDiffs[] = $current['dislikes'] - $next['dislikes'];
            $sharesDiffs[] = $current['shares'] - $next['shares'];
        }

        return [
            'labels' => $labels,
            'jadoreDiffs' => $jadoreDiffs,
            'likesDiffs' => $likesDiffs,
            'dislikesDiffs' => $dislikesDiffs,
            'sharesDiffs' => $sharesDiffs,
            'logements' => $logements, // For PDF report details
        ];
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
