<?php

namespace App\Controller\GestionTransport;

use Psr\Log\LoggerInterface;
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
use Exception;
use Knp\Snappy\Pdf;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Stripe\Stripe;
use Stripe\Customer;
use Stripe\Invoice;
use Stripe\InvoiceItem;

#[IsGranted('ROLE_TRANSPORTEUR')]
#[Route('/transport')]
class TransportController extends AbstractController
{
    private const OPENSTREETMAP_API_URL = 'https://router.project-osrm.org/route/v1/driving/';
    private $logger;
    private string $mailerFrom;
    private string $mailerFromName;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly DistanceService $distanceService,
        LoggerInterface $logger,
        private readonly Geocoder $geocoder,
        private readonly RouteSimulator $routeSimulator,
        private readonly HttpClientInterface $httpClient,
        private readonly InfobipService $infobipService,
        private readonly StripeService $stripeService,
        private readonly Pdf $knpSnappyPdf,
        private readonly MailerInterface $mailer,
        string $mailerFrom,
        string $mailerFromName,
        private readonly UrlGeneratorInterface $urlGenerator
    ) {
        $this->mailerFrom = $mailerFrom;
        $this->mailerFromName = $mailerFromName;
        $this->logger = $logger;
    }

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
                
                // Calculate extra costs for exceeding loading/unloading times
                $extraCost = 0;
                $costPerMinute = 0.5; // 0.5 TND per minute of delay
                
                if ($transport->getLoadingTimeActual() > $transport->getLoadingTimeAllowed()) {
                    $extraMinutes = $transport->getLoadingTimeActual() - $transport->getLoadingTimeAllowed();
                    $extraCost += $extraMinutes * $costPerMinute;
                }
                
                if ($transport->getUnloadingTimeActual() > $transport->getUnloadingTimeAllowed()) {
                    $extraMinutes = $transport->getUnloadingTimeActual() - $transport->getUnloadingTimeAllowed();
                    $extraCost += $extraMinutes * $costPerMinute;
                }
                
                $transport->setExtraCost($extraCost);
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
    public function delete(Request $request, Transport $transport, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$transport->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($transport);
            $entityManager->flush();
        }
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

    #[Route('/{id}/invoice', name: 'app_transport_invoice', methods: ['GET'])]
    public function invoice(Transport $transport): Response
    {
        if ($transport->getStatus() !== TransportStatus::COMPLETE) {
            $this->addFlash('erreur', 'Facture disponible uniquement pour les transports complétés');
            return $this->redirectToRoute('app_transport_index');
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

    #[Route('/{id}/bill', name: 'app_transport_bill', methods: ['GET', 'POST'])]
    public function bill(Request $request, Transport $transport, MailerInterface $mailer): Response
    {
        if ($transport->getStatus() !== TransportStatus::COMPLETE) {
            $this->addFlash('erreur', 'Facture disponible uniquement pour les transports complétés');
            return $this->redirectToRoute('app_transport_index');
        }
    
        if ($request->isMethod('POST')) {
            try {
                $tndToUsdRate = 0.32;
                $studentEmail = $transport->getReservation()->getEtudiant()->getEmail();
                if (empty($studentEmail)) {
                    throw new \Exception("L'adresse email de l'étudiant est vide");
                }
    
                $this->logger->info('Starting bill generation', [
                    'transport_id' => $transport->getId(),
                    'student_email' => $studentEmail,
                    'mailer_from' => $this->mailerFrom
                ]);
    
                // Debug: Log mailer class
                $this->logger->debug('Mailer class', [
                    'class' => get_class($mailer),
                    'transport_id' => $transport->getId()
                ]);
                
    
                // Stripe invoice creation
                $customer = Customer::create([
                    'email' => $studentEmail,
                    'name' => $transport->getReservation()->getEtudiant()->getNom() . ' ' . $transport->getReservation()->getEtudiant()->getPrenom(),
                    'metadata' => ['cin' => $transport->getReservation()->getEtudiant()->getCin()]
                ]);
    
                $invoice = Invoice::create([
                    'customer' => $customer->id,
                    'collection_method' => 'send_invoice',
                    'auto_advance' => true,
                    'currency' => 'usd',
                    'description' => 'Facture pour transport #' . $transport->getId(),
                    'days_until_due' => 7,
                    'metadata' => [
                        'transport_id' => $transport->getId(),
                        'tnd_amount' => $transport->getTarif() + ($transport->getExtraCost() ?? 0),
                        'tnd_to_usd_rate' => $tndToUsdRate
                    ]
                ]);
    
                InvoiceItem::create([
                    'customer' => $customer->id,
                    'invoice' => $invoice->id,
                    'amount' => (int) ($transport->getTarif() * $tndToUsdRate * 100),
                    'currency' => 'usd',
                    'description' => 'Transport de ' . $transport->getReservation()->getAdresseDepart() . ' à ' . $transport->getReservation()->getAdresseDestination()
                ]);
    
                if ($transport->getExtraCost() && $transport->getExtraCost() > 0) {
                    InvoiceItem::create([
                        'customer' => $customer->id,
                        'invoice' => $invoice->id,
                        'amount' => (int) ($transport->getExtraCost() * $tndToUsdRate * 100),
                        'currency' => 'usd',
                        'description' => 'Frais supplémentaires'
                    ]);
                }
    
                $invoice->finalizeInvoice();
                $this->logger->info('Stripe invoice created', ['invoice_id' => $invoice->id]);
    
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
                $this->logger->info('PDF generated', ['size' => strlen($pdfContent)]);
    
                // Generate email content
                $emailHtml = $this->renderView('GestionTransport/transport/bill.html.twig', [
                    'transport' => $transport,
                    'invoiceData' => [
                        'date' => new \DateTime(),
                        'reference' => 'INV-' . $transport->getId(),
                        'usd_amount' => ($transport->getTarif() + ($transport->getExtraCost() ?? 0)) * $tndToUsdRate
                    ]
                ]);
    
                $emailText = 'Veuillez trouver ci-joint votre facture pour le transport #' . $transport->getId() . '. ' .
                    'Montant en TND: ' . ($transport->getTarif() + ($transport->getExtraCost() ?? 0)) . ' TND, ' .
                    'équivalent en USD: ' . number_format(($transport->getTarif() + ($transport->getExtraCost() ?? 0)) * $tndToUsdRate, 2) . ' USD.';
    
                $email = (new Email())
                    ->from(new Address($this->mailerFrom, $this->mailerFromName))
                    ->to($studentEmail)
                    ->subject('Votre facture pour le transport #' . $transport->getId())
                    ->text($emailText)
                    ->html($emailHtml)
                    ->attach($pdfContent, 'facture-transport-' . $transport->getId() . '.pdf', 'application/pdf');
    
                $this->logger->info('Attempting to send invoice email', [
                    'recipient' => $studentEmail,
                    'subject' => 'Votre facture pour le transport #' . $transport->getId(),
                    'has_attachment' => true
                ]);
    
                // Debug: Log before sending

                try {
                    $mailer->send($email);
                    $this->logger->info('Email sent successfully');
                } catch (\Exception $e) {
                    $this->logger->error('Email sending failed', ['error' => $e->getMessage()]);
                }
    
                // Debug: Log after sending
                $this->logger->info('Invoice email sent successfully', ['recipient' => $studentEmail]);
    
                $this->addFlash('succès', 'Facture envoyée à ' . $studentEmail);
            } catch (\Exception $e) {
                $this->logger->error('Failed to send invoice email', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'recipient' => $studentEmail ?? 'N/A'
                ]);
                file_put_contents('/tmp/bill-mailer.log', 'Error in mailer->send at ' . date('Y-m-d H:i:s') . ': ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
                $this->addFlash('erreur', 'Erreur lors de l\'envoi de la facture: ' . $e->getMessage());
            }
    
            return $this->redirectToRoute('app_transport_index');
        }
    
        return $this->render('GestionTransport/transport/bill_preview.html.twig', [
            'transport' => $transport,
            'invoiceData' => [
                'date' => new \DateTime(),
                'reference' => 'INV-' . $transport->getId(),
                'usd_amount' => ($transport->getTarif() + ($transport->getExtraCost() ?? 0)) * 0.32
            ]
        ]);
    }

    #[Route('/api/reservation/{id}/arrival-time', name: 'api_reservation_arrival_time', methods: ['GET'])]
    public function getReservationArrivalTime(ReservationTransport $reservation): JsonResponse
    {
        try {
            $etudiant = $reservation->getEtudiant();
            $tempsArrivage = $reservation->getTempsArrivage();

            return new JsonResponse([
                'arrivalTime' => $tempsArrivage ? date('c', strtotime($tempsArrivage)) : null,
                'formatted' => $tempsArrivage ? date('d/m/Y H:i', strtotime($tempsArrivage)) : null,
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