<?php

namespace App\Controller\GestionReservation;

use App\Entity\ReservationLogement;
use App\Form\ReservationLogementType;
use App\Repository\ReservationLogementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use TCPDF;
use TCPDF2DBarcode;
use App\Repository\LogementRepository;


#[Route('/reservation/logement_ADMIN')]
final class ReservationLogementController_ADMIN extends AbstractController
{
    #[Route('/', name: 'app_reservation_logement_index_ADMIN', methods: ['GET'])]
    public function index(Request $request, ReservationLogementRepository $reservationLogementRepository, LogementRepository $logementRepository): Response
{

    $status = $request->query->get('status');
    $periode = $request->query->get('periode');

    $qb = $reservationLogementRepository->createQueryBuilder('r');
    if ($status) {
        $qb->andWhere('r.status = :status')->setParameter('status', $status);
    }
    if ($periode === 'month') {
        $start = (new \DateTime('first day of this month'))->setTime(0, 0);
        $end = (new \DateTime('last day of this month'))->setTime(23, 59, 59);
        $qb->andWhere('r.dateDebut BETWEEN :start AND :end')
           ->setParameter('start', $start)
           ->setParameter('end', $end);
    } elseif ($periode === 'year') {
        $start = (new \DateTime('first day of January this year'))->setTime(0, 0);
        $end = (new \DateTime('last day of December this year'))->setTime(23, 59, 59);
        $qb->andWhere('r.dateDebut BETWEEN :start AND :end')
           ->setParameter('start', $start)
           ->setParameter('end', $end);
    }

    $reservations = $qb->getQuery()->getResult();

    // Monthly, Quarterly, Seasonal Statistics
    $monthlyStats = array_fill(1, 12, ['count' => 0, 'totalDays' => 0, 'logements' => []]);
    $quarterlyStats = array_fill(1, 4, ['count' => 0, 'totalDays' => 0]);
    $seasonalStats = ['Hiver' => ['count' => 0, 'totalDays' => 0], 'Printemps' => ['count' => 0, 'totalDays' => 0], 
                      'Été' => ['count' => 0, 'totalDays' => 0], 'Automne' => ['count' => 0, 'totalDays' => 0]];
    $topLogement = ['id' => null, 'count' => 0, 'adresse' => ''];

    foreach ($reservations as $reservation) {
        $month = (int) $reservation->getDateDebut()->format('n');
        $quarter = ceil($month / 3);
        $season = in_array($month, [12, 1, 2]) ? 'Hiver' : 
                  (in_array($month, [3, 4, 5]) ? 'Printemps' : 
                  (in_array($month, [6, 7, 8]) ? 'Été' : 'Automne'));
        $days = $reservation->getDateDebut()->diff($reservation->getDateFin())->days;
        $logementId = is_iterable($reservation->getIdLogement()) ? ($reservation->getIdLogement()['id'] ?? $reservation->getIdLogement()) : $reservation->getIdLogement();

        // Monthly
        $monthlyStats[$month]['count']++;
        $monthlyStats[$month]['totalDays'] += $days;
        $monthlyStats[$month]['logements'][$logementId] = ($monthlyStats[$month]['logements'][$logementId] ?? 0) + 1;

        // Quarterly
        $quarterlyStats[$quarter]['count']++;
        $quarterlyStats[$quarter]['totalDays'] += $days;

        // Seasonal
        $seasonalStats[$season]['count']++;
        $seasonalStats[$season]['totalDays'] += $days;

        // Top Logement
        $logementCounts[$logementId] = ($logementCounts[$logementId] ?? 0) + 1;
        if ($logementCounts[$logementId] > $topLogement['count']) {
            $adresse = is_iterable($reservation->getIdLogement()) ? ($reservation->getIdLogement()['adresse'] ?? 'Logement #' . $logementId) : 
                       ($logementRepository->find($logementId)?->getAdresse() ?? $logementId);
            $topLogement = ['id' => $logementId, 'count' => $logementCounts[$logementId], 'adresse' => $adresse];
        }
    }

    // Calculate averages
    foreach ($monthlyStats as &$month) {
        $month['avgDays'] = $month['count'] > 0 ? round($month['totalDays'] / $month['count'], 1) : 0;
        $month['topLogement'] = !empty($month['logements']) ? array_search(max($month['logements']), $month['logements']) : null;
    }
    foreach ($quarterlyStats as &$quarter) {
        $quarter['avgDays'] = $quarter['count'] > 0 ? round($quarter['totalDays'] / $quarter['count'], 1) : 0;
    }
    foreach ($seasonalStats as &$season) {
        $season['avgDays'] = $season['count'] > 0 ? round($season['totalDays'] / $season['count'], 1) : 0;
    }

    // Status breakdown
    $statusCounts = ['confirmée' => 0, 'en_attente' => 0, 'refusée' => 0];
    foreach ($reservations as $reservation) {
        $statusCounts[$reservation->getStatus()]++;
    }
    $total = array_sum($statusCounts);
    $peakStatus = $total > 0 ? array_search(max($statusCounts), $statusCounts) : 'Aucun';
    $peakStatusPercent = $total > 0 ? round((max($statusCounts) / $total) * 100, 1) : 0;

    return $this->render('reservation_logement/index_ADMIN.html.twig', [
        'reservation_logements' => $reservations,
        'current_status' => $status,
        'current_periode' => $periode,
        'logement_repo' => $logementRepository,
        'stats' => [
            'monthly' => $monthlyStats,
            'quarterly' => $quarterlyStats,
            'seasonal' => $seasonalStats,
            'topLogement' => $topLogement,
            'peakStatus' => ['status' => $peakStatus, 'percent' => $peakStatusPercent]
        ],
    ]);
}
  
    #[Route('/{id}', name: 'app_reservation_logement_show_ADMIN', methods: ['GET'])]
    public function show(ReservationLogement $reservationLogement, LogementRepository $logementRepository): Response
    {
        return $this->render('reservation_logement/show_ADMIN.html.twig', [
            'reservation_logement' => $reservationLogement,
            'logement_repo' => $logementRepository

        ]);
    }

#[Route('/statistics/owner', name: 'app_reservation_logement_statistics_owner')]
public function statisticsOwner(ReservationLogementRepository $repository, LogementRepository $logementRepository): Response
{
    // Récupérer le CIN du propriétaire connecté (à adapter selon votre système d'authentification)
    $cinProprietaire = $this->getUser()->getCin(); // Adaptez cette ligne
    
    $stats = $repository->getMonthlyStatisticsForOwner($cinProprietaire);
    
    return $this->render('reservation_logement/statistics_owner.html.twig', [
        'stats' => $stats,
        'max' => !empty($stats) ? max(array_column($stats, 'count')) : 0
    ]);
}
}
