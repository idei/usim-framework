#!/bin/bash

# ==============================================================================
# USIM Framework - Script de Instalación y Puesta a Punto Inicial
# ==============================================================================

set -e

echo "🚀 Iniciando instalación y configuración del proyecto USIM..."

# 1. Configuración del archivo de entorno (.env)
if [ ! -f .env ]; then
    echo "📄 Creando archivo .env a partir de .env.example..."
    cp .env.example .env
else
    echo "✅ Archivo .env ya existe."
fi

# 1.1 Asegurar que ROOT_PASSWORD no sea CHANGE_ME para evitar fallos en la sincronización
if grep -q '^ROOT_PASSWORD=["'\''\?CHANGE_ME["'\''\?$' .env || grep -q '^ROOT_PASSWORD=$' .env; then
    echo "🔑 Configurando ROOT_PASSWORD por defecto ('password')..."
    sed -i 's/^ROOT_PASSWORD=.*/ROOT_PASSWORD="password"/' .env
fi

# 2. Configurar SQLite si es la base de datos seleccionada
DB_CONN=$(grep "^DB_CONNECTION=" .env 2>/dev/null | head -1 | cut -d'=' -f2- | tr -d '"' || echo "sqlite")
if [ "$DB_CONN" = "sqlite" ] || [ -z "$DB_CONN" ]; then
    if [ ! -f database/database.sqlite ]; then
        echo "🗄️  Creando archivo database/database.sqlite..."
        touch database/database.sqlite
    fi
fi

# 3. Permisos de directorios de almacenamiento y cache
echo "🔒 Ajustando permisos en storage y bootstrap/cache..."
mkdir -p storage/framework/{sessions,views,cache} storage/logs bootstrap/cache
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

# 4. Instalación de dependencias PHP con Composer
echo "📦 Instalando dependencias PHP (composer install)..."
composer install --no-interaction

# 5. Generar APP_KEY de Laravel si no está definida
if grep -q "^APP_KEY=$" .env || grep -q "^APP_KEY=\"\"$" .env; then
    echo "🔑 Generando APP_KEY..."
    php artisan key:generate --force
else
    echo "✅ APP_KEY ya configurada."
fi

# 6. Crear enlace simbólico de storage
if [ ! -L public/storage ]; then
    echo "🔗 Vinculando directorio de storage (php artisan storage:link)..."
    php artisan storage:link || true
fi

# 7. Instalación de dependencias Frontend con NPM
if command -v npm &> /dev/null; then
    echo "📦 Instalando dependencias de Node.js (npm install)..."
    npm install --no-audit --no-fund
fi

# 8. Ejecutar migraciones y seeders
echo "🧱 Ejecutando migraciones y seeders de base de datos..."
php artisan migrate --force --seed

# 9. Descubrir pantallas y sincronizar componentes de USIM
echo "🔍 Descubriendo pantallas de USIM y sincronizando..."
php artisan usim:discover
php artisan usim:sync all

# 10. Limpieza final de cachés
echo "🧹 Limpiando cachés..."
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

echo ""
echo "=============================================================================="
echo "🎉 ¡Instalación completada con éxito!"
echo "Ahora puedes ejecutar:"
echo "   ./start.sh"
echo "Para levantar el servidor Octane y probar tus pantallas."
echo "=============================================================================="
