<?php
// src/Service/ChatbotService.php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

class ChatbotService
{
    private string $textApiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?';
    private string $multimodalApiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-pro:generateContent?';
    private string $apiKey = 'AIzaSyDvCo2Ng6yVwBuuCG7Sl6JyBL8gU9G7T9k'; // Hardcoded API key

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger
    ) {}

    /**
     * Get text response for a prompt using gemini-2.0-flash
     */
    public function getResponse(string $prompt): string
    {
        try {
            $response = $this->httpClient->request(
                'POST',
                $this->textApiUrl,
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
            return $data['candidates'][0]['content']['parts'][0]['text'] 
                ?? 'Error: Unexpected API response format';
        } catch (\Exception $e) {
            $this->logger->error('Text API Error: ' . $e->getMessage());
            return 'Error: ' . $e->getMessage();
        }
    }

    /**
     * Transcribe audio file using gemini-1.5-pro
     *
     * @param string $audioPath Full path to the audio file
     * @return string Transcribed text
     * @throws \Exception on API failure
     */
   // In ChatbotService.php, update transcribeAudio
public function transcribeAudio(string $audioPath): string
{
    try {
        if (!file_exists($audioPath)) {
            throw new \Exception('Audio file not found: ' . $audioPath);
        }

        $audioContent = file_get_contents($audioPath);
        $base64Audio = base64_encode($audioContent);
        $mimeType = mime_content_type($audioPath);

        // Normalize video/webm to audio/webm for transcription
        if ($mimeType === 'video/webm') {
            $mimeType = 'audio/webm';
        }

        $response = $this->httpClient->request(
            'POST',
            $this->multimodalApiUrl,
            [
                'query' => ['key' => $this->apiKey],
                'json' => [
                    'contents' => [
                        'parts' => [
                            [
                                'inlineData' => [
                                    'mimeType' => $mimeType,
                                    'data' => $base64Audio
                                ]
                            ],
                            ['text' => 'Please transcribe this audio to text in French.']
                        ]
                    ]
                ],
                'timeout' => 30
            ]
        );

        $data = $response->toArray();
        return $data['candidates'][0]['content']['parts'][0]['text'] 
            ?? 'Error: Unexpected API response';
    } catch (\Exception $e) {
        $this->logger->error('Transcription API Error: ' . $e->getMessage());
        throw $e;
    }
}
}