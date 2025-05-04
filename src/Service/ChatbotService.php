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
     */
    public function transcribeAudio(string $audioPath): string
    {
        try {
            if (!file_exists($audioPath)) {
                throw new \Exception('Audio file not found: ' . $audioPath);
            }

            $audioContent = file_get_contents($audioPath);
            $base64Audio = base64_encode($audioContent);
            $mimeType = mime_content_type($audioPath);

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

    /**
     * Analyze a report to determine if it is legitimate
     */
    public function analyzeReport(string $reason, ?string $message): array
    {
        $messageContent = $message ?? 'Aucun message fourni.';
        $prompt = "Analyse le signalement suivant pour déterminer s'il est légitime (vrai) ou non (faux).\n" .
                  "Raison du signalement : \"$reason\"\n" .
                  "Contenu du message signalé : \"$messageContent\"\n" .
                  "Un signalement est considéré comme faux s'il semble malveillant, vague, non fondé, ou contient des informations contradictoires.\n" .
                  "Retourne uniquement un objet JSON valide avec 'isLegitimate' (booléen : true pour légitime, false pour non légitime) et 'reason' (explication en français, max 100 caractères). Ne retourne rien d'autre.\n" .
                  "Exemple : {\"isLegitimate\": true, \"reason\": \"La raison est claire et le message est inapproprié.\"}";

        try {
            $response = $this->httpClient->request('POST', $this->textApiUrl, [
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
                ],
                'timeout' => 60
            ]);

            $statusCode = $response->getStatusCode();
            $data = $response->toArray(false);

            $this->logger->info('Gemini API raw response for report analysis', [
                'status_code' => $statusCode,
                'response_data' => $data,
            ]);

            if ($statusCode !== 200 || isset($data['error'])) {
                $errorMessage = $data['error']['message'] ?? 'Unknown API error';
                $this->logger->error('Gemini API error', [
                    'status_code' => $statusCode,
                    'error' => $data['error'] ?? 'No error details',
                ]);
                return [
                    'isLegitimate' => false,
                    'reason' => 'Erreur API : ' . $errorMessage,
                    'message' => $messageContent
                ];
            }

            $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
            if (!$text) {
                $this->logger->error('No text found in Gemini API response', ['response' => $data]);
                return [
                    'isLegitimate' => false,
                    'reason' => 'Aucune réponse textuelle reçue de l\'API.',
                    'message' => $messageContent
                ];
            }

            $this->logger->info('Gemini API response text', [
                'text' => $text,
            ]);

            $cleanText = trim($text);
            $cleanText = preg_replace('/^```(?:json)?\s*\n|\n```$|^`+|`+$/m', '', $cleanText);

            $this->logger->info('Gemini API cleaned response text', [
                'clean_text' => $cleanText,
            ]);

            if (!str_starts_with($cleanText, '{') && !str_starts_with($cleanText, '[')) {
                $this->logger->error('Gemini response is not JSON after cleaning', ['clean_text' => $cleanText]);
                return [
                    'isLegitimate' => false,
                    'reason' => 'Réponse non-JSON reçue de l\'API : ' . substr($cleanText, 0, 100),
                    'message' => $messageContent
                ];
            }

            $parsedResult = json_decode($cleanText, true);
            if ($parsedResult === null) {
                $this->logger->error('Failed to parse JSON from Gemini response', [
                    'clean_text' => $cleanText,
                    'json_error' => json_last_error_msg(),
                ]);
                return [
                    'isLegitimate' => false,
                    'reason' => 'Erreur de parsing JSON : ' . json_last_error_msg(),
                    'message' => $messageContent
                ];
            }

            if (!is_array($parsedResult) || !isset($parsedResult['isLegitimate']) || !isset($parsedResult['reason'])) {
                $this->logger->error('Invalid Gemini response format for report analysis', [
                    'clean_text' => $cleanText,
                    'parsed_result' => $parsedResult,
                ]);
                return [
                    'isLegitimate' => false,
                    'reason' => 'Format de réponse invalide de l\'API.',
                    'message' => $messageContent
                ];
            }

            $this->logger->info('Gemini API response for report analysis', [
                'clean_text' => $cleanText,
                'parsed_result' => $parsedResult,
            ]);

            // Add the original message content to the response
            $parsedResult['message'] = $messageContent;
            return $parsedResult;
        } catch (\Exception $e) {
            $this->logger->error('Error calling Gemini API for report analysis', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return [
                'isLegitimate' => false,
                'reason' => 'Erreur lors de l\'analyse : ' . $e->getMessage(),
                'message' => $messageContent
            ];
        }
    }
}