<?php
require 'vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

use Twilio\Rest\Client;

$sid = $_ENV['TWILIO_ACCOUNT_SID'] ?? getenv('TWILIO_ACCOUNT_SID');
$token = $_ENV['TWILIO_AUTH_TOKEN'] ?? getenv('TWILIO_AUTH_TOKEN');
$whatsappNumber = $_ENV['TWILIO_WHATSAPP_NUMBER'] ?? getenv('TWILIO_WHATSAPP_NUMBER');

// Verify variables are loaded
if (empty($sid) || empty($token) || empty($whatsappNumber)) {
    die('Missing required environment variables');
}

$client = new Client($sid, $token);
try {
    $message = $client->messages->create(
        'whatsapp:+21654154300', // Test number (must be in your Sandbox)
        [
            'from' => $whatsappNumber,
            'body' => 'Test message from Twilio'
        ]
    );
    echo 'Message SID: ' . $message->sid;
} catch (\Exception $e) {
    echo 'Error: ' . $e->getMessage();
}