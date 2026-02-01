<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Загружаем конфигурацию
$config = file_exists(__DIR__ . '/config.php') ? include(__DIR__ . '/config.php') : [
    'port' => 8080,
    'host' => '0.0.0.0',
    'domain' => '',
    'pid_file' => __DIR__ . '/server.pid',
    'log_file' => __DIR__ . '/server.log'
];

// Функции управления сервером
function getServerStatus($config) {
    $pidFile = $config['pid_file'];
    
    if (!file_exists($pidFile)) {
        return ['running' => false, 'message' => 'Сервер не запущен'];
    }
    
    $pid = (int)file_get_contents($pidFile);
    
    if (!$pid) {
        return ['running' => false, 'message' => 'Сервер не запущен (некорректный PID)'];
    }
    
    // Проверяем процесс через ps
    $check = shell_exec("ps -p $pid 2>/dev/null");
    if (!$check || strpos($check, (string)$pid) === false) {
        return ['running' => false, 'message' => 'Сервер не запущен (PID файл устарел)'];
    }
    
    return ['running' => true, 'pid' => $pid, 'message' => 'Сервер работает'];
}

function startServer($config) {
    $status = getServerStatus($config);
    if ($status['running']) {
        return ['success' => false, 'message' => 'Сервер уже запущен'];
    }
    
    $command = sprintf(
        'nohup php %s/server.php > %s 2>&1 & echo $!',
        __DIR__,
        $config['log_file']
    );
    
    $pid = shell_exec($command);
    
    if ($pid && trim($pid)) {
        $pid = trim($pid);
        file_put_contents($config['pid_file'], $pid);
        sleep(2);
        
        // Проверяем через ps
        $check = shell_exec("ps -p $pid 2>/dev/null");
        if ($check && strpos($check, $pid) !== false) {
            return ['success' => true, 'message' => 'Сервер успешно запущен', 'pid' => $pid];
        }
    }
    
    return ['success' => false, 'message' => 'Не удалось запустить сервер'];
}

function stopServer($config) {
    $status = getServerStatus($config);
    
    if (!$status['running']) {
        return ['success' => false, 'message' => 'Сервер не запущен'];
    }
    
    $pid = $status['pid'];
    
    // Пытаемся остановить через kill
    shell_exec("kill $pid 2>/dev/null");
    sleep(1);
    
    // Проверяем что остановился
    $check = shell_exec("ps -p $pid 2>/dev/null");
    if (!$check || strpos($check, (string)$pid) === false) {
        unlink($config['pid_file']);
        return ['success' => true, 'message' => 'Сервер остановлен'];
    }
    
    // Принудительная остановка
    shell_exec("kill -9 $pid 2>/dev/null");
    sleep(1);
    unlink($config['pid_file']);
    return ['success' => true, 'message' => 'Сервер принудительно остановлен'];
    
    return ['success' => false, 'message' => 'Не удалось остановить сервер'];
}

function restartServer($config) {
    $stopResult = stopServer($config);
    sleep(1);
    return startServer($config);
}

function getServerLogs($config, $lines = 50) {
    $logFile = $config['log_file'];
    
    if (!file_exists($logFile)) {
        return 'Лог файл не найден';
    }
    
    $command = "tail -n {$lines} " . escapeshellarg($logFile);
    return shell_exec($command);
}

function getServerInfo() {
    return [
        'php_version' => PHP_VERSION,
        'server_ip' => $_SERVER['SERVER_ADDR'] ?? 'Unknown',
        'server_name' => $_SERVER['SERVER_NAME'] ?? 'Unknown',
        'memory_usage' => round(memory_get_usage() / 1024 / 1024, 2) . ' MB',
        'uptime' => shell_exec('uptime')
    ];
}

// Обработка AJAX запросов
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'start':
            echo json_encode(startServer($config));
            break;
        case 'stop':
            echo json_encode(stopServer($config));
            break;
        case 'restart':
            echo json_encode(restartServer($config));
            break;
        case 'status':
            echo json_encode(getServerStatus($config));
            break;
        case 'logs':
            $logs = getServerLogs($config);
            echo json_encode(['success' => true, 'logs' => $logs]);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Unknown action']);
    }
    exit;
}

