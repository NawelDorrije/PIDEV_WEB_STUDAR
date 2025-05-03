<?php
namespace App\Service;

use Twilio\Rest\Client;

class TwilioService
{
    private $client;
    private $whatsappNumber;

    public function __construct(string $accountSid, string $authToken, string $whatsappNumber)
    {
        $this->client = new Client($accountSid, $authToken);
        $this->whatsappNumber = $whatsappNumber;
    }

    public function sendWhatsAppMessage(string $to, string $message): void
    {
        $this->client->messages->create(
            "whatsapp:$to",
            [
                'from' => $this->whatsappNumber,
                'body' => $message,
            ]
        );
    }
}