<?php

namespace App\Controller;

use App\Service\GeocodingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class StudentGuideController extends AbstractController
{
    private $geocodingService;

    public function __construct(GeocodingService $geocodingService)
    {
        $this->geocodingService = $geocodingService;
    }

    public function index(Request $request): Response
    {
        $address = $request->query->get('address', '');
        $coordinates = null;
        $amenities = [];

        if ($address) {
            $coordinates = $this->geocodingService->geocodeAddress($address);
            if ($coordinates) {
                $amenities = [
                    'pharmacy' => $this->geocodingService->searchNearestAmenity($coordinates['lat'], $coordinates['lon'], 'pharmacy', 'Pharmacie'),
                    'police' => $this->geocodingService->searchNearestAmenity($coordinates['lat'], $coordinates['lon'], 'police', 'Poste de police'),
                    'post_office' => $this->geocodingService->searchNearestAmenity($coordinates['lat'], $coordinates['lon'], 'post_office', 'Bureau de poste'),
                    'doctors' => $this->geocodingService->searchNearestAmenity($coordinates['lat'], $coordinates['lon'], 'doctors', 'Médecin'),
                    'shop' => $this->geocodingService->searchNearestAmenity($coordinates['lat'], $coordinates['lon'], 'shop', 'Magasin général'),
                ];
            }
        }

        return $this->render('student_guide/index.html.twig', [
            'address' => $address,
            'coordinates' => $coordinates,
            'amenities' => $amenities,
        ]);
    }
}