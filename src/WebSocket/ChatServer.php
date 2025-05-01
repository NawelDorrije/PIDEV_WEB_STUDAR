<?php
namespace App\WebSocket;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Message;
use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;

class ChatServer implements MessageComponentInterface
{
    private $clients;
    private $entityManager;
    private $utilisateurRepository;

    public function __construct(EntityManagerInterface $entityManager, UtilisateurRepository $utilisateurRepository)
    {
        $this->clients = new \SplObjectStorage();
        $this->entityManager = $entityManager;
        $this->utilisateurRepository = $utilisateurRepository;
    }

    public function onOpen(ConnectionInterface $conn)
    {
        $this->clients->attach($conn);
        echo "New connection! ({$conn->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        $data = json_decode($msg, true);
        $senderCin = $data['senderCin'] ?? null;
        $receiverCin = $data['receiverCin'] ?? null;
        $content = $data['content'] ?? null;
        $timestamp = $data['timestamp'] ?? null;

        if (!$senderCin || !$receiverCin || !$content || !$timestamp) {
            echo "Invalid message data\n";
            return;
        }

        $sender = $this->utilisateurRepository->findOneBy(['cin' => $senderCin]);
        $receiver = $this->utilisateurRepository->findOneBy(['cin' => $receiverCin]);

        if (!$sender || !$receiver) {
            echo "Sender or receiver not found\n";
            return;
        }

        $message = new Message();
        $message->setSenderCin($sender);
        $message->setReceiverCin($receiver);
        $message->setContent($content);
        $message->setTimestamp(new \DateTime($timestamp));

        $this->entityManager->persist($message);
        $this->entityManager->flush();

        $messageData = [
            'senderCin' => $senderCin,
            'receiverCin' => $receiverCin,
            'content' => $content,
            'timestamp' => $message->getTimestamp()->format('d/m/Y H:i')
        ];

        echo "Broadcasting: " . json_encode($messageData) . "\n"; // Add this
        foreach ($this->clients as $client) {
            $client->send(json_encode($messageData));
        }

    
    }

    public function onClose(ConnectionInterface $conn)
    {
        $this->clients->detach($conn);
        echo "Connection closed! ({$conn->resourceId})\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        echo "An error occurred: {$e->getMessage()}\n";
        $conn->close();
    }
}