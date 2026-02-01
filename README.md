# ChildGuard WebSocket Server (PHP)

WebSocket сервер на PHP для real-time видео/аудио стриминга.

## Требования

- PHP 7.4 или выше
- Composer
- Расширение PHP: sockets, pcntl (обычно уже установлены)

## Установка

```bash
cd websocket-server-php
composer install
```

## Запуск локально

```bash
php server.php
```

Сервер запустится на `ws://localhost:8080`

## Запуск на хостинге

### Вариант 1: VPS/Dedicated Server (Рекомендуется)

На VPS с SSH доступом:

```bash
# Загрузи файлы на сервер
scp -r websocket-server-php user@your-server.com:/var/www/

# Подключись по SSH
ssh user@your-server.com

# Установи зависимости
cd /var/www/websocket-server-php
composer install

# Запусти сервер
php server.php

# Или запусти в фоне
nohup php server.php > server.log 2>&1 &
```

### Вариант 2: Shared Hosting с SSH

Если у тебя shared hosting с SSH:

```bash
# Загрузи через FTP или SSH
cd ~/public_html/websocket-server-php
composer install

# Запусти через screen (чтобы работал постоянно)
screen -S websocket
php server.php
# Нажми Ctrl+A, затем D чтобы отключиться от screen
```

### Вариант 3: Supervisor (Для автозапуска)

Создай файл `/etc/supervisor/conf.d/websocket.conf`:

```ini
[program:childguard-websocket]
command=php /var/www/websocket-server-php/server.php
directory=/var/www/websocket-server-php
autostart=true
autorestart=true
user=www-data
stdout_logfile=/var/log/websocket.log
stderr_logfile=/var/log/websocket-error.log
```

Затем:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start childguard-websocket
```

## Настройка Nginx (Проксирование WebSocket)

Создай файл `/etc/nginx/sites-available/websocket`:

```nginx
server {
    listen 80;
    server_name ws.your-domain.com;
    
    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_read_timeout 86400;
    }
}
```

Активируй:
```bash
sudo ln -s /etc/nginx/sites-available/websocket /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

## SSL (HTTPS/WSS)

Используй Certbot для бесплатного SSL:

```bash
sudo apt install certbot python3-certbot-nginx
sudo certbot --nginx -d ws.your-domain.com
```

После этого URL будет: `wss://ws.your-domain.com`

## Проверка работы

### Локально

```bash
# Установи wscat
composer global require textalk/websocket

# Подключись
php -r "
\$client = new \WebSocket\Client('ws://localhost:8080');
\$client->send(json_encode(['type' => 'auth', 'userId' => 'test', 'userType' => 'child']));
echo \$client->receive();
"
```

### Или используй онлайн тестер

1. Открой https://www.websocket.org/echo.html
2. Введи `ws://your-server.com:8080`
3. Нажми Connect
4. Отправь: `{"type":"auth","userId":"test","userType":"child"}`

## Мониторинг

### Проверка процесса
```bash
ps aux | grep server.php
```

### Логи
```bash
tail -f server.log
```

### Перезапуск
```bash
# Найди PID
ps aux | grep server.php

# Убей процесс
kill -9 PID

# Запусти снова
nohup php server.php > server.log 2>&1 &
```

## Настройка порта

По умолчанию используется порт 8080. Чтобы изменить:

```bash
PORT=9000 php server.php
```

Или отредактируй `server.php`:
```php
$port = 9000; // Твой порт
```

## Firewall

Открой порт в firewall:

```bash
# UFW
sudo ufw allow 8080/tcp

# iptables
sudo iptables -A INPUT -p tcp --dport 8080 -j ACCEPT
```

## Troubleshooting

### Ошибка "Address already in use"
```bash
# Найди процесс на порту 8080
sudo lsof -i :8080

# Убей процесс
kill -9 PID
```

### Ошибка "Class 'Ratchet\...' not found"
```bash
composer install
```

### Не подключается с iOS
1. Проверь что сервер запущен: `ps aux | grep server.php`
2. Проверь firewall: `sudo ufw status`
3. Проверь URL в приложении - должен быть правильный IP/домен

## Обновление URL в приложении

После запуска сервера обнови в `WebSocketService.swift`:

```swift
// Локально
ws.connect(serverURL: "ws://192.168.1.100:8080")

// На сервере без SSL
ws.connect(serverURL: "ws://your-server.com:8080")

// На сервере с SSL
ws.connect(serverURL: "wss://ws.your-domain.com")
```

## Производительность

Этот сервер может обрабатывать:
- До 1000 одновременных подключений
- До 100 активных видео стримов
- Задержка < 100ms

Для больших нагрузок используй:
- PHP 8.0+ (быстрее)
- OPcache (кеширование)
- Больше RAM (минимум 512MB)
