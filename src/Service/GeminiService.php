<?php
namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use App\Entity\Logement;

class GeminiService
{
    private $apiKey;
    private $httpClient;
    private $logger;

    public function __construct(ParameterBagInterface $params, LoggerInterface $logger)
    {
        $this->apiKey = $params->get('app.gemini_api_key');
        $this->httpClient = HttpClient::create();
        $this->logger = $logger;
    }

    public function interpretQuery(string $query, array $logements): array
    {
        $logementsArray = [];
        foreach ($logements as $logement) {
            $logementsArray[] = [
                'id' => $logement->getId(),
                'description' => $logement->getDescription() ?? '',
                'type' => $logement->getType(),
                'adresse' => $logement->getAdresse(),
                'chambres' => $logement->getNbrChambre(),
                'prix' => $logement->getPrix(),
            ];
        }

        $this->logger->info('Logement summaries prepared for Gemini', [
            'query' => $query,
            'logements_count' => count($logementsArray),
            'logements' => $logementsArray,
        ]);

        // Modifié le prompt pour demander des objets avec id et reason
        $prompt = "Voici la requête d'un client : \"$query\".\n" .
                  "Voici la liste des logements disponibles : " . json_encode($logementsArray) . ".\n" .
                  "Sélectionne jusqu'à 3 logements correspondant le mieux à la requête, en tenant compte de la description, type, adresse, chambres et prix.\n" .
                  "Si la requête mentionne un nombre de chambres (ex. 's+4'), cherche des logements avec ce nombre exact de chambres.\n" .
                  "Si la requête mentionne un prix (ex. 'prix 200'), cherche des logements proches de ce prix (±200 €).\n" .
                  "Retourne un tableau JSON d'objets avec 'id' (ID numérique) et 'reason' (explication en français).\n" .
                  "Exemple : [{\"id\": 123, \"reason\": \"Correspond à 4 chambres et type Appartement.\"}]";

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent';

        try {
            $response = $this->httpClient->request('POST', $url, [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'query' => [
                    'key' => $this->apiKey,
                ],
                'json' => [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $prompt]
                            ]
                        ]
                    ]
                ]
            ]);

            $data = $response->toArray();
            $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '[]';
            $parsedResult = json_decode($text, true);

            $this->logger->info('Gemini API response', [
                'raw_text' => $text,
                'parsed_result' => $parsedResult,
            ]);

            if (!is_array($parsedResult)) {
                $this->logger->error('Invalid Gemini response format', ['response' => $text]);
                return $this->applyFallback($query, $logementsArray);
            }

            $validIds = array_column($logementsArray, 'id');
            foreach ($parsedResult as $item) {
                if (!isset($item['id']) || !isset($item['reason']) || !in_array($item['id'], $validIds)) {
                    $this->logger->error('Gemini returned invalid item', ['item' => $item]);
                    return $this->applyFallback($query, $logementsArray);
                }
            }

            if (empty($parsedResult)) {
                $this->logger->info('No results from Gemini, applying fallback', ['query' => $query]);
                return $this->applyFallback($query, $logementsArray);
            }

            $this->logger->info('Final results', ['results' => $parsedResult]);
            return $parsedResult;
        } catch (\Exception $e) {
            $this->logger->error('Error calling Gemini API', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->applyFallback($query, $logementsArray);
        }
    }

    private function applyFallback(string $query, array $logementsArray): array
    {
        $filteredLogements = $logementsArray;

        if (preg_match('/s\+(\d+)/i', $query, $matches)) {
            $bedroomCount = (int)$matches[1];
            $filteredLogements = array_filter($filteredLogements, function ($logement) use ($bedroomCount) {
                return $logement['chambres'] === $bedroomCount;
            });
            $this->logger->info('Fallback: Filtered by bedrooms', ['bedroomCount' => $bedroomCount, 'filtered_count' => count($filteredLogements)]);
        }

        if (preg_match('/prix\s*(\d+)/i', $query, $matches)) {
            $targetPrice = (int)$matches[1];
            $filteredLogements = array_filter($filteredLogements, function ($logement) use ($targetPrice) {
                return is_numeric($logement['prix']) && abs($logement['prix'] - $targetPrice) <= 200;
            });
            $this->logger->info('Fallback: Filtered by price', ['targetPrice' => $targetPrice, 'filtered_count' => count($filteredLogements)]);
        }

        $parsedResult = array_map(function ($logement) {
            $reason = "Correspondance manuelle : " .
                      ($logement['chambres'] ? "{$logement['chambres']} chambres, " : "") .
                      "prix {$logement['prix']}€.";
            return [
                'id' => $logement['id'],
                'reason' => $reason
            ];
        }, array_values($filteredLogements));

        $this->logger->info('Fallback results', ['results' => $parsedResult]);
        return $parsedResult;
    }

    public function summarizeResults(Logement $logement, string $query): string
    {
        $logementSummary = "Adresse: {$logement->getAdresse()}, Chambres: {$logement->getNbrChambre()}, Type: {$logement->getType()}, Prix: {$logement->getPrix()} €";

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent';
        try {
            $response = $this->httpClient->request('POST', $url, [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'query' => [
                    'key' => $this->apiKey,
                ],
                'json' => [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => "Résumez le logement suivant pour la requête '$query' : $logementSummary. Fournissez un résumé concis en français, de moins de 100 caractères."]
                            ]
                        ]
                    ]
                ]
            ]);

            $data = $response->toArray();
            return $data['candidates'][0]['content']['parts'][0]['text'] ?? 'Aucun résumé disponible.';
        } catch (\Exception $e) {
            $this->logger->error('Error summarizing logement', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return 'Erreur lors du résumé.';
        }
    }
}