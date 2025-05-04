<?php
namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class TestEmailCommand extends Command
{
    protected static $defaultName = 'app:test-email';
    private $mailer;

    public function __construct(MailerInterface $mailer)
    {
        parent::__construct();
        $this->mailer = $mailer;
    }

    protected function configure()
    {
        $this->setDescription('Sends a test email');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $email = (new Email())
        ->from('studar21@gmail.com')
        ->to('nourmougou2@gmail.com') // Replace with your email
            ->subject('Test Email from Symfony')
            ->text('This is a test email sent from Symfony Mailer!');

        $this->mailer->send($email);
        $output->writeln('Test email sent successfully!');

        return Command::SUCCESS;
    }
}