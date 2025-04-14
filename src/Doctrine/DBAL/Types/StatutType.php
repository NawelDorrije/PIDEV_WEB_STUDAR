<?php

namespace App\Doctrine\DBAL\Types;

use App\Enums\Statut;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

class StatutType extends Type
{
    const NAME = 'statut';

    public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform): string
    {
        return "ENUM('DISPONIBLE', 'NON_DISPONIBLE')";
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?Statut
    {
        if ($value === null) {
            return null;
        }

        return Statut::from($value);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!$value instanceof Statut) {
            throw new \InvalidArgumentException('Value must be a Statut enum');
        }

        return $value->value; 
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