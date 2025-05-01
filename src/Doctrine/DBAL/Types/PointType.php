<?php

namespace App\Doctrine\DBAL\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use LongitudeOne\Spatial\PHP\Types\Geometry\Point;

class PointType extends Type
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'POINT';
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?Point
    {
        if ($value === null) {
            return null;
        }

        if (is_resource($value)) {
            $value = stream_get_contents($value);
        }

        // Parse MySQL's internal format (WKB)
        $unpacked = unpack('x4/corder/Ltype/dx/dy', $value);
        
        return new Point([$unpacked['x'], $unpacked['y']]);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!$value instanceof Point) {
            throw new \InvalidArgumentException('Value must be an instance of Point');
        }

        // Create WKB format
        $coordinates = $value->toArray();
        return pack('Ldd', 1, $coordinates[0], $coordinates[1]);
    }

    public function getName(): string
    {
        return 'point';
    }
}