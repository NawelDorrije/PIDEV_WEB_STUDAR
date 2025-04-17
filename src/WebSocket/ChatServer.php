<?php
namespace App\WebSocket;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use SplObjectStorage;

class ChatServer implements MessageComponentInterface
{
    protected $clients;
    protected $userConnections; // Map user CIN to their connections

    public function __construct()
    {
        $this->clients = new SplObjectStorage();
        $this->userConnections = [];
    }

    public function onOpen(ConnectionInterface $conn)
    {
        $this->clients->attach($conn);
        echo "New connection! ({$conn->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        $data = json_decode($msg, true);

        if (isset($data['type']) && $data['type'] === 'register') {
            // Register the user's CIN to their connection
            $cin = $data['cin'];
            if (!isset($this->userConnections[$cin])) {
                $this->userConnections[$cin] = new SplObjectStorage();
            }
            $this->userConnections[$cin]->attach($from);
            $from->cin = $cin; // Store CIN on connection for later use
            echo "User {$cin} registered on connection {$from->resourceId}\n";
            return;
        }

        // Handle incoming message
        $senderCin = $data['senderCin'] ?? null;
        $receiverCin = $data['receiverCin'] ?? null;
        $content = $data['content'] ?? null;
        $timestamp = $data['timestamp'] ?? null;

        if ($senderCin && $receiverCin && $content && $timestamp) {
            // Broadcast message to sender and receiver
            $messageData = [
                'content' => $content,
                'timestamp' => $timestamp,
                'senderCin' => $senderCin,
                'receiverCin' => $receiverCin,
            ];

            // Send to sender
            if (isset($this->userConnections[$senderCin])) {
                foreach ($this->userConnections[$senderCin] as $client) {
                    $client->send(json_encode($messageData));
                }
            }

            // Send to receiver
            if (isset($this->userConnections[$receiverCin])) {
                foreach ($this->userConnections[$receiverCin] as $client) {
                    $client->send(json_encode($messageData));
                }
            }
        }
    }

    public function onClose(ConnectionInterface $conn)
    {
        if (isset($conn->cin) && isset($this->userConnections[$conn->cin])) {
            $this->userConnections[$conn->cin]->detach($conn);
            if ($this->userConnections[$conn->cin]->count() === 0) {
                unset($this->userConnections[$conn->cin]);
            }
        }
        $this->clients->detach($conn);
        echo "Connection closed! ({$conn->resourceId})\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        echo "An error occurred: {$e->getMessage()}\n";
        $conn->close();
    }
}