$status = getServerStatus($config);
$serverInfo = getServerInfo();
$wsUrl = "ws://{$serverInfo['server_ip']}:{$config['port']}";
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ChildGuard WebSocket Server - Панель управления</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .header h1 { font-size: 28px; margin-bottom: 5px; }
        .header p { opacity: 0.9; }
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        .card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .card h2 {
            font-size: 18px;
            margin-bottom: 15px;
            color: #333;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }
        .status {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        .status.running {
            background: #d4edda;
            color: #155724;
        }
        .status.stopped {
            background: #f8d7da;
            color: #721c24;
        }
        .status-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        .status.running .status-dot { background: #28a745; }
        .status.stopped .status-dot { background: #dc3545; }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
            margin-right: 10px;
            margin-bottom: 10px;
        }
        .btn-primary {
            background: #667eea;
            color: white;
        }
        .btn-primary:hover { background: #5568d3; }
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        .btn-danger:hover { background: #c82333; }
        .btn-warning {
            background: #ffc107;
            color: #000;
        }
        .btn-warning:hover { background: #e0a800; }
        .btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        .info-row:last-child { border-bottom: none; }
        .info-label {
            font-weight: bold;
            color: #666;
        }
        .info-value {
            color: #333;
            font-family: 'Courier New', monospace;
        }
        .logs {
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 20px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            max-height: 400px;
            overflow-y: auto;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        .url-box {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #667eea;
            margin: 15px 0;
        }
        .url-box code {
            font-size: 16px;
            color: #667eea;
            font-weight: bold;
        }
        .copy-btn {
            background: #28a745;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            margin-left: 10px;
        }
        .copy-btn:hover { background: #218838; }
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        .alert-success { background: #d4edda; color: #155724; }
        .alert-error { background: #f8d7da; color: #721c24; }
        .loading {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚀 ChildGuard WebSocket Server</h1>
            <p>Панель управления и мониторинга</p>
        </div>
        
        <div class="grid">
            <div class="card">
                <h2>Статус сервера</h2>
                <div class="status <?= $status['running'] ? 'running' : 'stopped' ?>" id="server-status">
                    <div class="status-dot"></div>
                    <div>
                        <strong><?= $status['running'] ? 'Работает' : 'Остановлен' ?></strong>
                        <?php if ($status['running']): ?>
                            <br><small>PID: <?= $status['pid'] ?></small>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div id="alert-container"></div>
                
                <div>
                    <?php if ($status['running']): ?>
                        <button class="btn btn-danger" onclick="controlServer('stop')">⏹ Остановить</button>
                        <button class="btn btn-warning" onclick="controlServer('restart')">🔄 Перезапустить</button>
                    <?php else: ?>
                        <button class="btn btn-primary" onclick="controlServer('start')">▶ Запустить</button>
                    <?php endif; ?>
                    <button class="btn btn-primary" onclick="refreshStatus()">🔄 Обновить</button>
                    <button class="btn btn-warning" onclick="killPort()">🔓 Освободить порт 8080</button>
                </div>
            </div>
            
            <div class="card">
                <h2>WebSocket URL</h2>
                <div class="url-box">
                    <code id="ws-url"><?= $wsUrl ?></code>
                    <button class="copy-btn" onclick="copyUrl()">📋 Копировать</button>
                </div>
                <p style="color: #666; font-size: 14px; margin-top: 10px;">
                    Используйте этот URL в приложении для подключения к серверу.
                </p>
            </div>
            
            <div class="card">
                <h2>Информация о сервере</h2>
                <div class="info-row">
                    <span class="info-label">PHP версия:</span>
                    <span class="info-value"><?= $serverInfo['php_version'] ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">IP адрес:</span>
                    <span class="info-value"><?= $serverInfo['server_ip'] ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Порт:</span>
                    <span class="info-value"><?= $config['port'] ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Использование памяти:</span>
                    <span class="info-value"><?= $serverInfo['memory_usage'] ?></span>
                </div>
            </div>
        </div>
        
        <div class="card">
            <h2>Логи сервера <button class="btn btn-primary" style="float: right;" onclick="refreshLogs()">🔄 Обновить</button></h2>
            <div class="logs" id="server-logs">
                <?= htmlspecialchars(getServerLogs($config)) ?>
            </div>
        </div>
    </div>
    
    <script>
        function controlServer(action) {
            const alertContainer = document.getElementById('alert-container');
            const buttons = document.querySelectorAll('.btn');
            
            buttons.forEach(btn => btn.disabled = true);
            alertContainer.innerHTML = '<div class="alert alert-success"><span class="loading"></span> Выполнение...</div>';
            
            fetch('dashboard.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=' + action
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alertContainer.innerHTML = '<div class="alert alert-success">✓ ' + data.message + '</div>';
                    setTimeout(() => location.reload(), 1500);
                } else {
                    alertContainer.innerHTML = '<div class="alert alert-error">✗ ' + data.message + '</div>';
                    buttons.forEach(btn => btn.disabled = false);
                }
            })
            .catch(err => {
                alertContainer.innerHTML = '<div class="alert alert-error">✗ Ошибка: ' + err + '</div>';
                buttons.forEach(btn => btn.disabled = false);
            });
        }
        
        function refreshStatus() {
            fetch('dashboard.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=status'
            })
            .then(r => r.json())
            .then(data => {
                location.reload();
            });
        }
        
        function refreshLogs() {
            const logsDiv = document.getElementById('server-logs');
            logsDiv.innerHTML = '<span class="loading"></span> Загрузка...';
            
            fetch('dashboard.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=logs'
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    logsDiv.textContent = data.logs;
                    logsDiv.scrollTop = logsDiv.scrollHeight;
                }
            });
        }
        
        function killPort() {
            const alertContainer = document.getElementById('alert-container');
            alertContainer.innerHTML = '<div class="alert alert-success"><span class="loading"></span> Освобождаем порт 8080...</div>';
            
            fetch('kill-port.php')
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alertContainer.innerHTML = '<div class="alert alert-success">✓ ' + data.message + '</div>';
                    setTimeout(() => location.reload(), 2000);
                } else {
                    alertContainer.innerHTML = '<div class="alert alert-error">✗ ' + data.message + '</div>';
                }
            })
            .catch(err => {
                alertContainer.innerHTML = '<div class="alert alert-error">✗ Ошибка: ' + err + '</div>';
            });
        }
        
        function copyUrl() {
            const url = document.getElementById('ws-url').textContent;
            navigator.clipboard.writeText(url).then(() => {
                const btn = event.target;
                const originalText = btn.textContent;
                btn.textContent = '✓ Скопировано!';
                setTimeout(() => btn.textContent = originalText, 2000);
            });
        }
        
        // Автообновление логов каждые 5 секунд
        setInterval(refreshLogs, 5000);
    </script>
</body>
</html>
