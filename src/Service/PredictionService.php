<?php
namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

class PredictionService
{
    private $httpClient;
    private $googleNlApiKey;
    private $logger;

    public function __construct(
        HttpClientInterface $httpClient,
        string $googleNlApiKey,
        LoggerInterface $logger
    ) {
        $this->httpClient = $httpClient;
        $this->googleNlApiKey = trim($googleNlApiKey);
        $this->logger = $logger;
    }

    public function predict(string $raison): string
    {
        $raison = trim($raison);
        $this->logger->info('Raison envoyée à Google Cloud NL: ' . $raison);

        if (empty($raison)) {
            $this->logger->warning('Raison vide fournie');
            return 'Non valide';
        }

        try {
            $response = $this->httpClient->request('POST', "https://language.googleapis.com/v1/documents:analyzeSentiment?key={$this->googleNlApiKey}", [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'document' => [
                        'type' => 'PLAIN_TEXT',
                        'content' => $raison,
                    ],
                    'encodingType' => 'UTF8',
                ],
            ]);

            $result = $response->toArray();
            $this->logger->info('Réponse Google Cloud NL: ' . json_encode($result, JSON_PRETTY_PRINT));

            if (empty($result) || !isset($result['documentSentiment']['score'])) {
                $this->logger->error('Réponse Google Cloud NL invalide ou vide');
                return 'Non valide';
            }

            $score = $result['documentSentiment']['score'];
            $this->logger->info('Score de sentiment: ' . $score);

            // Un score >= 0 (neutre ou positif) est considéré comme valide
            return ($score >= 0) ? 'Valide' : 'Non valide';
        } catch (\Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface $e) {
            $this->logger->error('Erreur Google Cloud NL API: ' . $e->getMessage(), [
                'status' => $e->getResponse()->getStatusCode(),
                'response' => $e->getResponse()->getContent(false),
            ]);
            return 'Non valide';
        } catch (\Exception $e) {
            $this->logger->error('Erreur inattendue dans PredictionService: ' . $e->getMessage());
            return 'Non valide';
        }
    }
}