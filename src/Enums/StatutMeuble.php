<?php
// src/Enums/StatutMeuble.php

namespace App\Enums;

enum StatutMeuble: string
{
    case DISPONIBLE = 'disponible';
    case INDISPONIBLE = 'indisponible';
}