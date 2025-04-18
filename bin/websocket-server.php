<?php

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

// Load .env file
$dotenv = new Dotenv();
$dotenv->load(dirname(__DIR__) . '/.env');

try {
    // Boot the Symfony Kernel
    echo "Creating Kernel...\n";
    $kernel = new Kernel('dev', true); // 'dev' environment, debug true
    echo "Booting Kernel...\n";
    $kernel->boot();

    // Get the container from the Kernel
    $container = $kernel->getContainer();

    // Debug: Check if kernel.project_dir is available
    if ($container->hasParameter('kernel.project_dir')) {
        echo "kernel.project_dir: " . $container->getParameter('kernel.project_dir') . "\n";
    } else {
        echo "kernel.project_dir parameter is not set!\n";
    }

    // Debug: Check if the container has the ChatServer service
    if ($container->has('App\WebSocket\ChatServer')) {
        echo "ChatServer service is available.\n";
    } else {
        echo "ChatServer service is not available!\n";
    }

    // Start the WebSocket server
    $server = IoServer::factory(
        new HttpServer(
            new WsServer(
                $container->get('App\WebSocket\ChatServer')
            )
        ),
        8080
    );

    echo "Starting WebSocket server on ws://127.0.0.1:8080\n";
    $server->run();
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}