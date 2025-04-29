<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class GeocodingService
{
    private $geocoder;
    private $logger;

    public function __construct(Geocoder $geocoder, LoggerInterface $logger)
    {
        $this->geocoder = $geocoder;
        $this->logger = $logger;
    }

    public function geocodeAddress(string $address): ?array
    {
        $result = $this->geocoder->geocode($address);
        if ($result && isset($result['lat'], $result['lon'])) {
            return [
                'lat' => $result['lat'],
                'lon' => $result['lon'],
            ];
        }
        return null;
    }

    public function searchNearestAmenity(string $latitude, string $longitude, string $amenityType, string $displayName): array
    {
        try {
            $client = HttpClient::create();
            // Increase radius to 2000 meters (2 km) to improve chances of finding amenities
            $radius = 2000;
            $query = '[out:json];node(around:' . $radius . ',' . $latitude . ',' . $longitude . ')[amenity="' . $amenityType . '"];out body;';
            $this->logger->info('Overpass API Query', ['query' => $query]);

            $response = $client->request('POST', 'https://overpass-api.de/api/interpreter', [
                'body' => $query,
            ]);

            if ($response->getStatusCode() === 200) {
                $data = $response->toArray();
                $this->logger->info('Overpass API Response', ['response' => $data]);

                $minDistance = PHP_INT_MAX;
                $nearestAmenity = null;

                if (!empty($data['elements'])) {
                    foreach ($data['elements'] as $element) {
                        if (isset($element['tags']['name']) && isset($element['lat']) && isset($element['lon'])) {
                            $distance = $this->calculateDistance(
                                (float)$latitude,
                                (float)$longitude,
                                (float)$element['lat'],
                                (float)$element['lon']
                            );
                            if ($distance < $minDistance && $distance <= $radius / 1000) {
                                $minDistance = $distance;
                                $nearestAmenity = [
                                    'name' => $element['tags']['name'],
                                    'distance' => $distance,
                                    'displayName' => $displayName,
                                    'lat' => $element['lat'],
                                    'lon' => $element['lon'],
                                ];
                            }
                        }
                    }
                } else {
                    $this->logger->warning('No elements found for amenity', ['amenity' => $amenityType, 'lat' => $latitude, 'lon' => $longitude]);
                }

                return $nearestAmenity ?: ['displayName' => $displayName, 'name' => null, 'distance' => null, 'lat' => null, 'lon' => null];
            } else {
                $this->logger->error('Overpass API request failed', ['status' => $response->getStatusCode()]);
            }
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Overpass API Transport Exception', ['error' => $e->getMessage()]);
            return ['displayName' => $displayName, 'name' => null, 'distance' => null, 'lat' => null, 'lon' => null];
        }
        return ['displayName' => $displayName, 'name' => null, 'distance' => null, 'lat' => null, 'lon' => null];
    }

    private function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $R = 6371; // Rayon de la Terre en km
        $latDistance = deg2rad($lat2 - $lat1);
        $lonDistance = deg2rad($lon2 - $lon1);
        $a = sin($latDistance / 2) * sin($latDistance / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($lonDistance / 2) * sin($lonDistance / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $R * $c; // Distance en km
    }
}