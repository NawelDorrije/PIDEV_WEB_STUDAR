<?php

namespace App\Enums;

enum RoleUtilisateur: string
{
    case PROPRIETAIRE = 'propriétaire';
    case TRANSPORTEUR = 'transporteur';
    case ETUDIANT = 'étudiant';
    case ADMIN = 'admin';
}