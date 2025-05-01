<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class AppExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('isWithin24Hours', [$this, 'isWithin24Hours']),
        ];
    }

    public function isWithin24Hours(int $timestamp): bool
    {
        $now = time(); // Current timestamp in seconds
        $diffInSeconds = $now - $timestamp;
        $diffInHours = $diffInSeconds / (60 * 60); // Convert to hours
        return $diffInHours <= 24;
    }
}