<?php

namespace App\Service;

use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

class MercurePublisher
{
    private $hub;

    public function __construct(HubInterface $hub)
    {
        $this->hub = $hub;
    }

    public function publish(string $topic, array $data): void
    {
        $update = new Update(
            $topic,
            json_encode($data)
        );
        $this->hub->publish($update);
    }
}