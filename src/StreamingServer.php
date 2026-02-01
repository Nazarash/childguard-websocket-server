<?php
namespace ChildGuard;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class StreamingServer implements MessageComponentInterface {
    protected $connections;
    protected $childConnections;
    protected $parentConnections;
    
    public function __construct() {
        $this->connections = new \SplObjectStorage;
        $this->childConnections = [];  // childId => connection
        $this->parentConnections = []; // childId => [parent connections]
    }
    
    public function onOpen(ConnectionInterface $conn) {
        $this->connections->attach($conn);
        echo "🔌 New connection: {$conn->resourceId}\n";
    }
    
    public function onMessage(ConnectionInterface $from, $msg) {
        $data = json_decode($msg, true);
        
        if (!$data || !isset($data['type'])) {
            return;
        }
        
        $type = $data['type'];
        
        // Authentication
        if ($type === 'auth') {
            $this->handleAuth($from, $data);
        }
        
        // Parent commands
        elseif ($type === 'start_camera' || $type === 'start_microphone') {
            $this->handleParentCommand($from, $data);
        }
        
        elseif ($type === 'stop_camera' || $type === 'stop_microphone') {
            $this->handleParentCommand($from, $data);
        }
        
        // Child streaming
        elseif ($type === 'video_chunk' || $type === 'audio_chunk') {
            $this->handleMediaChunk($from, $data);
        }
    }
    
    private function handleAuth(ConnectionInterface $conn, $data) {
        $userId = $data['userId'] ?? null;
        $userType = $data['userType'] ?? null;
        
        if (!$userId || !$userType) {
            $this->sendError($conn, 'Missing userId or userType');
            return;
        }
        
        $conn->userId = $userId;
        $conn->userType = $userType;
        
        if ($userType === 'child') {
            $this->childConnections[$userId] = $conn;
            echo "✅ Child authenticated: {$userId}\n";
            
            $this->sendMessage($conn, [
                'type' => 'auth_success',
                'message' => 'Child authenticated'
            ]);
        }
        
        elseif ($userType === 'parent') {
            $childId = $data['childId'] ?? null;
            if (!$childId) {
                $this->sendError($conn, 'Missing childId for parent');
                return;
            }
            
            $conn->childId = $childId;
            
            if (!isset($this->parentConnections[$childId])) {
                $this->parentConnections[$childId] = [];
            }
            $this->parentConnections[$childId][] = $conn;
            
            echo "✅ Parent authenticated: {$userId} for child: {$childId}\n";
            
            $this->sendMessage($conn, [
                'type' => 'auth_success',
                'message' => 'Parent authenticated'
            ]);
        }
    }
    
    private function handleParentCommand(ConnectionInterface $from, $data) {
        $childId = $data['childId'] ?? $from->childId ?? null;
        
        if (!$childId) {
            $this->sendError($from, 'Missing childId');
            return;
        }
        
        if (!isset($this->childConnections[$childId])) {
            $this->sendError($from, 'Child is offline');
            return;
        }
        
        $childConn = $this->childConnections[$childId];
        $type = $data['type'];
        
        echo "📹 Parent requesting {$type} for child: {$childId}\n";
        
        // Forward command to child
        $this->sendMessage($childConn, [
            'type' => $type,
            'parentId' => $from->userId ?? 'unknown'
        ]);
        
        // Confirm to parent
        $this->sendMessage($from, [
            'type' => 'command_sent',
            'message' => "{$type} command sent to child"
        ]);
    }
    
    private function handleMediaChunk(ConnectionInterface $from, $data) {
        $childId = $from->userId ?? null;
        
        if (!$childId || !isset($this->parentConnections[$childId])) {
            return;
        }
        
        $type = $data['type'];
        $chunkData = $data['data'] ?? '';
        
        $message = [
            'type' => $type,
            'data' => $chunkData,
            'timestamp' => time()
        ];
        
        $parentCount = 0;
        foreach ($this->parentConnections[$childId] as $parentConn) {
            $this->sendMessage($parentConn, $message);
            $parentCount++;
        }
        
        $dataSize = strlen($chunkData);
        echo "📦 Forwarded {$type} chunk ({$dataSize} bytes) to {$parentCount} parent(s)\n";
    }
    
    private function sendMessage(ConnectionInterface $conn, array $data) {
        $conn->send(json_encode($data));
    }
    
    private function sendError(ConnectionInterface $conn, string $message) {
        $this->sendMessage($conn, [
            'type' => 'error',
            'message' => $message
        ]);
    }
    
    public function onClose(ConnectionInterface $conn) {
        $this->connections->detach($conn);
        
        // Clean up child connection
        if (isset($conn->userId) && isset($this->childConnections[$conn->userId])) {
            unset($this->childConnections[$conn->userId]);
            echo "🔌 Child disconnected: {$conn->userId}\n";
        }
        
        // Clean up parent connection
        if (isset($conn->childId) && isset($this->parentConnections[$conn->childId])) {
            $this->parentConnections[$conn->childId] = array_filter(
                $this->parentConnections[$conn->childId],
                function($c) use ($conn) { return $c !== $conn; }
            );
            
            if (empty($this->parentConnections[$conn->childId])) {
                unset($this->parentConnections[$conn->childId]);
            }
            
            echo "🔌 Parent disconnected from child: {$conn->childId}\n";
        }
        
        echo "🔌 Connection closed: {$conn->resourceId}\n";
    }
    
    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "❌ Error: {$e->getMessage()}\n";
        $conn->close();
    }
}
