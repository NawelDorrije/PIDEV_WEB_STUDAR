<?php

namespace App\Controller;

use App\Repository\ReclamationRepository;
use App\Service\ChatbotService;
use App\Service\PdfGenerator;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Routing\Annotation\Route;

class StatisticsController extends AbstractController
{
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    #[Route('/statistics', name: 'app_statistics')]
    public function index(Request $request, ReclamationRepository $reclamationRepository, ChatbotService $chatbotService): Response
    {
        $year = (int) $request->query->get('year', 2025);
        $month = (int) $request->query->get('month', 3); // Default to March
        $byMonth = $reclamationRepository->getStatsByMonth($year);
        $dailyReclamations = $reclamationRepository->getDailyReclamations($year, $month);

        // Get stats by status
        $statsByStatus = $reclamationRepository->getStatsByStatus();
        $labels = array_keys($statsByStatus);
        $data = array_values($statsByStatus);

        // Prepare data for status bar chart
        $statusBarData = [
            'en_cours' => array_map(fn($label) => $label === 'en cours' ? $statsByStatus[$label] : 0, $labels),
            'traite' => array_map(fn($label) => $label === 'traité' ? $statsByStatus[$label] : 0, $labels),
            'refuse' => array_map(fn($label) => $label === 'refusé' ? $statsByStatus[$label] : 0, $labels),
        ];

        // Compute analytics insights
        $analytics = [
            'trend' => $this->analyzeTrend($byMonth),
            'responseEfficiency' => $this->analyzeResponseEfficiency($reclamationRepository->getResponseRate()),
            'statusDistribution' => $this->analyzeStatusDistribution($statsByStatus),
            'resolutionTime' => $this->analyzeResolutionTime($reclamationRepository->getResolutionTimeDistribution()),
            'recommendations' => [],
        ];

        // Generate AI-powered recommendations
        $aiPrompt = $this->buildAiPrompt($analytics);
        $aiResponse = $chatbotService->getResponse($aiPrompt);
        if (!str_starts_with($aiResponse, 'Error')) {
            $analytics['recommendations'] = array_filter(array_map('trim', explode("\n", $aiResponse)), fn($line) => !empty($line));
        } else {
            $analytics['recommendations'] = ['Erreur lors de la génération des recommandations. Veuillez réessayer plus tard.'];
        }

        $stats = [
            'totalReclamations' => $reclamationRepository->getTotalReclamations(),
            'responseRate' => $reclamationRepository->getResponseRate(),
            'byStatus' => [
                'labels' => $labels,
                'data' => $data,
            ],
            'byMonth' => [
                'labels' => array_keys($byMonth),
                'data' => array_values($byMonth),
            ],
            'dailyReclamations' => [
                'labels' => array_keys($dailyReclamations),
                'data' => array_values($dailyReclamations),
            ],
            'resolutionTime' => [
                'labels' => array_keys($reclamationRepository->getResolutionTimeDistribution()),
                'data' => array_values($reclamationRepository->getResolutionTimeDistribution()),
            ],
            'statusBarData' => $statusBarData,
            'analytics' => $analytics,
        ];

        return $this->render('statistics/index.html.twig', [
            'stats' => $stats,
            'year' => $year,
            'month' => $month,
        ]);
    }

    #[Route('/statistics/export', name: 'app_statistics_export')]
    public function export(Request $request, ReclamationRepository $reclamationRepository, PdfGenerator $pdfGenerator, ChatbotService $chatbotService): Response
    {
        try {
            $year = (int) $request->query->get('year', 2025);
            $month = (int) $request->query->get('month', 3);
            $byMonth = $reclamationRepository->getStatsByMonth($year);
            $dailyReclamations = $reclamationRepository->getDailyReclamations($year, $month);

            $statsByStatus = $reclamationRepository->getStatsByStatus();
            $analytics = [
                'trend' => $this->analyzeTrend($byMonth),
                'responseEfficiency' => $this->analyzeResponseEfficiency($reclamationRepository->getResponseRate()),
                'statusDistribution' => $this->analyzeStatusDistribution($statsByStatus),
                'resolutionTime' => $this->analyzeResolutionTime($reclamationRepository->getResolutionTimeDistribution()),
                'recommendations' => [],
            ];

            // Generate AI recommendations for PDF
            $aiPrompt = $this->buildAiPrompt($analytics);
            $aiResponse = $chatbotService->getResponse($aiPrompt);
            if (!str_starts_with($aiResponse, 'Error')) {
                $analytics['recommendations'] = array_filter(array_map('trim', explode("\n", $aiResponse)), fn($line) => !empty($line));
            } else {
                $analytics['recommendations'] = ['Erreur lors de la génération des recommandations.'];
            }

            $stats = [
                'totalReclamations' => $reclamationRepository->getTotalReclamations(),
                'responseRate' => $reclamationRepository->getResponseRate(),
                'byStatus' => [
                    'labels' => array_keys($statsByStatus),
                    'data' => array_values($statsByStatus),
                ],
                'byMonth' => [
                    'labels' => array_keys($byMonth),
                    'data' => array_values($byMonth),
                ],
                'dailyReclamations' => [
                    'labels' => array_keys($dailyReclamations),
                    'data' => array_values($dailyReclamations),
                ],
                'resolutionTime' => [
                    'labels' => array_keys($reclamationRepository->getResolutionTimeDistribution()),
                    'data' => array_values($reclamationRepository->getResolutionTimeDistribution()),
                ],
                'analytics' => $analytics,
            ];

            // Log stats for debugging
            $this->logger->debug('StatisticsController: Stats for PDF', ['stats' => $stats]);

            // Render PDF template
            try {
                $html = $this->renderView('pdf/pdf.html.twig', [
                    'stats' => $stats,
                    'year' => $year,
                    'month' => $month,
                    // 'logo_base64' => base64_encode(file_get_contents('assets/images/logo.png')), // Optional
                ]);
            } catch (\Twig\Error\Error $e) {
                $this->logger->error('StatisticsController: Twig rendering error', [
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ]);
                throw new \RuntimeException('Erreur lors du rendu du modèle Twig : ' . $e->getMessage());
            }

            // Log HTML output
            $this->logger->debug('StatisticsController: Rendered HTML for PDF', ['html' => substr($html, 0, 500)]);

            // Generate PDF
            $filename = sys_get_temp_dir() . '/reclamations_analytics_' . date('Ymd') . '.pdf';
            $pdfPath = $pdfGenerator->generatePdf($html, $filename);

            // Log success
            $this->logger->info('StatisticsController: PDF generated', ['filename' => $pdfPath]);

            // Return the PDF as a downloadable file
            return new BinaryFileResponse($pdfPath, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . basename($filename) . '"',
            ]);

        } catch (\Exception $e) {
            $this->logger->error('StatisticsController: Error generating PDF', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw new \RuntimeException('Erreur lors de la génération du PDF : ' . $e->getMessage());
        }
    }

