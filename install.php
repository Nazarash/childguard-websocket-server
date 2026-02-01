<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Конфигурация
define('APP_NAME', 'ChildGuard WebSocket Server');
define('APP_VERSION', '1.0.0');
define('MIN_PHP_VERSION', '7.4.0');

// Шаги установки
$steps = [
    1 => 'Проверка системы',
    2 => 'Установка Composer',
    3 => 'Установка зависимостей',
    4 => 'Настройка конфигурации',
    5 => 'Запуск сервера',
    6 => 'Завершение'
];

$currentStep = isset($_GET['step']) ? (int)$_GET['step'] : 1;

// Функции
function checkRequirements() {
    $requirements = [
        'PHP Version' => [
            'required' => MIN_PHP_VERSION,
            'current' => PHP_VERSION,
            'status' => version_compare(PHP_VERSION, MIN_PHP_VERSION, '>=')
        ],
        'PHP Sockets Extension' => [
            'required' => 'Enabled',
            'current' => extension_loaded('sockets') ? 'Enabled' : 'Disabled',
            'status' => extension_loaded('sockets')
        ],
        'PHP JSON Extension' => [
            'required' => 'Enabled',
            'current' => extension_loaded('json') ? 'Enabled' : 'Disabled',
            'status' => extension_loaded('json')
        ],
        'PHP cURL Extension' => [
            'required' => 'Enabled',
            'current' => extension_loaded('curl') ? 'Enabled' : 'Disabled',
            'status' => extension_loaded('curl')
        ],
        'exec() Function' => [
            'required' => 'Enabled',
            'current' => function_exists('exec') ? 'Enabled' : 'Disabled',
            'status' => function_exists('exec')
        ],
        'shell_exec() Function' => [
            'required' => 'Enabled',
            'current' => function_exists('shell_exec') ? 'Enabled' : 'Disabled',
            'status' => function_exists('shell_exec')
        ],
        'Writable Directory' => [
            'required' => 'Yes',
            'current' => is_writable(__DIR__) ? 'Yes' : 'No',
            'status' => is_writable(__DIR__)
        ]
    ];
    
    return $requirements;
}

function installComposer() {
    $composerPath = __DIR__ . '/composer.phar';
    
    if (file_exists($composerPath)) {
        return ['success' => true, 'message' => 'Composer уже установлен'];
    }
    
    // Устанавливаем переменные окружения
    putenv('HOME=' . __DIR__);
    putenv('COMPOSER_HOME=' . __DIR__ . '/.composer');
    
    // Создаём директорию для Composer
    if (!file_exists(__DIR__ . '/.composer')) {
        mkdir(__DIR__ . '/.composer', 0755, true);
    }
    
    // Скачиваем Composer
    $installerUrl = 'https://getcomposer.org/installer';
    $installer = file_get_contents($installerUrl);
    
    if ($installer === false) {
        return ['success' => false, 'message' => 'Не удалось скачать Composer installer'];
    }
    
    file_put_contents(__DIR__ . '/composer-setup.php', $installer);
    
    // Запускаем установку с переменными окружения
    $command = sprintf(
        'HOME=%s COMPOSER_HOME=%s php composer-setup.php 2>&1',
        escapeshellarg(__DIR__),
        escapeshellarg(__DIR__ . '/.composer')
    );
    
    exec($command, $output, $returnCode);
    
    unlink(__DIR__ . '/composer-setup.php');
    
    if ($returnCode === 0 && file_exists($composerPath)) {
        return ['success' => true, 'message' => 'Composer успешно установлен'];
    }
    
    return ['success' => false, 'message' => 'Ошибка установки Composer: ' . implode("\n", $output)];
}

function installDependencies() {
    $composerPath = __DIR__ . '/composer.phar';
    
    if (!file_exists($composerPath)) {
        return ['success' => false, 'message' => 'Composer не найден'];
    }
    
    // Устанавливаем переменные окружения
    putenv('HOME=' . __DIR__);
    putenv('COMPOSER_HOME=' . __DIR__ . '/.composer');
    
    // Запускаем composer install с переменными окружения
    $command = sprintf(
        'cd %s && HOME=%s COMPOSER_HOME=%s php composer.phar install --no-dev --no-interaction 2>&1',
        escapeshellarg(__DIR__),
        escapeshellarg(__DIR__),
        escapeshellarg(__DIR__ . '/.composer')
    );
    
    exec($command, $output, $returnCode);
    
    if ($returnCode === 0) {
        return ['success' => true, 'message' => 'Зависимости успешно установлены', 'output' => $output];
    }
    
    return ['success' => false, 'message' => 'Ошибка установки зависимостей: ' . implode("\n", $output)];
}

