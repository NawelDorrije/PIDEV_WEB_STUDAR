<?php
namespace App\Command;

use App\Entity\Rendezvous;
use App\Repository\RendezvousRepository;
use App\Service\TwilioService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:send-rendezvous-reminders',
    description: 'Sends WhatsApp reminders for rendezvous 6 hours away'
)]
class SendRendezvousRemindersCommand extends Command
{
    private $rendezvousRepository;
    private $twilioService;
    private $entityManager;
    private $logger;
    private $timezone;
    private $baseUrl;

    public function __construct(
        RendezvousRepository $rendezvousRepository,
        TwilioService $twilioService,
        EntityManagerInterface $entityManager,
        LoggerInterface $logger,
        string $timezone = 'Africa/Tunis',
        string $baseUrl = 'https://yourapp.com'
    ) {
        $this->rendezvousRepository = $rendezvousRepository;
        $this->twilioService = $twilioService;
        $this->entityManager = $entityManager;
        $this->logger = $logger;
        $this->timezone = $timezone;
        $this->baseUrl = $baseUrl;
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $now = new \DateTime('now', new \DateTimeZone($this->timezone));
        $sixHoursLater = (clone $now)->modify('+6 hours');
        $timeRangeStart = (clone $sixHoursLater)->modify('-10 minutes');
        $timeRangeEnd = (clone $sixHoursLater)->modify('+10 minutes');

        $this->logger->info('Checking reminders at ' . $now->format('Y-m-d H:i:s') . ' for range ' . $timeRangeStart->format('H:i') . ' to ' . $timeRangeEnd->format('H:i'));

        $rendezvousList = $this->rendezvousRepository->createQueryBuilder('r')
            ->where('r.status = :status')
            ->andWhere('r.date = :date')
            ->andWhere('r.heure BETWEEN :startTime AND :endTime')
            ->setParameter('status', 'confirmée')
            ->setParameter('date', $sixHoursLater->format('Y-m-d'))
            ->setParameter('startTime', $timeRangeStart->format('H:i'))
            ->setParameter('endTime', $timeRangeEnd->format('H:i'))
            ->getQuery()
            ->getResult();

        $this->logger->info('Found ' . count($rendezvousList) . ' rendezvous to process');

        foreach ($rendezvousList as $rendezvous) {
            $logement = $this->entityManager->getRepository(\App\Entity\Logement::class)->find($rendezvous->getIdLogement());
            if (!$logement) {
                $this->logger->warning('Logement not found for rendezvous ID: ' . $rendezvous->getId());
                continue;
            }

            $utilisateurRepository = $this->entityManager->getRepository(\App\Entity\Utilisateur::class);
            $mapsUrl = "https://www.google.com/maps/search/?api=1&query=" . urlencode($logement->getAdresse());
            $logementUrl = $this->baseUrl . '/logement/' . $rendezvous->getIdLogement();

            // Format heure and date
            $heure = substr($rendezvous->getHeure(), 0, 5); // '09:20:00' -> '09:20'
            $date = $rendezvous->getDate() ? $rendezvous->getDate()->format('d/m/Y') : 'N/A';

            // Handle student reminder
            $etudiant = $rendezvous->getEtudiant();
            if ($etudiant) {
                $etudiant = $utilisateurRepository->find($etudiant->getCin());
                if ($etudiant && $etudiant->getNumTel()) {
                    $message = sprintf(
                        "Bonjour,\n\nRappel : Vous avez un rendez-vous pour visiter le logement situé à %s, le %s à %s.\nDétails du logement : %s\nPour vous rendre sur place, utilisez ce lien : %s\n\nCordialement,\nÉquipe Studar",
                        $logement->getAdresse(),
                        $date,
                        $heure,
                        $logementUrl,
                        $mapsUrl
                    );
                    try {
                        $this->twilioService->sendWhatsAppMessage($etudiant->getNumTel(), $message);
                        $this->logger->info('Reminder sent to student for rendezvous ID: ' . $rendezvous->getId());
                    } catch (\Exception $e) {
                        $this->logger->error('Failed to send student reminder: ' . $e->getMessage());
                    }
                } else {
                    $this->logger->warning('No valid student phone for rendezvous ID: ' . $rendezvous->getId());
                }
            }

            // Handle landlord reminder
            $proprietaire = $rendezvous->getProprietaire();
            if ($proprietaire) {
                $proprietaire = $utilisateurRepository->find($proprietaire->getCin());
                if ($proprietaire && $proprietaire->getNumTel()) {
                    $etudiantName = $etudiant ? trim($etudiant->getPrenom() . ' ' . $etudiant->getNom()) : 'Étudiant';
                    $message = sprintf(
                        "Bonjour,\n\nRappel : %s visitera le logement situé à %s, le %s à %s.\nPour vous rendre sur place, utilisez ce lien : %s\n\nCordialement,\nÉquipe Studar",
                        $etudiantName,
                        $logement->getAdresse(),
                        $date,
                        $heure,
                        $mapsUrl
                    );
                    try {
                        $this->twilioService->sendWhatsAppMessage($proprietaire->getNumTel(), $message);
                        $this->logger->info('Reminder sent to landlord for rendezvous ID: ' . $rendezvous->getId());
                    } catch (\Exception $e) {
                        $this->logger->error('Failed to send landlord reminder for rendezvous ID: ' . $rendezvous->getId() . ': ' . $e->getMessage());
                    }
                } else {
                    $this->logger->warning('No valid landlord phone for rendezvous ID: ' . $rendezvous->getId());
                }
            }
        }

        return Command::SUCCESS;
    }
}