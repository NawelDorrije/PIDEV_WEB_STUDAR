<?php

namespace App\Enums\GestionReservation;

enum StatusReservation: string
{
    case CONFIRMEE = 'confirmée';
    case EN_ATTENTE = 'en_attente';
    case REFUSEE = 'refusée';
}