<?php
// Script to kill process on port 8080 and free it

header('Content-Type: application/json');

$port = 8080;

// Find process using the port
$command = "lsof -ti:{$port} 2>/dev/null";
$pid = trim(shell_exec($command));

$result = [
    'success' => false,
    'message' => '',
    'port' => $port,
    'killed_pids' => []
];

if (empty($pid)) {
    // No process on port, check for server.php processes
    $phpCommand = "ps aux | grep '[s]erver.php' | awk '{print $2}'";
    $phpPids = shell_exec($phpCommand);
    
    if (empty(trim($phpPids))) {
        $result['success'] = true;
        $result['message'] = "Port {$port} is already free. No processes found.";
    } else {
        // Kill server.php processes
        $pids = explode("\n", trim($phpPids));
        foreach ($pids as $p) {
            if (!empty($p)) {
                shell_exec("kill -9 {$p} 2>/dev/null");
                $result['killed_pids'][] = $p;
            }
        }
        
        $result['success'] = true;
        $result['message'] = "Killed " . count($result['killed_pids']) . " server.php process(es)";
    }
} else {
    // Kill process on port
    shell_exec("kill -9 {$pid} 2>/dev/null");
    $result['killed_pids'][] = $pid;
    
    // Also kill any server.php processes
    $phpCommand = "ps aux | grep '[s]erver.php' | awk '{print $2}'";
    $phpPids = shell_exec($phpCommand);
    
    if (!empty(trim($phpPids))) {
        $pids = explode("\n", trim($phpPids));
        foreach ($pids as $p) {
            if (!empty($p) && $p != $pid) {
                shell_exec("kill -9 {$p} 2>/dev/null");
                $result['killed_pids'][] = $p;
            }
        }
    }
    
    $result['success'] = true;
    $result['message'] = "Port {$port} freed. Killed " . count($result['killed_pids']) . " process(es)";
}

// Remove PID file if exists
if (file_exists(__DIR__ . '/server.pid')) {
    unlink(__DIR__ . '/server.pid');
}

echo json_encode($result);