function saveConfig($data) {
    $config = "<?php\n";
    $config .= "// ChildGuard WebSocket Server Configuration\n";
    $config .= "// Generated: " . date('Y-m-d H:i:s') . "\n\n";
    $config .= "return [\n";
    $config .= "    'port' => " . (int)$data['port'] . ",\n";
    $config .= "    'host' => '" . addslashes($data['host']) . "',\n";
    $config .= "    'domain' => '" . addslashes($data['domain']) . "',\n";
    $config .= "    'auto_start' => " . ($data['auto_start'] ? 'true' : 'false') . ",\n";
    $config .= "    'log_file' => __DIR__ . '/server.log',\n";
    $config .= "    'pid_file' => __DIR__ . '/server.pid',\n";
    $config .= "];\n";
    
    return file_put_contents(__DIR__ . '/config.php', $config);
}

function startServer($port = 8080) {
    $pidFile = __DIR__ . '/server.pid';
    $logFile = __DIR__ . '/server.log';
    
    // Проверяем, не запущен ли уже
    if (file_exists($pidFile)) {
        $pid = (int)file_get_contents($pidFile);
        // Проверяем процесс через ps вместо posix_kill
        $check = shell_exec("ps -p $pid 2>/dev/null");
        if ($check && strpos($check, (string)$pid) !== false) {
            return ['success' => false, 'message' => 'Сервер уже запущен (PID: ' . $pid . ')'];
        }
    }
    
    // Запускаем сервер в фоне
    $command = sprintf(
        'nohup php %s/server.php > %s 2>&1 & echo $!',
        escapeshellarg(__DIR__),
        escapeshellarg($logFile)
    );
    
    $pid = shell_exec($command);
    
    if ($pid && trim($pid)) {
        $pid = trim($pid);
        file_put_contents($pidFile, $pid);
        sleep(3); // Даём время на запуск
        
        // Проверяем что запустился через ps
        $check = shell_exec("ps -p $pid 2>/dev/null");
        if ($check && strpos($check, $pid) !== false) {
            return ['success' => true, 'message' => 'Сервер успешно запущен (PID: ' . $pid . ')', 'pid' => $pid];
        }
        
        // Проверяем логи на наличие сообщения о запуске
        if (file_exists($logFile)) {
            $logs = file_get_contents($logFile);
            if (strpos($logs, 'WebSocket server running') !== false || strpos($logs, 'Starting WebSocket') !== false) {
                return ['success' => true, 'message' => 'Сервер запущен (PID: ' . $pid . ')', 'pid' => $pid];
            }
        }
    }
    
    // Пытаемся получить информацию об ошибке из логов
    $errorMsg = 'Не удалось запустить сервер';
    if (file_exists($logFile)) {
        $logs = file_get_contents($logFile);
        if ($logs) {
            $errorMsg .= '. Проверьте логи: ' . substr($logs, -200);
        }
    }
    
    return ['success' => false, 'message' => $errorMsg];
}

// Обработка POST запросов
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'install_composer') {
        $result = installComposer();
        echo json_encode($result);
        exit;
    }
    
    if ($action === 'install_dependencies') {
        $result = installDependencies();
        echo json_encode($result);
        exit;
    }
    
    if ($action === 'save_config') {
        $data = [
            'port' => $_POST['port'] ?? 8080,
            'host' => $_POST['host'] ?? '0.0.0.0',
            'domain' => $_POST['domain'] ?? '',
            'auto_start' => isset($_POST['auto_start'])
        ];
        
        if (saveConfig($data)) {
            echo json_encode(['success' => true, 'message' => 'Конфигурация сохранена']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Ошибка сохранения конфигурации']);
        }
        exit;
    }
    
    if ($action === 'start_server') {
        $port = $_POST['port'] ?? 8080;
        $result = startServer($port);
        echo json_encode($result);
        exit;
    }
}

