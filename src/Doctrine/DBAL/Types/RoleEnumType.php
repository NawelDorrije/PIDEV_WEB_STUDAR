<?php
namespace App\Doctrine\DBAL\Types;

use App\Enums\RoleUtilisateur;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

class RoleEnumType extends Type
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'VARCHAR(20)';
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?RoleUtilisateur
    {
        return $value ? RoleUtilisateur::from($value) : null;
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        return $value?->value;
    }

    public function getName(): string
    {
        return 'role_enum';
    }
}