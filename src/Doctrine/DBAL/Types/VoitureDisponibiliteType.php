<?php

namespace App\Doctrine\DBAL\Types;

use App\Enums\GestionTransport\VoitureDisponibilite;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

class VoitureDisponibiliteType extends Type
{
    const NAME = 'voiture_disponibilite';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        // Use VARCHAR instead of ENUM for compatibility
        return $platform->getVarcharTypeDeclarationSQL([
            'length' => 20,
            'fixed' => false
        ]);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?VoitureDisponibilite
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return VoitureDisponibilite::from($value);
        } catch (\ValueError $e) {
            throw new \InvalidArgumentException(sprintf(
                'Invalid value "%s" for enum type "%s"',
                $value,
                self::NAME
            ));
        }
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof VoitureDisponibilite) {
            return $value->value;
        }

        throw new \InvalidArgumentException(sprintf(
            'Expected instance of %s, got %s',
            VoitureDisponibilite::class,
            gettype($value)
        ));
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