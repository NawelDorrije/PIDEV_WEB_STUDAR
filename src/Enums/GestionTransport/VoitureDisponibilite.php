<?php

namespace App\Enums\GestionTransport;

enum VoitureDisponibilite: string
{
    case DISPONIBLE = 'Disponible';
    case NON_DISPONIBLE = 'Non disponible';
    public static function getValues(): array
    {
        return array_column(self::cases(), 'value');
    }
    
}