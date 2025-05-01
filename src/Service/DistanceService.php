<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class DistanceService
{
    private const API_KEY = '5b3ce3597851110001cf624817b0d320e6774ce78206822e9e7d34b4';
    private const GEOCODE_URL = 'https://api.openrouteservice.org/geocode/search';
    private const MATRIX_URL = 'https://api.openrouteservice.org/v2/matrix/driving-car';

    public function __construct(private HttpClientInterface $httpClient) {}

    public function geocode(string $address): ?array
    {
        $response = $this->httpClient->request('GET', self::GEOCODE_URL, [
            'query' => [
                'api_key' => self::API_KEY,
                'text' => $address,
            ],
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);

        $data = $response->toArray(false);
        if (!empty($data['features'][0]['geometry']['coordinates'])) {
            return [
                'lon' => $data['features'][0]['geometry']['coordinates'][0],
                'lat' => $data['features'][0]['geometry']['coordinates'][1],
            ];
        }

        return null;
    }

    public function calculateDistanceKm(string $addressStart, string $addressEnd): float
    {
        $origin = $this->geocode($addressStart);
        $destination = $this->geocode($addressEnd);

        if (!$origin || !$destination) {
            return -1;
        }

        $response = $this->httpClient->request('POST', self::MATRIX_URL, [
            'headers' => [
                'Authorization' => self::API_KEY,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'locations' => [
                    [$origin['lon'], $origin['lat']],
                    [$destination['lon'], $destination['lat']],
                ],
                'metrics' => ['distance'],
                'units' => 'km',
            ],
        ]);

        $data = $response->toArray(false);
        return $data['distances'][0][1] ?? -1;
    }
}
