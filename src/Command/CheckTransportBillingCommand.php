<?php

namespace App\Command;

use App\Entity\GestionTransport\Transport;
use App\Enums\GestionTransport\TransportStatus;
use App\Service\StripeService;
use App\Service\InfobipService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsCommand(name: 'app:check-transport-billing')]
class CheckTransportBillingCommand extends Command
{
    private $entityManager;
    private $stripeService;
    private $infobipService;
    private $mailer;
    private $urlGenerator;

    public function __construct(
        EntityManagerInterface $entityManager,
        StripeService $stripeService,
        InfobipService $infobipService,
        MailerInterface $mailer,
        UrlGeneratorInterface $urlGenerator
    ) {
        $this->entityManager = $entityManager;
        $this->stripeService = $stripeService;
        $this->infobipService = $infobipService;
        $this->mailer = $mailer;
        $this->urlGenerator = $urlGenerator;

        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $transportRepository = $this->entityManager->getRepository(Transport::class);
        
        // Only process transports that are completed and not yet invoiced
        $transports = $transportRepository->createQueryBuilder('t')
            ->where('t.status = :status')
            ->andWhere('t.stripeInvoiceId IS NULL')
            ->setParameter('status', TransportStatus::COMPLETE)
            ->getQuery()
            ->getResult();

        foreach ($transports as $transport) {
            try {
                $etudiant = $transport->getReservation()->getEtudiant();
                $cin = $etudiant->getCin();
                
                // Calculate any extra costs
                $extraCost = $this->calculateExtraCost($transport);
                $totalAmount = $transport->getTarif() + $extraCost;

                // Create invoice in Stripe
                $invoice = $this->stripeService->createInvoice([
                    'transport_id' => $transport->getId(),
                    'student_cin' => $cin,
                    'description' => 'Transport #'.$transport->getId(),
                    'amount' => $totalAmount,
                    'currency' => 'TND'
                ]);

                // Update transport with invoice reference
                $transport->setStripeInvoiceId($invoice['id']);
                $transport->setExtraCost($extraCost);
                $this->entityManager->persist($transport);
                
                // Send invoice email
                $this->sendInvoiceEmail($transport, $invoice, $output);
                
                $output->writeln(sprintf(
                    'Created invoice %s for transport #%d (Amount: %.2f TND)',
                    $invoice['id'],
                    $transport->getId(),
                    $totalAmount
                ));

            } catch (\Exception $e) {
                $output->writeln(sprintf(
                    'Error processing transport #%d: %s',
                    $transport->getId(),
                    $e->getMessage()
                ));
                continue;
            }
        }

        $this->entityManager->flush();
        return Command::SUCCESS;
    }

    private function calculateExtraCost(Transport $transport): float
    {
        $extraCost = 0.0;
        
        // Calculate loading time overrun penalty
        $loadingOverrun = ($transport->getLoadingTimeActual() ?? 0) - $transport->getLoadingTimeAllowed();
        if ($loadingOverrun > 0) {
            $extraCost += $loadingOverrun * 5; // 5 TND per minute
        }

        // Calculate unloading time overrun penalty
        $unloadingOverrun = ($transport->getUnloadingTimeActual() ?? 0) - $transport->getUnloadingTimeAllowed();
        if ($unloadingOverrun > 0) {
            $extraCost += $unloadingOverrun * 5; // 5 TND per minute
        }

        return $extraCost;
    }

    private function sendInvoiceEmail(Transport $transport, array $invoice, OutputInterface $output): void
    {
        try {
            $etudiant = $transport->getReservation()->getEtudiant();
            $emailAddress = $etudiant->getEmail();

            if (!$emailAddress || !filter_var($emailAddress, FILTER_VALIDATE_EMAIL)) {
                throw new \RuntimeException('Invalid or missing email address');
            }

            $email = (new TemplatedEmail())
                ->from('studar21@gmail.com')
                ->to($emailAddress)
                ->subject('Facture Transport #' . $transport->getId())
                ->htmlTemplate('emails/billing.html.twig')
                ->context([
                    'transport' => $transport,
                    'etudiant' => $etudiant,
                    'invoice' => [
                        'number' => $invoice['number'],
                        'amount' => $transport->getTarif() + $transport->getExtraCost(),
                        'pdf_url' => $invoice['pdf_url'],
                        'date' => new \DateTime()
                    ]
                ]);

            $this->mailer->send($email);
            
        } catch (\Exception $e) {
            $output->writeln(sprintf(
                'Failed to send email for transport #%d: %s',
                $transport->getId(),
                $e->getMessage()
            ));
        }
    }
}