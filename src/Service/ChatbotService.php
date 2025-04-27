<?php
// src/Service/ChatbotService.php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

class ChatbotService
{
    private string $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?';
    private string $apiKey = 'AIzaSyDvCo2Ng6yVwBuuCG7Sl6JyBL8gU9G7T9k'; // Replace with your key

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger
    ) {}

    public function getResponse(string $prompt): string
    {
        try {
            $response = $this->httpClient->request(
                'POST',
                $this->apiUrl,
                [
                    'query' => ['key' => $this->apiKey], // Correct way to pass API key
                    'json' => [
                        'contents' => [
                            'parts' => [['text' => $prompt]]
                        ]
                    ],
                    'timeout' => 15
                ]
            );

            $data = $response->toArray();
            
            // Handle Gemini's response structure
            return $data['candidates'][0]['content']['parts'][0]['text'] 
                ?? 'Error: Unexpected API response format';

        } catch (\Exception $e) {
            $this->logger->error('API Error: ' . $e->getMessage());
            return 'Error: ' . $e->getMessage();
        }
    }
}