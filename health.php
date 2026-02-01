<?php
// Simple health check endpoint for Railway
header('Content-Type: application/json');
echo json_encode([
    'status' => 'ok',
    'timestamp' => time(),
    'service' => 'ChildGuard WebSocket Server'
]);
