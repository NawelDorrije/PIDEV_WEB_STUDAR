<?php

namespace App\Controller\GestionMeubles;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Annotation\Route;

class SendEmailMeubleController extends AbstractController
{
    #[Route('/send-email-meuble', name: 'send_email_meuble')]
    public function sendEmail(MailerInterface $mailer): Response
    {
        $email = (new Email())
            ->from('naweldorrije789@gmail.com') // ton adresse Gmail
            ->to('naweldorrije789@gmail.com') // mets ici l'adresse de destination
            ->subject('Confirmation de commande de meuble')
            ->text("Merci pour votre commande. Votre meuble sera livré sous peu.\n- L'équipe STU-DAR");

        $mailer->send($email);

        return new Response('Email de meuble envoyé avec succès !');
    }
}
