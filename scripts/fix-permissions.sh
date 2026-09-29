#!/usr/bin/env bash

# Script para restaurar permisos de SQLite, storage y bootstrap/cache
# Compatible con ejecuciones en host, Lerd y dentro del Dev Container

set -e

# Detectar la raíz del proyecto
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
if [ -f "$SCRIPT_DIR/artisan" ]; then
    ROOT_DIR="$SCRIPT_DIR"
elif [ -f "$SCRIPT_DIR/../artisan" ]; then
    ROOT_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
else
    echo "Error: No se encontró la raíz del proyecto Laravel."
    exit 1
fi

cd "$ROOT_DIR"

echo "🔧 Restaurando permisos en: $ROOT_DIR"

# 1. Asegurar directorios de framework
mkdir -p database storage/framework/{cache,sessions,views} storage/logs bootstrap/cache

# 2. Permisos en el directorio database y archivos SQLite (sin modificar migraciones ni seeders)
chmod 777 database
if [ -f "database/database.sqlite" ]; then
    chmod 666 database/database.sqlite
fi
find database/ -maxdepth 1 -name "*.sqlite*" -exec chmod 666 {} + 2>/dev/null || true

# 3. Permisos en storage y bootstrap/cache
chmod -R a+rwX storage bootstrap/cache

# 4. Asignar ownership a usuario host (1000:1000) si es posible
chown 1000:1000 database 2>/dev/null || true
find database/ -maxdepth 1 -name "*.sqlite*" -exec chown 1000:1000 {} + 2>/dev/null || true
chown -R 1000:1000 storage bootstrap/cache 2>/dev/null || true

echo "✅ Permisos restaurados con éxito para Lerd y entorno local."
