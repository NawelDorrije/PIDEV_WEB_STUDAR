<?php

namespace App\Doctrine\DBAL\Types;

use App\Enums\RoleUtilisateur;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

class RoleEnumType extends Type
{
    const NAME = 'role_enum';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return "ENUM('ETUDIANT', 'TRANSPORTEUR', 'ADMIN')";
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?RoleUtilisateur
    {
        if ($value === null) {
            return null;
        }

        // $value is a string (e.g., 'TRANSPORTEUR'), convert it to the enum
        return RoleUtilisateur::from($value);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        // $value should be a RoleUtilisateur enum instance, get its string value
        return $value instanceof RoleUtilisateur ? $value->value : (string) $value;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}