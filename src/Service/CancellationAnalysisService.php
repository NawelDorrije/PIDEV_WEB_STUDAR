<?php
// src/Service/CancellationAnalysisService.php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

class CancellationAnalysisService
{
    private string $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?';
    private string $apiKey = 'AIzaSyDvCo2Ng6yVwBuuCG7Sl6JyBL8gU9G7T9k'; // Replace with your key

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger
    ) {}

    public function analyzeCancellationReason(string $reason): array
    {
        $prompt = sprintf(
            "Vous êtes un assistant qui évalue les raisons d'annulation de commandes sur une plateforme de commerce de meubles. Analysez la raison suivante : '%s'. Déterminez si elle est valide ou invalide. Une raison est valide si elle est spécifique, liée à la commande et raisonnable, par exemple : problèmes avec le produit (défaut, non conforme), soucis de livraison (retard, adresse incorrecte), urgence personnelle (hospitalisation, déménagement urgent), ou difficultés liées au paiement ou à l'adresse de livraison (par exemple, carte refusée sans possibilité de changer de méthode de paiement sur la plateforme, ou adresse de livraison incorrecte sans option de modification). Une raison est invalide si elle est vague (par exemple, 'je ne veux plus'), non pertinente (par exemple, 'je change d'avis sans raison'), ou insuffisamment détaillée. Retournez la réponse au format suivant : 'Valid: [explication]' ou 'Invalid: [explication]'",
            $reason
        );

        try {
            $response = $this->httpClient->request(
                'POST',
                $this->apiUrl,
                [
                    'query' => ['key' => $this->apiKey],
                    'json' => [
                        'contents' => [
                            'parts' => [['text' => $prompt]]
                        ]
                    ],
                    'timeout' => 15
                ]
            );

            $data = $response->toArray();
            $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? 'Error: Unexpected API response format';

            // Parse the response to extract validity and explanation
            if (str_starts_with($text, 'Valid:') || str_starts_with($text, 'Invalid:')) {
                $isValid = str_starts_with($text, 'Valid:');
                $explanation = trim(substr($text, strpos($text, ':') + 1));
                return [
                    'isValid' => $isValid,
                    'explanation' => $explanation
                ];
            }

            // Handle unexpected response format
            $this->logger->error('Unexpected API response format: ' . $text);
            return [
                'isValid' => false,
                'explanation' => 'Erreur : format de réponse API inattendu'
            ];

        } catch (\Exception $e) {
            $this->logger->error('API Error during cancellation analysis: ' . $e->getMessage());
            return [
                'isValid' => false,
                'explanation' => 'Erreur : ' . $e->getMessage()
            ];
        }
    }
}