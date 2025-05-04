<?php

namespace App\Service;

use App\Entity\ReservationTransport;
use App\Entity\Utilisateur;
use App\Repository\ReservationTransportRepository;

class CompatibilityService
{
    private ReservationTransportRepository $reservationTransportRepository;

    public function __construct(ReservationTransportRepository $reservationTransportRepository)
    {
        $this->reservationTransportRepository = $reservationTransportRepository;
    }

    public function calculateCompatibility(ReservationTransport $reservation, Utilisateur $transporteur): array
    {
        // Derive preferred zone from transporteur's past reservations
        $pastReservations = $this->reservationTransportRepository->findBy(['transporteur' => $transporteur]);
        $zones = [];
        foreach ($pastReservations as $pastReservation) {
            $depart = explode(' ', $pastReservation->getAdresseDepart() ?? '')[0] ?? '';
            $destination = explode(' ', $pastReservation->getAdresseDestination() ?? '')[0] ?? '';
            if ($depart) $zones[] = strtolower($depart);
            if ($destination) $zones[] = strtolower($destination);
        }
        $preferredZone = !empty($zones) ? array_unique($zones)[0] : 'unknown';

        $depart = explode(' ', $reservation->getAdresseDepart() ?? '')[0] ?? '';
        $destination = explode(' ', $reservation->getAdresseDestination() ?? '')[0] ?? '';
        $isMorning = $reservation->getId() % 2 === 0; // Simulate morning preference

        $zoneMatch = $preferredZone !== 'unknown' && ($depart === $preferredZone || $destination === $preferredZone);
        $timeMatch = $isMorning;

        $score = (($zoneMatch && $timeMatch) ? 100 : ($zoneMatch || $timeMatch)) ? 50 : 0;
        $reasons = [];
        if (!$zoneMatch) {
            $reasons[] = "La zone de départ ($depart) ou destination ($destination) ne correspond pas à votre zone préférée ($preferredZone).";
        }
        if (!$timeMatch) {
            $reasons[] = "L'horaire (après-midi) ne correspond pas à votre préférence matinale.";
        }
        if ($score === 100) {
            $reasons[] = "Compatibilité parfaite : zone et horaire correspondent à vos préférences.";
        }

        $recommendation = $score === 0
            ? "Nous vous recommandons de refuser cette réservation car elle ne correspond pas à vos préférences de zone ou d'horaire."
            : "Vous pouvez envisager d'accepter cette réservation, mais notez les incompatibilités ci-dessus.";

        return [
            'score' => $score,
            'reasons' => $reasons,
            'recommendation' => $recommendation
        ];
    }
}
