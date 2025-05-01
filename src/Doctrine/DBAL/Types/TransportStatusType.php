<?php

namespace App\Doctrine\DBAL\Types;

use App\Enums\GestionTransport\TransportStatus;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

class TransportStatusType extends Type
{
    public const NAME = 'transport_status';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getVarcharTypeDeclarationSQL([
            'length' => 20,
            'fixed' => false,
        ]);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?TransportStatus
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return TransportStatus::from($value);
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

        if ($value instanceof TransportStatus) {
            return $value->value;
        }

        throw new \InvalidArgumentException(sprintf(
            'Expected instance of %s, got %s',
            TransportStatus::class,
            gettype($value)
        ));  // Added missing parenthesis here
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