<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Doctrine\DBAL\Types\Type;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;
    public function boot(): void
    {
        parent::boot();

        if (!Type::hasType('point')) {
            Type::addType('point', 'App\Doctrine\DBAL\Types\PointType');
        }
    }
}
