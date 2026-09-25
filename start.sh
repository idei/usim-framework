#!/bin/bash

clear

# Function to get a value from .env file (non-commented lines)
get_env_value() {
    local key="$1"
    grep "^$key=" .env 2>/dev/null | head -1 | cut -d'=' -f2- | sed 's/^"\(.*\)"$/\1/'
}

env_db=$(get_env_value "DB_CONNECTION")

# Determine available port (starting from 8000)
find_available_port() {
    local port=8000
    while netstat -tuln 2>/dev/null | grep -q ":$port " || ss -tuln 2>/dev/null | grep -q ":$port "; do
        port=$((port + 1))
    done
    echo $port
}

# Update APP_URL in .env to match the actual port
update_env_url() {
    local port="$1"
    local current_url
    current_url=$(get_env_value "APP_URL")
    local new_url="http://127.0.0.1:${port}"

    if [[ "$current_url" != "$new_url" ]]; then
        sed -i "s|^APP_URL=.*|APP_URL=${new_url}           # Cambiar si fuera necesario sin la \"/\" al final.|" .env
        echo "APP_URL actualizado a ${new_url}"
    fi
}

if [[ "$*" == *"-r"* ]]; then
    if [[ "$env_db" == "mysql" ]]; then
        # Get the .env DB values that do not start with '#'
        db=$(get_env_value "DB_DATABASE")
        user=$(get_env_value "DB_USERNAME")
        pass=$(get_env_value "DB_PASSWORD")

        echo "Removing Database: $db with $user privileges"
        mysql -u "$user" -p"$pass" -e "DROP DATABASE IF EXISTS $db; CREATE DATABASE $db;"
    fi

    if [[ "$env_db" == "sqlite" ]]; then
        # Remove the database
        rm -f database/database.sqlite
    fi

    php artisan migrate --force
    # php artisan usim:install --no-interaction || exit 1

    php artisan db:seed --no-interaction
    php artisan usim:sync
fi

# Find available port and update .env
SERVER_PORT=$(find_available_port)
update_env_url "$SERVER_PORT"

echo "Using port: $SERVER_PORT"

# Clear cache before starting the server
echo "Clearing cache..."
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Register UI Screens/Components
echo "Discovering UI Screens..."
php artisan usim:discover
php artisan usim:sync all

# Start queue worker in background
echo "Starting queue worker in background..."
php artisan queue:work --queue=default,emails --tries=3 --timeout=90 --sleep=3 > storage/logs/queue-worker.log 2>&1 &
QUEUE_PID=$!
echo "Queue worker started with PID: $QUEUE_PID"

# Function to cleanup on exit
cleanup() {
    echo ""
    echo "Stopping queue worker..."
    kill $QUEUE_PID 2>/dev/null
    exit 0
}

# Trap SIGINT (Ctrl+C) and SIGTERM
trap cleanup SIGINT SIGTERM

echo "Starting Octane server..."
php artisan octane:start --watch --host=0.0.0.0 --port=$SERVER_PORT
