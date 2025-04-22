<?php

namespace App\Controller\GestionReservation;

use App\Enums\RoleUtilisateur;
use App\Entity\ReservationTransport;
use App\Entity\Utilisateur;
use App\Repository\ReservationTransportRepository;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ReservationTransportController_ADMIN extends AbstractController
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    #[Route('/reservation/transport_ADMIN', name: 'app_reservation_transport_index_ADMIN', methods: ['GET'])]
    public function index(Request $request, ReservationTransportRepository $reservationTransportRepository, UtilisateurRepository $utilisateurRepository): Response
    {
    
        $status = $request->query->get('status');
        $dateStart = $request->query->get('date_start');
        $dateEnd = $request->query->get('date_end');

        $qb = $reservationTransportRepository->createQueryBuilder('r');

        if ($status) {
            $qb->andWhere('r.status = :status')->setParameter('status', $status);
        }

        if ($dateStart && $dateEnd) {
            $qb->andWhere('r.createdAt BETWEEN :date_start AND :date_end')
               ->setParameter('date_start', new \DateTime($dateStart))
               ->setParameter('date_end', (new \DateTime($dateEnd))->modify('+1 day'));
        }

        $reservations = $qb->getQuery()->getResult();
        $transporteurs = $utilisateurRepository->createQueryBuilder('u')
            ->where('u.role = :role')
            ->setParameter('role', RoleUtilisateur::TRANSPORTEUR)
            ->getQuery()
            ->getResult();

        return $this->render('reservation_transport/index_ADMIN.html.twig', [
            'reservations' => $reservations,
            'current_status' => $status,
            'date_start' => $dateStart,
            'date_end' => $dateEnd,
            'transporteurs' => $transporteurs,
        ]);
    }

    #[Route('/api/reservation_transport/{id}/transporteur', name: 'app_reservation_transport_update_transporteur', methods: ['PATCH'])]
    public function updateTransporteur(Request $request, ReservationTransport $reservation, UtilisateurRepository $utilisateurRepository): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $transporteurCin = $data['transporteur_cin'] ?? null;

        if (!$transporteurCin) {
            return $this->json(['error' => 'Transporteur CIN requis'], 400);
        }

        $transporteur = $utilisateurRepository->findOneBy(['cin' => $transporteurCin, 'role' => RoleUtilisateur::TRANSPORTEUR]);
        if (!$transporteur) {
            return $this->json(['error' => 'Transporteur non trouvé'], 404);
        }

        $reservation->setTransporteur($transporteur);
        $this->entityManager->flush();

        return $this->json(['success' => true]);
    }


  
    #[Route('/{id}', name: 'app_reservation_transport_show_ADMIN', methods: ['GET'])]
    public function show(ReservationTransport $reservationTransport): Response
    {
        return $this->render('reservation_transport/show_ADMIN.html.twig', [
            'reservation_transport' => $reservationTransport,
        ]);
    }

    

    
}
