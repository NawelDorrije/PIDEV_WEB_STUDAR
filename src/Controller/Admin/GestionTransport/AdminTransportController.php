<?php

namespace App\Controller\Admin\GestionTransport;

use App\Entity\GestionTransport\Voiture;
use App\Entity\GestionTransport\Transport;
use App\Repository\GestionTransport\VoitureRepository;
use App\Repository\GestionTransport\TransportRepository;
use App\Service\Geocoder;
use App\Service\RouteSimulator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use App\Enums\GestionTransport\TransportStatus;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpClient\Exception\TimeoutException;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mailer\MailerInterface;
use Knp\Component\Pager\PaginatorInterface;


#[IsGranted('ROLE_ADMIN')]
#[Route('/gestiontransport')]
class AdminTransportController extends AbstractController
{
    private const OPENSTREETMAP_API_URL = 'https://router.project-osrm.org/route/v1/driving/';
    private const CACHE_TTL = 3600; // 1 hour
    private string $mailerFrom;
    private string $mailerFromName; 

    private $cache;



    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RouteSimulator $routeSimulator,
        private readonly Geocoder $geocoder,
        private readonly PaginatorInterface $paginator,
        private readonly MailerInterface $mailer,
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger // Initialize logger here
    ) {
        $this->mailerFrom = $_ENV['MAILER_FROM'];
        $this->mailerFromName = $_ENV['MAILER_FROM_NAME'];
        // Remove the line that attempts to modify $this->logger

        // Initialize in-memory cache
        $this->cache = new ArrayAdapter();
    }

