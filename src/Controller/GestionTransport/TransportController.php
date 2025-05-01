<?php

namespace App\Controller\GestionTransport;

use App\Entity\GestionTransport\Transport;
use App\Entity\ReservationTransport;
use App\Form\GestionTransport\TransportType;
use App\Repository\GestionTransport\TransportRepository;
use App\Enums\GestionTransport\TransportStatus;
use App\Service\DistanceService;
use App\Service\Geocoder;
use App\Service\RouteSimulator;
use App\Service\StripeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use App\Service\InfobipService;
use Knp\Snappy\Pdf;
use Knp\Bundle\SnappyBundle\Snappy\Response\PdfResponse;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[IsGranted('ROLE_TRANSPORTEUR')]
#[Route('/transport')]
class TransportController extends AbstractController
{
    private const OPENSTREETMAP_API_URL = 'https://router.project-osrm.org/route/v1/driving/';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly DistanceService $distanceService,
        private readonly Geocoder $geocoder,
        private readonly RouteSimulator $routeSimulator,
        private readonly HttpClientInterface $httpClient,
        private readonly InfobipService $infobipService,
        private readonly StripeService $stripeService,
        private readonly Pdf $knpSnappyPdf,
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urlGenerator
    ) {}

    #[Route(name: 'app_transport_index', methods: ['GET'])]
    public function index(TransportRepository $transportRepository, Request $request): Response
    {
        $status = $request->query->get('status');
        $user = $this->getUser();
        
        $transports = $status 
            ? $transportRepository->findByStatusAndUser($status, $user)
            : $transportRepository->findByUser($user);

        return $this->render('GestionTransport/transport/index.html.twig', [
            'transports' => $transports,
            'current_filter' => $status
        ]);
    }

    #[Route('/new', name: 'app_transport_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
  
        $transport = new Transport();
        $form = $this->createForm(TransportType::class, $transport, ['form_type' => 'new']);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $reservation = $transport->getReservation();
                $depart = $reservation->getAdresseDepart();
                $arrivee = $reservation->getAdresseDestination();

                $distanceKm = $this->distanceService->calculateDistanceKm($depart, $arrivee);
                $transport->setTrajetEnKm($distanceKm);

                $tarif = $distanceKm * 0.5;
                $transport->setTarif($tarif);

                $this->entityManager->persist($reservation);
                $this->entityManager->persist($transport);
                $this->entityManager->flush();
                
                $this->addFlash('succès', 'Transport créé avec succès');
                return $this->redirectToRoute('app_transport_index');
            } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException $e) {
                $this->addFlash('erreur', 'Cette réservation est déjà prise par un autre transport.');
                return $this->render('GestionTransport/transport/new.html.twig', [
                    'transport' => $transport,
                    'form' => $form->createView(),
                ]);
            } catch (\Exception $e) {
                $this->addFlash('erreur', 'Une erreur est survenue lors de la création du transport: ' . $e->getMessage());
                return $this->render('GestionTransport/transport/new.html.twig', [
                    'transport' => $transport,
                    'form' => $form->createView(),
                ]);
            }
        }

        return $this->render('GestionTransport/transport/new.html.twig', [
            'transport' => $transport,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_transport_show', methods: ['GET'])]
    public function show(Transport $transport): Response
    {
        $reservation = $transport->getReservation();
        $departAddress = $reservation->getAdresseDepart();
        $arriveeAddress = $reservation->getAdresseDestination();

        $departCoords = $this->geocoder->geocode($departAddress);
        $arriveeCoords = $this->geocoder->geocode($arriveeAddress);

        return $this->render('GestionTransport/transport/show.html.twig', [
            'transport' => $transport,
            'departLat' => $departCoords['lat'] ?? null,
            'departLon' => $departCoords['lon'] ?? null,
            'arriveeLat' => $arriveeCoords['lat'] ?? null,
            'arriveeLon' => $arriveeCoords['lon'] ?? null,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_transport_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Transport $transport): Response
    {
        $form = $this->createForm(TransportType::class, $transport, ['form_type' => 'edit']);
        $form->handleRequest($request);
    
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $reservation = $transport->getReservation();
                $depart = $reservation->getAdresseDepart();
                $arrivee = $reservation->getAdresseDestination();
    
                $distanceKm = $this->distanceService->calculateDistanceKm($depart, $arrivee);
                $transport->setTrajetEnKm($distanceKm);
    
                $tarif = $distanceKm * 0.5;
                $transport->setTarif($tarif);
    
                $this->entityManager->flush();
    
                $this->addFlash('succès', 'Transport modifié avec succès');
                return $this->redirectToRoute('app_transport_index');
            } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException $e) {
                $this->addFlash('erreur', 'Cette réservation est déjà prise par un autre transport.');
                return $this->render('GestionTransport/transport/edit.html.twig', [
                    'transport' => $transport,
                    'form' => $form->createView(),
                ]);
            } catch (\Exception $e) {
                $this->addFlash('erreur', 'Une erreur est survenue lors de la modification du transport: ' . $e->getMessage());
                return $this->render('GestionTransport/transport/edit.html.twig', [
                    'transport' => $transport,
                    'form' => $form->createView(),
                ]);
            }
        }
    
        return $this->render('GestionTransport/transport/edit.html.twig', [
            'transport' => $transport,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_transport_delete', methods: ['POST'])]
    public function delete(Request $request, Transport $transport): Response
    {
        if ($this->isCsrfTokenValid('delete'.$transport->getId(), $request->getPayload()->get('_token'))) {
            $this->entityManager->remove($transport);
            $this->entityManager->flush();
            $this->addFlash('succès', 'Transport supprimé avec succès');
            return $this->redirectToRoute('app_transport_index');
        }

        $this->addFlash('erreur', 'Invalid CSRF token');
        return $this->redirectToRoute('app_transport_index');
    }

    #[Route('/{id}/track/simulate', name: 'app_transport_track_simulate', methods: ['POST'])]
    public function simulateTracking(Request $request, Transport $transport, HubInterface $hub): JsonResponse
    {
        try {
            $body = json_decode($request->getContent(), true);
            $useWaypoints = [];

            if (!empty($body['coords']) && is_array($body['coords'])) {
                $useWaypoints = array_map(
                    fn(array $pt) => ['lat' => $pt[1], 'lon' => $pt[0]],
                    $body['coords']
                );
            } else {
                $reservation = $transport->getReservation();
                $depart = $reservation->getAdresseDepart();
                $arrivee = $reservation->getAdresseDestination();
                
                $departCoords = $this->geocoder->geocode($depart);
                $arriveeCoords = $this->geocoder->geocode($arrivee);
                
                $url = self::OPENSTREETMAP_API_URL
                    . "{$departCoords['lon']},{$departCoords['lat']};"
                    . "{$arriveeCoords['lon']},{$arriveeCoords['lat']}?overview=full&geometries=geojson";
                
                $response = $this->httpClient->request('GET', $url);
                $routeData = $response->toArray();
                
                $useWaypoints = array_map(
                    fn($c) => ['lat' => $c[1], 'lon' => $c[0]],
                    $routeData['routes'][0]['geometry']['coordinates']
                );
            }

            // Start simulation in a separate process to avoid blocking
            $this->routeSimulator->simulate(
                $hub,
                $transport->getId(),
                $transport->getReservation()->getAdresseDepart(),
                $transport->getReservation()->getAdresseDestination(),
                $transport->getTrajetEnKm(),
                $useWaypoints,
                count($useWaypoints),
                1,
                fn() => $this->completeTransport($transport)
            );

            return new JsonResponse([
                'status' => 'success',
                'message' => 'Simulation started'
            ]);
        } catch (\RuntimeException $e) {
            return new JsonResponse([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }

    #[Route('/{id}/track/position', name: 'app_transport_track_position', methods: ['GET'])]
    public function getCurrentPosition(Transport $transport): JsonResponse
    {
        try {
            $position = $this->routeSimulator->getCurrentPosition(
                $transport->getId(),
                $transport->getReservation()->getAdresseDepart(),
                $transport->getReservation()->getAdresseDestination()
            );

            return new JsonResponse([
                'status' => 'success',
                'data' => $position
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }

    #[Route('/{id}/alternative-directions', name: 'app_transport_alternative_directions', methods: ['POST'])]
    public function getAlternativeDirections(Request $request, Transport $transport): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            if (!$data || !isset($data['coords']) || count($data['coords']) !== 2) {
                return new JsonResponse([
                    'status' => 'error',
                    'message' => 'Invalid coordinates format'
                ], Response::HTTP_BAD_REQUEST);
            }

            [[$lon1, $lat1], [$lon2, $lat2]] = $data['coords'];
            $url = self::OPENSTREETMAP_API_URL . "{$lon1},{$lat1};{$lon2},{$lat2}?alternatives=true&steps=true&geometries=polyline";

            $response = $this->httpClient->request('GET', $url);
            $content = $response->toArray();

            if (isset($content['routes']) && count($content['routes']) > 0) {
                $alternativeRoutes = [];
                foreach ($content['routes'] as $route) {
                    $steps = [];
                    foreach ($route['legs'][0]['steps'] as $step) {
                        $steps[] = $step['maneuver']['instruction'];
                    }
                    $alternativeRoutes[] = [
                        'distance' => $route['distance'] / 1000,
                        'duration' => $route['duration'] / 60,
                        'instructions' => $steps,
                        'geometry' => $route['geometry'],
                    ];
                }

                return new JsonResponse([
                    'status' => 'success',
                    'alternatives' => $alternativeRoutes
                ]);
            }

            return new JsonResponse([
                'status' => 'error',
                'message' => 'No routes found'
            ], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            return new JsonResponse([
                'status' => 'error',
                'message' => 'OSRM error: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function completeTransport(Transport $transport): Response
    {
        if ($transport->getStatus() !== TransportStatus::ACTIF) {
            return $this->render('GestionTransport/transport/show.html.twig', [
                'transport' => $transport,
                'error' => 'Transport must be in ACTIF status.'
            ]);
        }

        $transport->setStatus(TransportStatus::COMPLETE);
        $this->entityManager->persist($transport);
        $this->entityManager->flush();

        $phoneNumber = $transport->getReservation()->getEtudiant()->getNumTel() ?? '+21600000000';
        $this->infobipService->sendSMS(
            $phoneNumber,
            "Transport #{$transport->getId()} has arrived at {$transport->getReservation()->getAdresseDestination()}."
        );

        return $this->render('GestionTransport/transport/show.html.twig', [
            'transport' => $transport,
            'success' => true
        ]);
    }
     
    #[Route('/{id}/pickup', name: 'app_transport_pickup', methods: ['GET', 'POST'])]
    public function pickup(Request $request, Transport $transport): Response
    {
        if ($transport->getStatus() !== TransportStatus::PENDING) {
            $this->addFlash('erreur', 'Le transport ne peut pas être pris en charge car il n\'est pas en attente.');
            return $this->redirectToRoute('app_transport_index');
        }

        $form = $this->createFormBuilder()
            ->add('confirm', SubmitType::class, [
                'label' => 'Confirmer la prise en charge',
                'attr' => ['class' => 'btn btn-success'],
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $transport->setStatus(TransportStatus::ACTIF);
                $this->entityManager->persist($transport);
                $this->entityManager->flush();

                $etudiant = $transport->getReservation()->getEtudiant();
                $phoneNumber = $etudiant->getNumTel() ?? '+21600000000';
                $this->infobipService->sendSMS(
                    $phoneNumber,
                    "Transport #{$transport->getId()} has been picked up."
                );

                $this->addFlash('succès', 'Prise en charge confirmée avec succès.');
                return $this->redirectToRoute('app_transport_index');
            } catch (\Exception $e) {
                $this->addFlash('erreur', 'Erreur lors de la confirmation de la prise en charge: ' . $e->getMessage());
                return $this->redirectToRoute('app_transport_index');
            }
        }

        return $this->render('GestionTransport/transport/pickup.html.twig', [
            'transport' => $transport,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/bill', name: 'app_transport_bill', methods: ['GET', 'POST'])]
    public function bill(Request $request, Transport $transport, StripeService $stripeService): Response
    {
        if ($transport->getStatus() !== TransportStatus::COMPLETE) {
            $this->addFlash('error', 'Le transport doit être complété avant de générer la facture');
            return $this->redirectToRoute('app_transport_index');
        }
    
        $form = $this->createFormBuilder()
            ->add('confirm', SubmitType::class, [
                'label' => 'Générer la facture',
                'attr' => ['class' => 'btn btn-primary']
            ])
            ->getForm();
    
        $form->handleRequest($request);
    
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $etudiant = $transport->getReservation()->getEtudiant();
                $totalAmount = $transport->getTarif() + ($transport->getExtraCost() ?? 0);
    
                // Create invoice using CIN as reference
                $invoice = $stripeService->createInvoice([
                    'student_cin' => $etudiant->getCin(),
                    'transport_id' => $transport->getId(),
                    'description' => 'Transport #'.$transport->getId(),
                    'amount' => $totalAmount,
                    'currency' => 'TND'
                ]);
    
                // Save invoice reference
                $transport->setStripeInvoiceId($invoice['id']);
                $this->entityManager->persist($transport);
                $this->entityManager->flush();
    
                // Send billing email
                $email = (new TemplatedEmail())
                    ->from('studar21@gmail.com')
                    ->to($transport->getReservation()->getEtudiant()->getEmail())
                    ->subject('Facture Transport #' . $transport->getId())
                    ->html($this->renderView('bill.html.twig', [
                        'transport' => $transport,
                        'invoice' => [
                            'reference' => $invoice['id'],
                            'amount' => $totalAmount,
                            'pdf_url' => $invoice['pdf_url'],
                            'date' => new \DateTime()
                        ]
                    ]));
    
                $this->mailer->send($email);
    
                $this->addFlash('success', 'Facture générée et envoyée avec succès');
                return $this->redirectToRoute('app_transport_show', ['id' => $transport->getId()]);
    
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la génération de la facture: '.$e->getMessage());
            }
        }
    
        $totalAmount = $transport->getTarif() + ($transport->getExtraCost() ?? 0);
        return $this->render('GestionTransport/transport/bill.html.twig', [
            'transport' => $transport,
            'form' => $form->createView(),
            'invoiceData' => [
                'amount' => $totalAmount,
                'reference' => $transport->getStripeInvoiceId() ?? 'INV-' . $transport->getId(),
                'date' => new \DateTime(),
            ],
            'company_info' => [
                'name' => 'StuDar',
                'address' => '123 Rue Exemple',
                'zip' => '1000',
                'city' => 'Tunis',
                'phone' => '+216 12 345 678',
                'email' => 'contact@studar.com',
                'siret' => '123 456 789 00012',
            ],
            'logo_enabled' => true,
        ]);
    
    }
    #[Route('/{id}/invoice', name: 'app_transport_invoice', methods: ['GET'])]
public function downloadInvoice(int $id, Pdf $pdf,Request $request): Response
{
    $transport = $this->entityManager->getRepository(Transport::class)->find($id);
    if (!$transport || $transport->getStatus() !== TransportStatus::COMPLETE) {
        throw $this->createNotFoundException('Transport non trouvé ou non complété.');
    }

    $totalAmount = $transport->getTarif() + ($transport->getExtraCost() ?? 0);
    $html = $this->renderView('GestionTransport/transport/invoice_pdf.html.twig', [
        'transport' => $transport,
        'invoiceData' => [
            'amount' => $totalAmount,
            'reference' => $transport->getStripeInvoiceId() ?? 'INV-' . $transport->getId(), // Fallback if no Stripe ID
            'date' => new \DateTime(),
        ],
        'company_info' => [
            'name' => 'StuDar',
            'address' => '123 Rue Exemple',
            'zip' => '1000',
            'city' => 'Tunis',
            'phone' => '+216 12 345 678',
            'email' => 'contact@studar.com',
            'siret' => '123 456 789 00012',
        ],
        'logo_enabled' => true,
        'base_path' => $request->getSchemeAndHttpHost() 
    ]);

    return new Response(
        $pdf->getOutputFromHtml($html),
        Response::HTTP_OK,
        [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="facture_transport_' . $transport->getId() . '.pdf"',
        ]
    );
}
    #[Route('/api/reservation/{id}/arrival-time', name: 'api_reservation_arrival_time', methods: ['GET'])]
    public function getReservationArrivalTime(ReservationTransport $reservation): JsonResponse
    {
        try {
            $etudiant = $reservation->getEtudiant();
            $tempsArrivage = $reservation->getTempsArrivage(); // VARCHAR, e.g., '2025-05-01 14:30:00'

            return new JsonResponse([
                'arrivalTime' => $tempsArrivage ? date('c', strtotime($tempsArrivage)) : null, // ISO 8601
                'formatted' => $tempsArrivage ? date('d/m/Y H:i', strtotime($tempsArrivage)) : null, // Formatted
                'etudiant' => $etudiant ? [
                    'nom' => $etudiant->getNom(),
                    'prenom' => $etudiant->getPrenom(),
                ] : null,
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'status' => 'error',
                'message' => 'Erreur lors de la récupération des données: ' . $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }
}