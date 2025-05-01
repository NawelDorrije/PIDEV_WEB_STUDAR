<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;

class MailController extends AbstractController
{
    #[Route('/send-test-email', name: 'send_test_email')]
    public function sendTestEmail(MailerInterface $mailer): Response
    {
        try {
            $email = (new Email())
                ->from('naweldorrije789@gmail.com')
                ->to('dorrijenawel@gmail.com')
                ->subject('Test Email from Symfony')
                ->text('Ceci est un email de test envoyé depuis Symfony !')
                ->html('<p>Ceci est un email de test envoyé depuis Symfony !</p>');
    
            $mailer->send($email);
    
            return new Response('Email envoyé avec succès !');
        } catch (TransportExceptionInterface $e) {
            return new Response('Erreur lors de l\'envoi de l\'email : ' . $e->getMessage());
        }
    }
}