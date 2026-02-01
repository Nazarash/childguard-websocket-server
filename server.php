<?php
require __DIR__ . '/vendor/autoload.php';

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use ChildGuard\StreamingServer;

// Load configuration - Railway sets PORT env variable
$port = getenv('PORT') ?: 8081;
if (file_exists(__DIR__ . '/config.php')) {
    $config = include(__DIR__ . '/config.php');
    $port = $config['port'] ?? $port;
}

echo "🚀 Starting WebSocket server on port {$port}...\n";
echo "📡 Ready to stream video/audio in real-time\n";

$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new StreamingServer()
        )
    ),
    $port
);

$server->run();
