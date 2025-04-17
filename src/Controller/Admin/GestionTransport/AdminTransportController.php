<?php

namespace App\Controller\Admin\GestionTransport;

use App\Entity\GestionTransport\Voiture;
use App\Entity\GestionTransport\Transport;
use App\Repository\GestionTransport\VoitureRepository;
use App\Repository\GestionTransport\TransportRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/gestiontransport')]
  class AdminTransportController extends AbstractController
    {
        #[Route('/adminTransport', name: 'app_gestion_transport_dashboard', methods: ['GET'])]
        public function adminTransport(
            VoitureRepository $voitureRepository,
            TransportRepository $transportRepository,
            Request $request
        ): Response {
            $this->denyAccessUnlessGranted('ROLE_ADMIN');
            
            // Get filter parameters
            $disponibilite = $request->query->get('disponibilite');
            $status = $request->query->get('status');
    
            // Filter vehicles
            $queryBuilder = $voitureRepository->createQueryBuilder('v');
            if ($disponibilite) {
                $queryBuilder->andWhere('v.disponibilite = :disponibilite')
                            ->setParameter('disponibilite', $disponibilite);
            }
            $voitures = $queryBuilder->getQuery()->getResult();
            
            // Filter transports
            try {
                $transportQb = $transportRepository->createQueryBuilder('t')
                    ->innerJoin('t.reservation', 'r')
                    ->addSelect('r');
                
                if ($status) {
                    $transportQb->andWhere('t.status = :status')
                               ->setParameter('status', $status);
                }
                
                $transports = $transportQb->getQuery()->getResult();
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error loading transports: ' . $e->getMessage());
                $transports = [];
            }
        
            return $this->render('admin/GestionTransport/dashboard.html.twig', [
                'voitures' => $voitures,
                'transports' => $transports,
            ]);
        }
    
    #[IsGranted('ROLE_ADMIN')]
    #[Route('/transport/{id}', name: 'admin_transport_show', methods: ['GET'])]
    public function showTransport(Transport $transport): Response
    {
        return $this->render('admin/GestionTransport/transport/show.html.twig', [
            'transport' => $transport,
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
    // Change this route path
    #[IsGranted('ROLE_ADMIN')]
    #[Route('/stats', name: 'admin_transport_stats', methods: ['GET'])]
    public function getStats(VoitureRepository $voitureRepo, TransportRepository $transportRepo): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        // Get available years
        $years = $transportRepo->createQueryBuilder('t')
            ->select("DISTINCT DATE_FORMAT(t.timestamp, '%Y') as year")
            ->orderBy('year', 'DESC')
            ->getQuery()
            ->getSingleColumnResult();
        
        $yearToUse = !empty($years) ? max($years) : (new \DateTime())->format('Y');
        
        // Get revenue data
        $revenueData = $transportRepo->getRevenueByMonth($yearToUse);
        
        // Debug revenue data
        dump([
            'year_used' => $yearToUse,
            'raw_revenue_data' => $revenueData,
            'normalized_revenue' => $this->normalizeRevenueData($revenueData)
        ]);
        
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
        // Check if data already has labels and values
        if (isset($data['labels'], $data['values'])) {
            $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            $values = array_fill(0, 12, 0);
    
            // Map provided values to correct months
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
    
        // Fallback for empty or invalid data
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
   
}