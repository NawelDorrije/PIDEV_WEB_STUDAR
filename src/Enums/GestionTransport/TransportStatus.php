<?php

namespace App\Enums;

enum TransportStatus: string
{
    case ACTIF = 'Actif';
    case COMPLETE = 'Complété';
}