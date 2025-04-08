<?php

namespace App\Doctrine\DBAL\Types;

use App\Enums\RoleUtilisateur;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;

class EnumType extends Type
{
    public const NAME = 'enum';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        // Specific implementation for RoleUtilisateur
        return "ENUM('propriétaire', 'transporteur', 'étudiant', 'admin')";
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!$value instanceof RoleUtilisateur) {
            throw new \InvalidArgumentException('Expected RoleUtilisateur instance');
        }

        return $value->value;
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?RoleUtilisateur
    {
        if ($value === null) {
            return null;
        }

        return RoleUtilisateur::tryFrom($value) ?? throw ConversionException::conversionFailed(
            $value,
            $this->getName(),
            sprintf('Invalid value "%s" for enum %s', $value, RoleUtilisateur::class)
        );
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