    private function analyzeTrend(array $byMonth): array
    {
        $counts = array_values($byMonth);
        $labels = array_keys($byMonth);
        if (count($counts) < 2) {
            return ['summary' => 'Données insuffisantes pour déterminer une tendance.', 'peakMonth' => null];
        }

        $differences = [];
        for ($i = 1; $i < count($counts); $i++) {
            $differences[] = $counts[$i] - $counts[$i - 1];
        }

        $avgDifference = array_sum($differences) / count($differences);
        $trend = $avgDifference > 0 ? 'en augmentation' : ($avgDifference < 0 ? 'en diminution' : 'stable');

        $peakIndex = array_search(max($counts), $counts);
        $peakMonth = $labels[$peakIndex];

        return [
            'summary' => "Le volume des réclamations est $trend au cours de l'année.",
            'peakMonth' => $peakMonth,
        ];
    }

    private function analyzeResponseEfficiency(float $responseRate): array
    {
        $status = $responseRate >= 80 ? 'bon' : ($responseRate >= 50 ? 'modéré' : 'faible');
        return [
            'responseRate' => $responseRate,
            'summary' => sprintf('Le taux de réponse est de %.1f%%, ce qui est %s.', $responseRate, $status),
        ];
    }

    private function analyzeStatusDistribution(array $statsByStatus): array
    {
        $total = array_sum($statsByStatus);
        if ($total == 0) {
            return ['summary' => 'Aucune réclamation à analyser.', 'enCoursPercentage' => 0];
        }

        $enCours = $statsByStatus['en cours'] ?? 0;
        $enCoursPercentage = ($enCours / $total) * 100;

        return [
            'summary' => sprintf('%.1f%% des réclamations sont encore en cours.', $enCoursPercentage),
            'enCoursPercentage' => $enCoursPercentage,
        ];
    }

    private function analyzeResolutionTime(array $resolutionTime): array
    {
        $total = array_sum($resolutionTime);
        if ($total == 0) {
            return ['summary' => 'Aucune réclamation résolue à analyser.', 'slowPercentage' => 0];
        }

        $slow = $resolutionTime['Slow (>7 days or unresolved)'] ?? 0;
        $slowPercentage = ($slow / $total) * 100;

        return [
            'summary' => sprintf('%.1f%% des réclamations prennent plus de 7 jours à être résolues ou restent non résolues.', $slowPercentage),
            'slowPercentage' => $slowPercentage,
        ];
    }

    private function buildAiPrompt(array $analytics): string
    {
        return sprintf(
            "Vous êtes un analyste expert en gestion des réclamations. Voici les données analytiques pour un système de gestion des réclamations en %s :\n".
            "- Tendance : %s (Mois de pointe : %s)\n".
            "- Efficacité des réponses : Taux de réponse de %.1f%%, classé comme %s\n".
            "- Répartition par statut : %.1f%% des réclamations sont en cours\n".
            "- Temps de résolution : %.1f%% des réclamations prennent plus de 7 jours ou sont non résolues\n".
            "Fournissez une liste concise de recommandations (en français, sous forme de points) pour améliorer la gestion des réclamations basée sur ces données.",
            date('Y'),
            $analytics['trend']['summary'],
            $analytics['trend']['peakMonth'] ?? 'non disponible',
            $analytics['responseEfficiency']['responseRate'],
            $analytics['responseEfficiency']['summary'],
            $analytics['statusDistribution']['enCoursPercentage'],
            $analytics['resolutionTime']['slowPercentage']
        );
    }
}