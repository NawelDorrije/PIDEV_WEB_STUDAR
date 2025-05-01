<?php
// src/Service/IAService.php
namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class IAService
{
    private HttpClientInterface $client;
    private string $apiKey;

    public function __construct(HttpClientInterface $client, string $openaiApiKey)
    {
        $this->client = $client;
        $this->apiKey = $openaiApiKey;
    }

    public function analyserRaison(string $raison): bool
    {
        try {
            $response = $this->client->request('POST', 'https://api.openai.com/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => 'gpt-3.5-turbo',
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'Tu es un assistant qui juge si une raison d’annulation est valide ou non. Réponds uniquement par "valide" ou "non valide".',
                        ],
                        [
                            'role' => 'user',
                            'content' => $raison,
                        ],
                    ],
                    'temperature' => 0.2,
                ],
            ]);

            $data = $response->toArray();
            $content = strtolower(trim($data['choices'][0]['message']['content'] ?? ''));

            return $content === 'valide';
        } catch (\Exception $e) {
            // En cas d’erreur, on considère la raison comme invalide par sécurité
            return false;
        }
    }
}