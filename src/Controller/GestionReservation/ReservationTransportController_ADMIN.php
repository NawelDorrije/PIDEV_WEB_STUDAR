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
use App\Service\CompatibilityService;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Psr\Log\LoggerInterface;

class ReservationTransportController_ADMIN extends AbstractController
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    #[Route('/reservation/transport_ADMIN/{id}', name: 'app_reservation_transport_index_ADMIN', methods: ['GET'])]   
    public function index(Request $request, ReservationTransportRepository $reservationTransportRepository, UtilisateurRepository $utilisateurRepository): Response
    {
        $status = $request->query->get('status');
        $dateStart = $request->query->get('date_start');
        $dateEnd = $request->query->get('date_end');
        $compatibility = $request->query->get('compatibility');

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
            'current_compatibility' => $compatibility,
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

    #[Route('/api/reservation_transport/{id}/status', name: 'app_reservation_transport_update_status', methods: ['PATCH'])]
    public function updateStatus(
        Request $request,
        ReservationTransport $reservation,
        EntityManagerInterface $entityManager,
        CompatibilityService $compatibilityService,
        MailerInterface $mailer,
        LoggerInterface $logger
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $newStatus = $data['status'] ?? null;
        if ($newStatus === 'en_attente' && $reservation->getTransporteur()) {
            $transporteur = $reservation->getTransporteur();
            $emailAddress = $transporteur->getEmail();
            if (empty($emailAddress)) {
                $logger->warning('Transporteur email is empty', ['transporteur_cin' => $transporteur->getCin(), 'reservation_id' => $reservation->getId()]);
                return $this->json(['success' => true, 'warning' => 'No email address for transporteur']);
            }
        
            $compatibility = $compatibilityService->calculateCompatibility($reservation, $transporteur);
            $logger->debug('Compatibility calculated', ['score' => $compatibility['score'], 'reservation_id' => $reservation->getId()]);
        
            if ($compatibility['score'] === 0) {
                try {
                    $email = (new Email())
                        ->from('chocnour@gmail.com')
                        ->to($emailAddress)
                        ->subject('Avis sur la réservation #' . $reservation->getId())
                        ->html('
                            <h2>Réservation #' . $reservation->getId() . '</h2>
                            <p>Cher(e) ' . $transporteur->getNom() . ' ' . $transporteur->getPrenom() . ',</p>
                            <p>La réservation suivante est en attente de votre confirmation, mais présente une faible compatibilité avec vos préférences :</p>
                            <ul>' . implode('', array_map(fn($r) => "<li>$r</li>", $compatibility['reasons'])) . '</ul>
                            <p><strong>Recommandation :</strong> ' . $compatibility['recommendation'] . '</p>
                            <p>Veuillez confirmer ou refuser la réservation dans votre tableau de bord.</p>
                            <p>Cordialement,<br>L\'équipe de gestion des transports</p>
                        ');
        
                    $mailer->send($email);
                    $logger->info('Email sent to transporteur', [
                        'transporteur' => $emailAddress,
                        'reservation_id' => $reservation->getId(),
                    ]);
                } catch (\Exception $e) {
                    $logger->error('Failed to send email', [
                        'error' => $e->getMessage(),
                        'transporteur' => $emailAddress,
                        'reservation_id' => $reservation->getId(),
                    ]);
                    return $this->json(['success' => true, 'warning' => 'Email sending failed: ' . $e->getMessage()]);
                }
            } else {
                $logger->debug('Email not sent: compatibility score not low', ['score' => $compatibility['score'], 'reservation_id' => $reservation->getId()]);
            }
        } else {
            $logger->debug('Email not sent: conditions not met', [
                'status' => $newStatus,
                'has_transporteur' => $reservation->getTransporteur() ? true : false,
                'reservation_id' => $reservation->getId(),
            ]);
        }

        return $this->json(['success' => true]);
    }

    #[Route('/api/test-email', name: 'app_test_email', methods: ['GET'])]
    public function testEmail(MailerInterface $mailer, LoggerInterface $logger): JsonResponse
    {
        try {
            $email = (new Email())
                ->from('chocnour@gmail.com')
                ->to('nourimen2024isim@gmail.com')
                ->subject('Test Email')
                ->html('<p>This is a test email from your Symfony application.</p>');
    
            $mailer->send($email);
            $logger->info('Test email sent successfully');
            return $this->json(['success' => true, 'message' => 'Test email sent']);
        } catch (\Exception $e) {
            $logger->error('Failed to send test email: ' . $e->getMessage());
            return $this->json(['error' => 'Failed to send test email', 'details' => $e->getMessage()], 500);
        }
    }

    #[Route('/api/transporteur/{cin}/send-compatibility-email', name: 'app_transporteur_send_compatibility_email', methods: ['POST'])]
    public function sendCompatibilityEmail(
        string $cin,
        UtilisateurRepository $utilisateurRepository,
        ReservationTransportRepository $reservationTransportRepository,
        CompatibilityService $compatibilityService,
        MailerInterface $mailer,
        LoggerInterface $logger
    ): JsonResponse {
        $transporteur = $utilisateurRepository->findOneBy(['cin' => $cin, 'role' => RoleUtilisateur::TRANSPORTEUR]);
        if (!$transporteur) {
            return $this->json(['error' => 'Transporteur non trouvé'], 404);
        }

        $emailAddress = $transporteur->getEmail();
        if (empty($emailAddress)) {
            $logger->warning('Transporteur email is empty', ['transporteur_cin' => $cin]);
            return $this->json(['error' => 'No email address for transporteur'], 400);
        }

        $enAttenteReservations = $reservationTransportRepository->findBy([
            'transporteur' => $transporteur,
            'status' => 'en_attente'
        ]);

        if (empty($enAttenteReservations)) {
            return $this->json(['success' => true, 'message' => 'Aucune réservation en attente pour ce transporteur']);
        }

        $htmlContent = '
            <h2>Réservations en Attente - Recommandations</h2>
            <p>Cher(e) ' . $transporteur->getNom() . ' ' . $transporteur->getPrenom() . ',</p>
            <p>Voici un résumé de vos réservations en attente avec des recommandations basées sur la compatibilité avec vos préférences :</p>
        ';

        foreach ($enAttenteReservations as $reservation) {
            $compatibility = $compatibilityService->calculateCompatibility($reservation, $transporteur);
            $htmlContent .= '
                <div style="margin-bottom: 1.5rem; padding: 1rem; border: 1px solid #e2e8f0; border-radius: 8px;">
                    <h3>Réservation #' . $reservation->getId() . '</h3>
                    <p><strong>Départ :</strong> ' . $reservation->getAdresseDepart() . '</p>
                    <p><strong>Destination :</strong> ' . $reservation->getAdresseDestination() . '</p>
                    <p><strong>Score de compatibilité :</strong> ' . $compatibility['score'] . '%</p>
                    <p><strong>Raisons :</strong></p>
                    <ul>' . implode('', array_map(fn($r) => "<li>$r</li>", $compatibility['reasons'])) . '</ul>
                    <p><strong>Recommandation :</strong> ' . $compatibility['recommendation'] . '</p>
                </div>
            ';
        }

        $htmlContent .= '
            <p>Veuillez examiner ces réservations et confirmer ou refuser dans votre tableau de bord.</p>
            <p>Cordialement,<br>L\'équipe de gestion des transports</p>
        ';

        try {
            $email = (new Email())
                ->from('chocnour@gmail.com')
                ->to($emailAddress)
                ->subject('Recommandations pour vos réservations en attente')
                ->html($htmlContent);

            $mailer->send($email);
            $logger->info('Compatibility email sent to transporteur', [
                'transporteur' => $emailAddress,
                'reservations' => array_map(fn($r) => $r->getId(), $enAttenteReservations),
            ]);

            return $this->json(['success' => true, 'message' => 'Email de recommandations envoyé avec succès']);
        } catch (\Exception $e) {
            $logger->error('Failed to send compatibility email', [
                'error' => $e->getMessage(),
                'transporteur' => $emailAddress,
            ]);
            return $this->json(['error' => 'Échec de l\'envoi de l\'email: ' . $e->getMessage()], 500);
        }
    }
}