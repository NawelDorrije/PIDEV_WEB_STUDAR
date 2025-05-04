<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use App\Service\TwilioService; 
use Psr\Log\LoggerInterface;


// src/Command/DebugTwilioTestCommand.php
#[AsCommand(name: 'debug:twilio-test')]
class DebugTwilioTestCommand extends Command
{
    public function __construct(
        private TwilioService $twilioService,
        private LoggerInterface $logger
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
{
    $testNumber = '+21654154300';
    
    try {
        $this->logger->info('Attempting to send test message to '.$testNumber);
        
        $this->twilioService->sendWhatsAppMessage(
            $testNumber,
            'Test message from Symfony at '.date('Y-m-d H:i:s')
        );
        
        $output->writeln('Message sent successfully');
        return Command::SUCCESS;
        
    } catch (\Exception $e) {
        $this->logger->error('Twilio error: '.$e->getMessage());
        $output->writeln('<error>Error: '.$e->getMessage().'</error>');
        return Command::FAILURE;
    }
}
}