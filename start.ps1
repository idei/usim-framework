Clear-Host

# Function to get a value from .env file (non-commented lines)
function Get-EnvValue {
    param([string]$Key)
    if (Test-Path ".env") {
        # Busca la línea que empiece por la clave y captura el valor
        $line = Get-Content ".env" | Where-Object { $_ -match "^$Key=(.*)" } | Select-Object -First 1
        if ($line) {
            $value = $line.Substring($line.IndexOf('=') + 1)
            # Elimina comillas dobles al principio y al final si existen
            $value = $value -replace '^"(.*)"$', '$1'
            return $value
        }
    }
    return $null
}

$env = Get-EnvValue "DB_CONNECTION"

# Comprobar si se pasó el argumento -r
if ($args -contains "-r") {
    if ($env_db -eq "mysql") {
        $db = Get-EnvValue "DB_DATABASE"
        $user = Get-EnvValue "DB_USERNAME"
        $pass = Get-EnvValue "DB_PASSWORD"

        Write-Host "Removing Database: $db with $user privileges" -ForegroundColor Yellow
        # Llamada a mysql de Laragon
        & mysql -u "$user" "-p$pass" -e "DROP DATABASE IF EXISTS $db; CREATE DATABASE $db;"
    }

    if ($env_db -eq "sqlite") {
        $sqlitePath = "database/database.sqlite"
        if (Test-Path $sqlitePath) {
            Write-Host "Removing SQLite Database..." -ForegroundColor Yellow
            Remove-Item -Path $sqlitePath -Force
        }
    }

    Write-Host "Running migrations..." -ForegroundColor Cyan
    php artisan migrate --force
    
    Write-Host "Running usim:install..." -ForegroundColor Cyan
    php artisan usim:install --no-interaction
    if ($LASTEXITCODE -ne 0) { exit 1 }
    
    Write-Host "Running seeders..." -ForegroundColor Cyan
    php artisan db:seed --no-interaction
}

# Clear cache before starting the server
Write-Host "Clearing cache..." -ForegroundColor Cyan
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Register UI Screens/Components
Write-Host "Discovering UI Screens..." -ForegroundColor Cyan
php artisan usim:discover

# Start queue worker in background
Write-Host "Starting queue worker in background..." -ForegroundColor Cyan

$logPathOut = "storage/logs/queue-worker.log"
$logPathErr = "storage/logs/queue-worker-error.log"

# Ejecutamos el worker separando los logs de salida y error
$workerProcess = Start-Process -FilePath "php" -ArgumentList "artisan queue:work --queue=default,emails --tries=3 --sleep=3" -WindowStyle Hidden -RedirectStandardOutput $logPathOut -RedirectStandardError $logPathErr -PassThru

$QUEUE_PID = $workerProcess.Id
Write-Host "Queue worker started with PID: $QUEUE_PID" -ForegroundColor Green

# Trap SIGINT / SIGTERM equivalent in PowerShell
# Usamos un bloque try/finally para capturar la interrupción (Ctrl+C)
try {
    Write-Host "`n[ OK ] Entorno iniciado. Presiona Ctrl+C para detener el worker y salir..." -ForegroundColor Yellow

    # Open browser to the home page
    Start-Process "http://usim-framework.test"

    # Mantiene el script activo indefinidamente
    while ($true) {
        Start-Sleep -Seconds 1
    }
}
finally {
    # Esta sección (cleanup) se ejecuta automáticamente cuando el usuario presiona Ctrl+C
    Write-Host "`nStopping queue worker..." -ForegroundColor Yellow
    Stop-Process -Id $QUEUE_PID -ErrorAction SilentlyContinue
    Write-Host "Queue worker stopped." -ForegroundColor Green
    exit 0
}

