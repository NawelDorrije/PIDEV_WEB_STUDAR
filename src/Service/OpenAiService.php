<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class OpenAiService
{
    private HttpClientInterface $client;
    private string $apiKey;

    public function __construct(HttpClientInterface $client, string $apiKey)
    {
        if (empty($apiKey)) {
            throw new \InvalidArgumentException('La clé API OpenAI est manquante.');
        }
        $this->client = $client;
        $this->apiKey = $apiKey;
    }

    public function isReasonValid(string $raison): bool
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
            $content = strtolower($data['choices'][0]['message']['content'] ?? '');
            if (!in_array($content, ['valide', 'non valide'])) {
                throw new \RuntimeException('Réponse inattendue de l’API OpenAI.');
            }
            return $content === 'valide';
        } catch (\Exception $e) {
            throw new \RuntimeException('Erreur lors de l’analyse : ' . $e->getMessage());
        }
    }
}