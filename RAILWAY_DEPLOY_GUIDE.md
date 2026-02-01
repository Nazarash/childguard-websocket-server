# 🚀 Деплой WebSocket сервера на Railway

## 📋 Что нужно

1. **GitHub аккаунт** (бесплатный)
2. **Railway аккаунт** (бесплатный - railway.app)
3. **Файлы проекта** (уже готовы)

---

## 🔧 Шаг 1: Подготовка проекта

Все файлы уже созданы:
- ✅ `Dockerfile` - для сборки контейнера
- ✅ `railway.json` - конфигурация Railway
- ✅ `server.php` - обновлён для Railway
- ✅ `.dockerignore` - исключения для Docker

---

## 📤 Шаг 2: Загрузка на GitHub

### 2.1 Создай репозиторий на GitHub
1. Иди на [github.com](https://github.com)
2. Нажми **"New repository"**
3. Название: `childguard-websocket-server`
4. Сделай **Public**
5. Нажми **"Create repository"**

### 2.2 Загрузи файлы
В терминале:
```bash
cd /Users/nazarasuraliev/Desktop/ChildGuard/websocket-server-php

# Инициализация git
git init
git add .
git commit -m "Initial WebSocket server for Railway"

# Подключение к GitHub (замени USERNAME на свой)
git remote add origin https://github.com/USERNAME/childguard-websocket-server.git
git branch -M main
git push -u origin main
```

---

## 🚂 Шаг 3: Деплой на Railway

### 3.1 Регистрация
1. Иди на [railway.app](https://railway.app)
2. Нажми **"Login"**
3. Войди через **GitHub**

### 3.2 Создание проекта
1. Нажми **"New Project"**
2. Выбери **"Deploy from GitHub repo"**
3. Выбери репозиторий `childguard-websocket-server`
4. Нажми **"Deploy Now"**

### 3.3 Настройка
1. Railway автоматически:
   - Обнаружит `Dockerfile`
   - Соберёт контейнер
   - Запустит сервер
   - Выдаст публичный URL

2. Получи URL:
   - В панели Railway найди **"Domains"**
   - Скопируй URL (например: `https://childguard-websocket-server-production.up.railway.app`)

---

## 📱 Шаг 4: Обновление iOS приложения

Замени URL в `WebSocketService.swift`:
```swift
func connect(serverURL: String = "wss://твой-railway-url.up.railway.app") {
```

**Важно:** Используй `wss://` (не `ws://`) для HTTPS!

---

## ✅ Проверка

1. **Railway Dashboard** - сервер должен быть **"Running"**
2. **Логи** - должно быть:
   ```
   🚀 Starting WebSocket server on port 8080...
   📡 Ready to stream video/audio in real-time
   ```
3. **iOS приложение** - должно подключаться к серверу

---

## 🎯 Готово!

Теперь у тебя есть:
- ✅ Бесплатный WebSocket сервер на Railway
- ✅ Публичный HTTPS URL
- ✅ Автоматические деплои при push в GitHub
- ✅ Логи и мониторинг в Railway Dashboard

**Railway бесплатно даёт:**
- 500 часов в месяц
- 1GB RAM
- 1GB диск
- Публичный домен

Этого хватит для тестирования и разработки! 🚀
