#!/bin/bash
clear

# Function to get a value from .env file (non-commented lines)
get_env_value() {
    local key="$1"
    grep "^$key=" .env 2>/dev/null | head -1 | cut -d'=' -f2- | sed 's/^"\(.*\)"$/\1/'
}

env_db=$(get_env_value "DB_CONNECTION")
env_app_url=$(get_env_value "APP_URL")

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
    php artisan usim:install --no-interaction || exit 1
    php artisan db:seed --no-interaction
fi

# Clear cache before starting the server
echo "Clearing cache..."
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Register UI Screens/Components
echo "Discovering UI Screens..."
php artisan usim:discover

# Start queue worker in background
echo "Starting queue worker in background..."
php artisan queue:work --queue=default,emails --tries=3 --timeout=90 --sleep=3 > storage/logs/queue-worker.log 2>&1 &
QUEUE_PID=$!
echo "Queue worker started with PID: $QUEUE_PID"

echo -e "\n✓ Opening application on $env_app_url"
if [ -n "$BROWSER" ]; then
    "$BROWSER" "$env_app_url" > /dev/null 2>&1 || true
elif grep -q Microsoft /proc/version 2>/dev/null || [ -n "$WSL_DISTRO_NAME" ]; then
    cmd.exe /c start "$env_app_url" > /dev/null 2>&1
elif command -v xdg-open > /dev/null; then
    xdg-open "$env_app_url" > /dev/null 2>&1 &
else
    echo "  → $env_app_url"
fi

# Function to cleanup on exit
cleanup() {
    echo ""
    echo "Stopping queue worker..."
    kill $QUEUE_PID 2>/dev/null
    exit 0
}

# Trap SIGINT (Ctrl+C) and SIGTERM
trap cleanup SIGINT SIGTERM

echo -e "\n\e[33m[ OK ] Entorno iniciado. Presiona Ctrl+C para detener el worker y salir...\e[0m"

# Mantiene el script activo indefinidamente
while true; do
    sleep 1
done