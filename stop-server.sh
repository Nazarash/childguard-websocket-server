#!/bin/bash

# Script to stop WebSocket server and free port 8080

echo "🛑 Stopping WebSocket server..."

# Find process using port 8080
PID=$(lsof -ti:8080 2>/dev/null)

if [ -z "$PID" ]; then
    echo "ℹ️  No process found on port 8080"
else
    echo "📍 Found process on port 8080: PID $PID"
    kill -9 $PID 2>/dev/null
    
    if [ $? -eq 0 ]; then
        echo "✅ Process $PID killed successfully"
    else
        echo "❌ Failed to kill process $PID"
    fi
fi

# Also check for server.php processes
PHP_PIDS=$(ps aux | grep '[s]erver.php' | awk '{print $2}')

if [ -z "$PHP_PIDS" ]; then
    echo "ℹ️  No server.php processes found"
else
    echo "📍 Found server.php processes: $PHP_PIDS"
    for pid in $PHP_PIDS; do
        kill -9 $pid 2>/dev/null
        echo "✅ Killed server.php process: $pid"
    done
fi

# Remove PID file if exists
if [ -f "server.pid" ]; then
    rm server.pid
    echo "🗑️  Removed server.pid file"
fi

echo "✅ Port 8080 is now free"
echo ""
echo "You can now start the server with: php server.php"
