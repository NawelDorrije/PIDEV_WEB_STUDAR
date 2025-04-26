<?php

namespace App\Controller;

use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;

class TestEmailController extends AbstractController
{
    #[Route('/api/test-email', name: 'app_test_email', methods: ['GET'])]
    public function testEmail(MailerInterface $mailer, LoggerInterface $logger): JsonResponse
    {
        try {
            $email = (new Email())
                ->from($_ENV['MAILER_FROM'] ?? 'studar21@gmail.com')
                ->to('nourmougou@gmail.com')
                ->subject('Test Email from Studar')
                ->html('<p>This is a test email from your Symfony application.</p>');

            $mailer->send($email);
            $logger->info('Test email sent successfully', [
                'to' => 'nourmougou2@gmail.com',
                'dsn' => $_ENV['MAILER_DSN'] ?? 'not set'
            ]);
            return $this->json(['success' => true, 'message' => 'Test email sent']);
        } catch (\Exception $e) {
            $logger->error('Failed to send test email', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'dsn' => $_ENV['MAILER_DSN'] ?? 'not set'
            ]);
            return $this->json([
                'success' => false,
                'message' => 'Failed to send test email: ' . $e->getMessage()
            ], 500);
        }
    }
}