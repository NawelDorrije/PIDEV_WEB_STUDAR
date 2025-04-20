<?php

namespace App\Form\DataTransformer;

use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

class DateTimeToStringTransformer implements DataTransformerInterface
{
    public function transform($string): ?\DateTimeImmutable
    {
        if (null === $string || '' === $string) {
            return null;
        }

        try {
            return new \DateTimeImmutable($string);
        } catch (\Exception $e) {
            throw new TransformationFailedException('Invalid date string: ' . $e->getMessage());
        }
    }

    public function reverseTransform($dateTime): ?string
    {
        if (null === $dateTime) {
            return null;
        }

        if (!$dateTime instanceof \DateTimeInterface) {
            throw new TransformationFailedException('Expected a DateTimeInterface, got ' . gettype($dateTime));
        }

        return $dateTime->format('Y-m-d H:i:s');
    }
}