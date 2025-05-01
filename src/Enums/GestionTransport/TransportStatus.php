<?php

namespace App\Enums\GestionTransport;

enum TransportStatus: string
{
    case ACTIF = 'Actif';
    case PENDING = 'En attente';
    case COMPLETE = 'Complété';

    public static function getValues(): array
    {
        return array_column(self::cases(), 'value');
    }
}