#[Route('/adminTransport', name: 'app_gestion_transport_dashboard', methods: ['GET'])]
public function adminTransport(
    VoitureRepository $voitureRepository,
    TransportRepository $transportRepository,
    Request $request
): Response {
    $this->denyAccessUnlessGranted('ROLE_ADMIN');
    
    $disponibilite = $request->query->get('disponibilite');
    $status = $request->query->get('status');
    $search = $request->query->get('search', '');
    $dateFrom = $request->query->get('date_from', '');
    $dateTo = $request->query->get('date_to', '');
    $page = $request->query->getInt('page', 1);
    $limit = $request->query->getInt('limit', 10);

    // Vehicles query using findByAvailability
    if ($disponibilite) {
        $voitures = $voitureRepository->findByAvailability($disponibilite);
    } else {
        $voitures = $voitureRepository->findAll();
    }

    $voitures = $this->paginator->paginate(
        $voitures,
        $page,
        $limit,
    );
    
    // Transports query using findByStatus
    try {
        if ($status && in_array($status, TransportStatus::getValues(), true)) {
            $transports = $transportRepository->findByStatus($status);
        } else {
            $transports = $transportRepository->findAll();
        }
        
        $transports = $this->paginator->paginate(
            $transports,
            $page,
            $limit,
        );
    } catch (\Exception $e) {
        $this->logger->error('Error loading transports: ' . $e->getMessage(), ['exception' => $e]);
        $this->addFlash('error', 'Error loading transports: ' . $e->getMessage());
        $transports = [];
    }

    // Invoices query (completed transports) with pagination
    $invoicesQb = $transportRepository->createQueryBuilder('t')
        ->innerJoin('t.reservation', 'r')
        ->innerJoin('r.etudiant', 'e')
        ->where('t.status = :status')
        ->setParameter('status', TransportStatus::COMPLETE);

    if ($search) {
        $invoicesQb->andWhere('e.nom LIKE :search')
                   ->setParameter('search', "%$search%");
    }
    if ($dateFrom) {
        $invoicesQb->andWhere('t.timestamp >= :dateFrom')
                   ->setParameter('dateFrom', new \DateTime($dateFrom));
    }
    if ($dateTo) {
        $invoicesQb->andWhere('t.timestamp <= :dateTo')
                   ->setParameter('dateTo', new \DateTime($dateTo));
    }

    // Add ordering by student name
    $invoicesQb->orderBy('e.nom', 'ASC');

    $invoiceTransports = $invoicesQb->getQuery()->getResult();
    $invoiceData = [];
    foreach ($invoiceTransports as $transport) {
        $invoiceData[] = [
            'transport' => $transport,
            'invoiceData' => [
                'reference' => 'INV-' . $transport->getId(),
                'date' => $transport->getTimestamp() ?? new \DateTime(),
                'amount' => $transport->getTarif() + ($transport->getExtraCost() ?? 0),
                'usd_amount' => ($transport->getTarif() + ($transport->getExtraCost() ?? 0)) * 0.32,
            ],
        ];
    }

    // Paginate the invoice data array
    $invoices = $this->paginator->paginate(
        $invoiceData,
        $request->query->getInt('i_page', 1),
        $limit,    );

    // Calculate completed transports and total revenue
    $allTransports = $transportRepository->findAll();
    $completedTransports = array_filter($allTransports, function($transport) {
        return $transport->getStatus() === TransportStatus::COMPLETE;
    });
    $totalRevenue = array_reduce($completedTransports, function($sum, $transport) {
        return $sum + ($transport->getTarif() ?? 0);
    }, 0);

    // Process transports for coordinates
    $transportsWithCoords = [];
    foreach ($transports as $transport) {
        if ($transport->getReservation()) {
            $departAddress = $transport->getReservation()->getAdresseDepart();
            $arriveeAddress = $transport->getReservation()->getAdresseDestination();

            $departCoords = $departAddress ? $this->geocoder->geocode($departAddress) : null;
            $arriveeCoords = $arriveeAddress ? $this->geocoder->geocode($arriveeAddress) : null;

            $transportsWithCoords[] = [
                'transport' => $transport,
                'departLat' => $departCoords['lat'] ?? null,
                'departLon' => $departCoords['lon'] ?? null,
                'arriveeLat' => $arriveeCoords['lat'] ?? null,
                'arriveeLon' => $arriveeCoords['lon'] ?? null,
            ];
        }
    }

    return $this->render('admin/GestionTransport/dashboard.html.twig', [
        'voitures' => $voitures,
        'transports' => $transports,
        'transportsWithCoords' => $transportsWithCoords,
        'statusOptions' => TransportStatus::getValues(),
        'totalRevenue' => $totalRevenue,
        'completedTransportsCount' => count($completedTransports),
        'invoices' => $invoices,
    ]);
}

    #[Route('/simulate-all', name: 'admin_transport_simulate_all', methods: ['POST'])]
    public function simulateAllActiveTransports(
        TransportRepository $transportRepository,
        HubInterface $hub
    ): JsonResponse {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        try {
            $this->logger->info('Starting simulation for all active transports');

            $activeTransports = $transportRepository->createQueryBuilder('t')
                ->where('t.status = :status')
                ->setParameter('status', TransportStatus::ACTIF)
                ->getQuery()
                ->getResult();

            $this->logger->info('Found ' . count($activeTransports) . ' active transports', [
                'transport_ids' => array_map(fn($t) => $t->getId(), $activeTransports)
            ]);

            if (empty($activeTransports)) {
                $this->logger->warning('No active transports found');
                return new JsonResponse([
                    'status' => 'error',
                    'message' => 'No active transports found'
                ], Response::HTTP_BAD_REQUEST);
            }

            // Fetch routes concurrently
            $promises = [];
            $transportData = [];
            foreach ($activeTransports as $transport) {
                $reservation = $transport->getReservation();
                $depart = $reservation->getAdresseDepart();
                $arrivee = $reservation->getAdresseDestination();
                
                $cacheKey = md5("{$depart}|{$arrivee}");
                $cachedRoute = $this->cache->getItem($cacheKey);
                
                if ($cachedRoute->isHit()) {
                    $this->logger->info("Cache hit for route between {$depart} and {$arrivee}");
                    $transportData[$transport->getId()] = [
                        'transport' => $transport,
                        'waypoints' => $cachedRoute->get(),
                    ];
                    continue;
                }

                $departCoords = $this->geocoder->geocode($depart);
                $arriveeCoords = $this->geocoder->geocode($arrivee);
                
                $url = self::OPENSTREETMAP_API_URL
                    . "{$departCoords['lon']},{$departCoords['lat']};"
                    . "{$arriveeCoords['lon']},{$arriveeCoords['lat']}?overview=full&geometries=geojson";
                
                $promises[$transport->getId()] = [
                    'promise' => $this->httpClient->request('GET', $url),
                    'transport' => $transport,
                    'cacheKey' => $cacheKey,
                ];
            }

            // Process OSRM responses asynchronously
            foreach ($promises as $transportId => $promiseData) {
                try {
                    $response = $promiseData['promise']->toArray();
                    $waypoints = array_map(
                        fn($c) => ['lat' => $c[1], 'lon' => $c[0]],
                        $response['routes'][0]['geometry']['coordinates']
                    );
                    
                    // Cache the waypoints
                    $cacheItem = $this->cache->getItem($promiseData['cacheKey']);
                    $cacheItem->set($waypoints);
                    $cacheItem->expiresAfter(self::CACHE_TTL);
                    $this->cache->save($cacheItem);
                    
                    $this->logger->info("Fetched and cached " . count($waypoints) . " waypoints for transport #{$transportId}");
                    $transportData[$transportId] = [
                        'transport' => $promiseData['transport'],
                        'waypoints' => $waypoints,
                    ];
                } catch (ClientException|TimeoutException $e) {
                    $this->logger->error("Failed to fetch route for transport #{$transportId}: " . $e->getMessage());
                    // Fallback to a straight line or skip
                    $transportData[$transportId] = [
                        'transport' => $promiseData['transport'],
                        'waypoints' => [],
                    ];
                }
            }

            // Start simulations
            foreach ($transportData as $data) {
                $this->simulateTracking($data['transport'], $hub, $data['waypoints']);
            }

            $this->logger->info('Simulation started for all active transports');
            return new JsonResponse([
                'status' => 'success',
                'message' => 'Simulation started for all active transports',
                'count' => count($activeTransports)
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error starting simulations: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);
            return new JsonResponse([
                'status' => 'error',
                'message' => 'Error starting simulations: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/transport/{id}/simulate', name: 'admin_transport_simulate', methods: ['POST'])]
    public function simulateTransport(
        Transport $transport,
        HubInterface $hub,
        Request $request
    ): JsonResponse {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        try {
            if ($transport->getStatus() !== TransportStatus::ACTIF) {
                $this->logger->warning('Transport #' . $transport->getId() . ' is not in ACTIF status', [
                    'current_status' => $transport->getStatus()->value
                ]);
                return new JsonResponse([
                    'status' => 'error',
                    'message' => 'Transport must be in ACTIF status'
                ], Response::HTTP_BAD_REQUEST);
            }

            $body = json_decode($request->getContent(), true);
            $useWaypoints = [];
            if (!empty($body['coords']) && is_array($body['coords'])) {
                $useWaypoints = array_map(
                    fn(array $pt) => ['lat' => $pt[1], 'lon' => $pt[0]],
                    $body['coords']
                );
            }

            $this->simulateTracking($transport, $hub, $useWaypoints);

            return new JsonResponse([
                'status' => 'success',
                'message' => 'Simulation started for transport #' . $transport->getId()
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error simulating transport #' . $transport->getId() . ': ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);
            return new JsonResponse([
                'status' => 'error',
                'message' => 'Error simulating transport: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function simulateTracking(Transport $transport, HubInterface $hub, array $useWaypoints = []): void
    {
        $this->logger->info('Starting simulation for transport #' . $transport->getId());

        if (empty($useWaypoints)) {
            $reservation = $transport->getReservation();
            $depart = $reservation->getAdresseDepart();
            $arrivee = $reservation->getAdresseDestination();
            
            $cacheKey = md5("{$depart}|{$arrivee}");
            $cachedRoute = $this->cache->getItem($cacheKey);
            
            if ($cachedRoute->isHit()) {
                $useWaypoints = $cachedRoute->get();
                $this->logger->info("Cache hit for route between {$depart} and {$arrivee}, " . count($useWaypoints) . " waypoints");
            } else {
                $departCoords = $this->geocoder->geocode($depart);
                $arriveeCoords = $this->geocoder->geocode($arrivee);
                
                $url = self::OPENSTREETMAP_API_URL
                    . "{$departCoords['lon']},{$departCoords['lat']};"
                    . "{$arriveeCoords['lon']},{$arriveeCoords['lat']}?overview=full&geometries=geojson";
                
                try {
                    $response = $this->httpClient->request('GET', $url);
                    $routeData = $response->toArray();
                    
                    $useWaypoints = array_map(
                        fn($c) => ['lat' => $c[1], 'lon' => $c[0]],
                        $routeData['routes'][0]['geometry']['coordinates']
                    );
                    
                    $cacheItem = $this->cache->getItem($cacheKey);
                    $cacheItem->set($useWaypoints);
                    $cacheItem->expiresAfter(self::CACHE_TTL);
                    $this->cache->save($cacheItem);
                    
                    $this->logger->info('Fetched and cached ' . count($useWaypoints) . ' waypoints for transport #' . $transport->getId());
                } catch (ClientException|TimeoutException $e) {
                    $this->logger->error('Failed to fetch route for transport #' . $transport->getId() . ': ' . $e->getMessage());
                    $useWaypoints = [];
                }
            }
        }

        $this->routeSimulator->simulate(
            $hub,
            $transport->getId(),
            $transport->getReservation()->getAdresseDepart(),
            $transport->getReservation()->getAdresseDestination(),
            $transport->getTrajetEnKm(),
            $useWaypoints,
            count($useWaypoints),
            1,
            function() use ($transport) {
                $this->completeTransport($transport);
            }
        );
    }

    private function completeTransport(Transport $transport): void
    {
        if ($transport->getStatus() !== TransportStatus::ACTIF) {
            $this->logger->warning('Transport #' . $transport->getId() . ' is not in ACTIF status, cannot complete', [
                'current_status' => $transport->getStatus()->value
            ]);
            return;
        }

        $this->logger->info('Setting transport #' . $transport->getId() . ' status to COMPLETE');
        $transport->setStatus(TransportStatus::COMPLETE);
        $this->entityManager->persist($transport);
        $this->entityManager->flush();
        $this->logger->info('Transport #' . $transport->getId() . ' status updated to COMPLETE');
    }

    #[Route('/transport/{id}/position', name: 'admin_transport_position', methods: ['GET'])]
    public function getTransportPosition(Transport $transport): JsonResponse
    {
        try {
            $this->logger->info('Fetching position for transport #' . $transport->getId());
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
            $this->logger->error('Error fetching position for transport #' . $transport->getId() . ': ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);
            return new JsonResponse([
                'status' => 'error',
                'message' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/transport/{id}', name: 'admin_transport_show', methods: ['GET'])]
    public function showTransport(Transport $transport): Response
    {
        $reservation = $transport->getReservation();
        $departAddress = $reservation->getAdresseDepart();
        $arriveeAddress = $reservation->getAdresseDestination();

        $departCoords = $this->geocoder->geocode($departAddress);
        $arriveeCoords = $this->geocoder->geocode($arriveeAddress);

        return $this->render('admin/GestionTransport/transport/show.html.twig', [
            'transport' => $transport,
            'departLat' => $departCoords['lat'] ?? null,
            'departLon' => $departCoords['lon'] ?? null,
            'arriveeLat' => $arriveeCoords['lat'] ?? null,
            'arriveeLon' => $arriveeCoords['lon'] ?? null,
        ]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/voiture/{idVoiture}', name: 'admin_voiture_show', methods: ['GET'])]
    public function showVoiture(Voiture $voiture): Response
    {
        return $this->render('admin/GestionTransport/voiture/show.html.twig', [
            'voiture' => $voiture,
        ]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/stats', name: 'admin_transport_stats', methods: ['GET'])]
    public function getStats(VoitureRepository $voitureRepo, TransportRepository $transportRepo): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        $years = $transportRepo->createQueryBuilder('t')
            ->select("DISTINCT DATE_FORMAT(t.timestamp, '%Y') as year")
            ->orderBy('year', 'DESC')
            ->getQuery()
            ->getSingleColumnResult();
        
        $yearToUse = !empty($years) ? max($years) : (new \DateTime())->format('Y');
        
        $revenueData = $transportRepo->getRevenueByMonth($yearToUse);
        
        $response = [
            'vehicles' => $this->normalizeMonthlyData($voitureRepo->countByMonth($yearToUse)),
            'transports' => $this->normalizeTransportData($transportRepo->countByMonthAndStatus($yearToUse)),
            'revenue' => $this->normalizeRevenueData($revenueData),
            'year_used' => $yearToUse
        ];
        
        return $this->json($response);
    }

    private function normalizeRevenueData(array $data): array
    {
        if (isset($data['labels'], $data['values'])) {
            $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            $values = array_fill(0, 12, 0);
    
            foreach ($data['labels'] as $index => $month) {
                $monthIndex = array_search($month, $months);
                if ($monthIndex !== false) {
                    $values[$monthIndex] = (float)($data['values'][$index] ?? 0);
                }
            }
    
            return [
                'labels' => $months,
                'values' => $values
            ];
        }
    
        return [
            'labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
            'values' => array_fill(0, 12, 0)
        ];
    }

    private function normalizeMonthlyData(array $data): array
    {
        $defaultMonths = array_fill_keys(['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'], 0);
        
        return [
            'labels' => array_keys($defaultMonths),
            'values' => array_values(array_merge($defaultMonths, $data))
        ];
    }

    private function normalizeTransportData(array $data): array
    {
        $defaults = [
            'labels' => ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'],
            'completed' => array_fill(0, 12, 0),
            'active' => array_fill(0, 12, 0)
        ];
        
        if (empty($data)) return $defaults;
        
        return [
            'labels' => $defaults['labels'],
            'completed' => array_values(array_replace(
                $defaults['completed'],
                array_column($data, 'complete')
            )),
            'active' => array_values(array_replace(
                $defaults['active'],
                array_column($data, 'actif')
            ))
        ];
    }
    #[IsGranted('ROLE_ADMIN')]
#[Route('/invoices', name: 'admin_transport_invoices', methods: ['GET'])]
public function invoices(TransportRepository $transportRepository, VoitureRepository $voitureRepository, Request $request): Response
{
    $this->denyAccessUnlessGranted('ROLE_ADMIN');

    $status = $request->query->get('status', TransportStatus::COMPLETE->value);
    $search = $request->query->get('search', '');
    $dateFrom = $request->query->get('date_from', '');
    $dateTo = $request->query->get('date_to', '');

    // Build query for completed transports
    $qb = $transportRepository->createQueryBuilder('t')
        ->innerJoin('t.reservation', 'r')
        ->innerJoin('r.etudiant', 'e')
        ->where('t.status = :status')
        ->setParameter('status', TransportStatus::COMPLETE);

    // Apply filters
    if ($search) {
        $qb->andWhere('e.nom LIKE :search OR e.prenom LIKE :search OR e.email LIKE :search OR t.id LIKE :search')
           ->setParameter('search', "%$search%");
    }
    if ($dateFrom) {
        $qb->andWhere('t.timestamp >= :dateFrom')
           ->setParameter('dateFrom', new \DateTime($dateFrom));
    }
    if ($dateTo) {
        $qb->andWhere('t.timestamp <= :dateTo')
           ->setParameter('dateTo', new \DateTime($dateTo));
    }

    // Get results and create invoice data
    $transports = $qb->getQuery()->getResult();
    $invoiceData = array_map(function ($transport) {
        return [
            'transport' => $transport,
            'invoiceData' => [
                'reference' => 'INV-' . $transport->getId(),
                'date' => $transport->getTimestamp() ?? new \DateTime(),
                'amount' => $transport->getTarif() + ($transport->getExtraCost() ?? 0),
                'usd_amount' => ($transport->getTarif() + ($transport->getExtraCost() ?? 0)) * 0.32,
            ],
        ];
    }, $transports);

    // Paginate invoices
    $invoices = $this->paginator->paginate(
        $invoiceData,
        $request->query->getInt('page', 1),
        10
    );

    // Add vehicles query
    $voitures = $this->paginator->paginate(
        $voitureRepository->findAll(),
        $request->query->getInt('v_page', 1),
        10,
        
    );

    // Get transports for the main list
    $transportsQb = $transportRepository->createQueryBuilder('t')
        ->orderBy('t.timestamp', 'DESC');
    
    $transports = $this->paginator->paginate(
        $transportsQb->getQuery(),
        $request->query->getInt('t_page', 1),
        10,
    );

    // Calculate statistics
    $allTransports = $transportRepository->findAll();
    $completedTransports = array_filter($allTransports, function($transport) {
        return $transport->getStatus() === TransportStatus::COMPLETE;
    });
    $totalRevenue = array_reduce($completedTransports, function($sum, $transport) {
        return $sum + ($transport->getTarif() ?? 0);
    }, 0);

    return $this->render('admin/GestionTransport/dashboard.html.twig', [
        'invoices' => $invoices,
        'transports' => $transports,
        'voitures' => $voitures,
        'totalRevenue' => $totalRevenue,
        'completedTransportsCount' => count($completedTransports),
        'filters' => [
            'status' => $status,
            'search' => $search,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ],
    ]);
}
#[IsGranted('ROLE_ADMIN')]
#[Route('/transport/{id}/invoice', name: 'admin_transport_invoice', methods: ['GET'])]
public function invoice(Transport $transport): Response
{
    if ($transport->getStatus() !== TransportStatus::COMPLETE) {
        $this->addFlash('error', 'Invoice available only for completed transports');
        return $this->redirectToRoute('admin_transport_invoices');
    }

    $options = new Options();
    $options->set('isRemoteEnabled', true);
    $options->set('isHtml5ParserEnabled', true);
    $options->set('chroot', $this->getParameter('kernel.project_dir'));
    $dompdf = new Dompdf($options);

    $html = $this->renderView('GestionTransport/transport/invoice_pdf.html.twig', [
        'transport' => $transport,
        'invoiceData' => [
            'date' => new \DateTime(),
            'reference' => 'INV-' . $transport->getId(),
            'amount' => $transport->getTarif() + ($transport->getExtraCost() ?? 0)
        ],
        'company_info' => [
            'name' => 'STUDAR',
            'address' => '123 Rue Exemple',
            'zip' => '1000',
            'city' => 'Tunis',
            'phone' => '+216 12 345 678',
            'email' => 'studar21@gmail.com',
            'siret' => '12345678901234'
        ],
        'logo_enabled' => true,
        'base_path' => $this->getParameter('kernel.project_dir') . '/public/'
    ]);

    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    return new Response(
        $dompdf->output(),
        200,
        [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="facture-transport-' . $transport->getId() . '.pdf"'
        ]
    );
}
#[IsGranted('ROLE_ADMIN')]
#[Route('/transport/{id}/bill', name: 'admin_transport_bill', methods: ['POST'])]
public function bill(Request $request, Transport $transport, MailerInterface $mailer): Response
{
    if ($transport->getStatus() !== TransportStatus::COMPLETE) {
        $this->addFlash('error', 'Invoice available only for completed transports');
        return $this->redirectToRoute('admin_transport_invoices');
    }

    try {
        $tndToUsdRate = 0.32;
        $studentEmail = $transport->getReservation()->getEtudiant()->getEmail();

        if (empty($studentEmail)) {
            throw new \Exception("Student's email address is empty");
        }

        $this->logger->info('Starting bill resend process', [
            'transport_id' => $transport->getId(),
            'student_email' => $studentEmail,
        ]);

        // Generate PDF
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('chroot', $this->getParameter('kernel.project_dir'));
        $dompdf = new Dompdf($options);

        $html = $this->renderView('GestionTransport/transport/invoice_pdf.html.twig', [
            'transport' => $transport,
            'invoiceData' => [
                'date' => new \DateTime(),
                'reference' => 'INV-' . $transport->getId(),
                'amount' => $transport->getTarif() + ($transport->getExtraCost() ?? 0),
                'usd_amount' => ($transport->getTarif() + ($transport->getExtraCost() ?? 0)) * $tndToUsdRate
            ],
            'company_info' => [
                'name' => 'STUDAR',
                'address' => '123 Rue Exemple',
                'zip' => '1000',
                'city' => 'Tunis',
                'phone' => '+216 12 345 678',
                'email' => 'contact@studar.tn',
                'siret' => '12345678901234'
            ],
            'logo_enabled' => true,
            'base_path' => $this->getParameter('kernel.project_dir') . '/public/'
        ]);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $pdfContent = $dompdf->output();

        // Send Email
        $emailHtml = $this->renderView('GestionTransport/transport/bill.html.twig', [
            'transport' => $transport,
            'invoiceData' => [
                'date' => new \DateTime(),
                'reference' => 'INV-' . $transport->getId(),
                'usd_amount' => ($transport->getTarif() + ($transport->getExtraCost() ?? 0)) * $tndToUsdRate
            ]
        ]);

        $emailText = 'Please find attached your invoice. Amount in TND: ' .
            ($transport->getTarif() + ($transport->getExtraCost() ?? 0)) .
            ' TND, equivalent in USD: ' .
            number_format(($transport->getTarif() + ($transport->getExtraCost() ?? 0)) * $tndToUsdRate, 2) . ' USD.';

        $email = (new Email())
            ->from(new Address($this->mailerFrom, $this->mailerFromName))
            ->to($studentEmail)
            ->subject('Your invoice for transport #' . $transport->getId())
            ->text($emailText)
            ->html($emailHtml)
            ->attach($pdfContent, 'facture-transport-' . $transport->getId() . '.pdf', 'application/pdf');

        $mailer->send($email);
        $this->logger->info('Invoice email resent successfully', ['transport_id' => $transport->getId()]);
        $this->addFlash('success', 'Invoice resent to ' . $studentEmail);

    } catch (\Exception $e) {
        $this->logger->error('Error resending invoice', [
            'transport_id' => $transport->getId(),
            'error' => $e->getMessage(),
        ]);
        $this->addFlash('error', 'Error resending invoice: ' . $e->getMessage());
    }

    return $this->redirectToRoute('app_gestion_transport_dashboard');
}
}