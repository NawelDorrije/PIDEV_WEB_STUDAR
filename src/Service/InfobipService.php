<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

class InfobipService
{
    private const INFOBIP_BASE_URL = 'https://d9xerl.api.infobip.com';
    private const INFOBIP_API_KEY = 'c2807e18f1ddae57f6dd94dd50daab53-ce852d37-5d84-4096-9a27-be2e71518e73';
    private const INFOBIP_SENDER_ID = '447491163443'; // Or your registered sender name/number

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger
    ) {}

    public function sendSms(string $to, string $message): void
    {
        try {
            $formattedTo = $this->formatPhoneNumber($to);
            
            $response = $this->httpClient->request('POST', self::INFOBIP_BASE_URL . '/sms/2/text/advanced', [
                'headers' => [
                    'Authorization' => 'App ' . self::INFOBIP_API_KEY,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'json' => [
                    'messages' => [
                        [
                            'destinations' => [['to' => $formattedTo]],
                            'from' => self::INFOBIP_SENDER_ID,
                            'text' => $message,
                        ]
                    ]
                ],
            ]);

            $statusCode = $response->getStatusCode();
            $content = $response->toArray(false);

            if ($statusCode !== 200) {
                $this->logger->error('Infobip API error', [
                    'status' => $statusCode,
                    'response' => $content
                ]);
                throw new \Exception('Failed to send SMS: ' . ($content['requestError']['serviceException']['text'] ?? 'Unknown error'));
            }

            $this->logger->info('SMS sent successfully', [
                'to' => $formattedTo,
                'status' => $statusCode,
                'messageId' => $content['messages'][0]['messageId'] ?? null
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to send SMS: ' . $e->getMessage(), [
                'exception' => $e,
                'to' => $to
            ]);
            throw $e;
        }
    }

    private function formatPhoneNumber(string $number): string
    {
        // Remove any non-digit characters except '+'
        $cleaned = preg_replace('/[^0-9+]/', '', $number);
        
        // If number starts with '0', replace it with Tunisia country code
        if (str_starts_with($cleaned, '0')) {
            $cleaned = '+216' . substr($cleaned, 1);
        }
        // If number doesn't start with '+', add Tunisia country code
        elseif (!str_starts_with($cleaned, '+')) {
            $cleaned = '+216' . $cleaned;
        }
        
        return $cleaned;
    }
}