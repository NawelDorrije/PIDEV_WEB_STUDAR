<?php
namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class Geocoder
{
    public function __construct(private HttpClientInterface $client) {}

    public function geocode(string $address): ?array
    {
        $response = $this->client->request(
            'GET',
            'https://nominatim.openstreetmap.org/search',
            [
                'query' => [
                    'q' => $address,
                    'format' => 'json',
                    'limit' => 1
                ]
            ]
        );

        $data = $response->toArray();
        return $data[0] ?? null;
    }
}