$requirements = checkRequirements();
$allRequirementsMet = !in_array(false, array_column($requirements, 'status'));
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> - Установка</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            text-align: center;
        }
        .header h1 { font-size: 32px; margin-bottom: 10px; }
        .header p { opacity: 0.9; font-size: 16px; }
        .steps {
            display: flex;
            background: #f8f9fa;
            padding: 20px;
            overflow-x: auto;
        }
        .step {
            flex: 1;
            text-align: center;
            padding: 10px;
            position: relative;
        }
        .step:not(:last-child)::after {
            content: '→';
            position: absolute;
            right: -10px;
            top: 50%;
            transform: translateY(-50%);
            color: #ccc;
        }
        .step.active { color: #667eea; font-weight: bold; }
        .step.completed { color: #28a745; }
        .content {
            padding: 40px;
        }
        .requirement {
            display: flex;
            justify-content: space-between;
            padding: 15px;
            border-bottom: 1px solid #eee;
        }
        .requirement:last-child { border-bottom: none; }
        .status {
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: bold;
        }
        .status.success { background: #d4edda; color: #155724; }
        .status.error { background: #f8d7da; color: #721c24; }
        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 15px 40px;
            border-radius: 10px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: transform 0.2s;
        }
        .btn:hover { transform: translateY(-2px); }
        .btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #333;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
        }
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .progress {
            width: 100%;
            height: 30px;
            background: #f0f0f0;
            border-radius: 15px;
            overflow: hidden;
            margin: 20px 0;
        }
        .progress-bar {
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            transition: width 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .code-block {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #667eea;
            font-family: 'Courier New', monospace;
            margin: 20px 0;
            overflow-x: auto;
        }
        .success-icon {
            font-size: 80px;
            color: #28a745;
            text-align: center;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚀 <?= APP_NAME ?></h1>
            <p>Версия <?= APP_VERSION ?> - Автоматическая установка</p>
        </div>
        
        <div class="steps">
            <?php foreach ($steps as $num => $name): ?>
                <div class="step <?= $num === $currentStep ? 'active' : ($num < $currentStep ? 'completed' : '') ?>">
                    <?= $num ?>. <?= $name ?>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="content">
            <?php if ($currentStep === 1): ?>
                <h2>Шаг 1: Проверка системы</h2>
                <p style="margin: 20px 0; color: #666;">Проверяем соответствие вашего сервера требованиям...</p>
                
                <?php foreach ($requirements as $name => $req): ?>
                    <div class="requirement">
                        <div>
                            <strong><?= $name ?></strong><br>
                            <small style="color: #666;">Требуется: <?= $req['required'] ?> | Текущее: <?= $req['current'] ?></small>
                        </div>
                        <span class="status <?= $req['status'] ? 'success' : 'error' ?>">
                            <?= $req['status'] ? '✓ OK' : '✗ Ошибка' ?>
                        </span>
                    </div>
                <?php endforeach; ?>
                
                <?php if ($allRequirementsMet): ?>
                    <div class="alert alert-success" style="margin-top: 20px;">
                        ✓ Все требования выполнены! Можно продолжить установку.
                    </div>
                    <button class="btn" onclick="window.location.href='?step=2'">Продолжить →</button>
                <?php else: ?>
                    <div class="alert alert-error" style="margin-top: 20px;">
                        ✗ Некоторые требования не выполнены. Пожалуйста, исправьте ошибки и обновите страницу.
                    </div>
                    <button class="btn" onclick="location.reload()">Обновить</button>
                <?php endif; ?>
                
            <?php elseif ($currentStep === 2): ?>
                <h2>Шаг 2: Установка Composer</h2>
                <p style="margin: 20px 0; color: #666;">Composer - менеджер зависимостей для PHP. Необходим для установки библиотек.</p>
                
                <div id="composer-status"></div>
                
                <button class="btn" id="install-composer-btn" onclick="installComposer()">
                    Установить Composer
                </button>
                
                <script>
                function installComposer() {
                    const btn = document.getElementById('install-composer-btn');
                    const status = document.getElementById('composer-status');
                    
                    btn.disabled = true;
                    btn.innerHTML = '<span class="loading"></span> Установка...';
                    
                    fetch('install.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: 'action=install_composer'
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            status.innerHTML = '<div class="alert alert-success">' + data.message + '</div>';
                            setTimeout(() => window.location.href = '?step=3', 1500);
                        } else {
                            status.innerHTML = '<div class="alert alert-error">' + data.message + '</div>';
                            btn.disabled = false;
                            btn.innerHTML = 'Повторить попытку';
                        }
                    });
                }
                </script>
                
            <?php elseif ($currentStep === 3): ?>
                <h2>Шаг 3: Установка зависимостей</h2>
                <p style="margin: 20px 0; color: #666;">Устанавливаем необходимые библиотеки (Ratchet WebSocket)...</p>
                
                <div id="dependencies-status"></div>
                
                <button class="btn" id="install-deps-btn" onclick="installDependencies()">
                    Установить зависимости
                </button>
                
                <script>
                function installDependencies() {
                    const btn = document.getElementById('install-deps-btn');
                    const status = document.getElementById('dependencies-status');
                    
                    btn.disabled = true;
                    btn.innerHTML = '<span class="loading"></span> Установка... (может занять 1-2 минуты)';
                    
                    fetch('install.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: 'action=install_dependencies'
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            status.innerHTML = '<div class="alert alert-success">' + data.message + '</div>';
                            setTimeout(() => window.location.href = '?step=4', 1500);
                        } else {
                            status.innerHTML = '<div class="alert alert-error">' + data.message + '</div>';
                            btn.disabled = false;
                            btn.innerHTML = 'Повторить попытку';
                        }
                    });
                }
                </script>
                
            <?php elseif ($currentStep === 4): ?>
                <h2>Шаг 4: Настройка конфигурации</h2>
                <p style="margin: 20px 0; color: #666;">Укажите параметры WebSocket сервера...</p>
                
                <form id="config-form" onsubmit="saveConfig(event)">
                    <div class="form-group">
                        <label>Порт сервера:</label>
                        <input type="number" name="port" value="8080" required>
                        <small style="color: #666;">По умолчанию: 8080. Убедитесь что порт открыт в firewall.</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Хост:</label>
                        <input type="text" name="host" value="0.0.0.0" required>
                        <small style="color: #666;">0.0.0.0 - слушать на всех интерфейсах</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Домен (опционально):</label>
                        <input type="text" name="domain" placeholder="ws.your-domain.com">
                        <small style="color: #666;">Оставьте пустым если используете IP адрес</small>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="auto_start" checked>
                            Автоматически запустить сервер после установки
                        </label>
                    </div>
                    
                    <div id="config-status"></div>
                    
                    <button type="submit" class="btn">Сохранить и продолжить →</button>
                </form>
                
                <script>
                function saveConfig(e) {
                    e.preventDefault();
                    const form = e.target;
                    const formData = new FormData(form);
                    formData.append('action', 'save_config');
                    
                    const btn = form.querySelector('button');
                    btn.disabled = true;
                    btn.innerHTML = '<span class="loading"></span> Сохранение...';
                    
                    fetch('install.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            document.getElementById('config-status').innerHTML = 
                                '<div class="alert alert-success">' + data.message + '</div>';
                            setTimeout(() => window.location.href = '?step=5', 1500);
                        } else {
                            document.getElementById('config-status').innerHTML = 
                                '<div class="alert alert-error">' + data.message + '</div>';
                            btn.disabled = false;
                            btn.innerHTML = 'Сохранить и продолжить →';
                        }
                    });
                }
                </script>
                
            <?php elseif ($currentStep === 5): ?>
                <h2>Шаг 5: Запуск сервера</h2>
                <p style="margin: 20px 0; color: #666;">Запускаем WebSocket сервер...</p>
                
                <div id="server-status"></div>
                
                <button class="btn" id="start-server-btn" onclick="startServer()">
                    🚀 Запустить сервер
                </button>
                
                <script>
                function startServer() {
                    const btn = document.getElementById('start-server-btn');
                    const status = document.getElementById('server-status');
                    
                    btn.disabled = true;
                    btn.innerHTML = '<span class="loading"></span> Запуск...';
                    
                    fetch('install.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: 'action=start_server&port=8080'
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            status.innerHTML = '<div class="alert alert-success">' + data.message + '</div>';
                            setTimeout(() => window.location.href = '?step=6', 2000);
                        } else {
                            status.innerHTML = '<div class="alert alert-error">' + data.message + '</div>';
                            btn.disabled = false;
                            btn.innerHTML = 'Повторить попытку';
                        }
                    });
                }
                </script>
                
            <?php elseif ($currentStep === 6): ?>
                <div class="success-icon">✓</div>
                <h2 style="text-align: center; color: #28a745;">Установка завершена!</h2>
                <p style="text-align: center; margin: 20px 0; color: #666;">
                    WebSocket сервер успешно установлен и запущен.
                </p>
                
                <?php
                $serverIP = $_SERVER['SERVER_ADDR'] ?? 'YOUR_SERVER_IP';
                $config = file_exists(__DIR__ . '/config.php') ? include(__DIR__ . '/config.php') : ['port' => 8080];
                $wsUrl = "ws://{$serverIP}:{$config['port']}";
                ?>
                
                <div class="alert alert-info">
                    <strong>URL вашего WebSocket сервера:</strong>
                    <div class="code-block"><?= $wsUrl ?></div>
                </div>
                
                <h3>Следующие шаги:</h3>
                <ol style="line-height: 2; margin: 20px 0;">
                    <li>Скопируйте URL выше</li>
                    <li>Откройте <code>WebSocketService.swift</code> в Xcode</li>
                    <li>Замените URL на: <code><?= $wsUrl ?></code></li>
                    <li>Пересоберите приложение (Cmd+B)</li>
                    <li>Запустите на устройствах</li>
                </ol>
                
                <div style="text-align: center; margin-top: 30px;">
                    <button class="btn" onclick="window.location.href='dashboard.php'">
                        Открыть панель управления →
                    </button>
                </div>
                
                <div class="alert alert-info" style="margin-top: 20px;">
                    <strong>Важно:</strong> Убедитесь что порт <?= $config['port'] ?> открыт в firewall вашего сервера.
                    <div class="code-block" style="margin-top: 10px;">
                        sudo ufw allow <?= $config['port'] ?>/tcp
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
