#!/usr/bin/env bash

set -e

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DEV_DIR="$ROOT_DIR/dev"
CACHE_DIR="$ROOT_DIR/.laravel-template"

clear

echo "---------------------------------------"
echo "USIM dev environment rebuild"
echo "---------------------------------------"

cd "$ROOT_DIR"

echo ""
echo "1) Preparing Laravel template..."

if [ ! -d "$CACHE_DIR" ]; then
    echo "Downloading Laravel template (first time only)..."
    composer create-project laravel/laravel "$CACHE_DIR" --prefer-dist
else
    echo "Laravel template already cached."
fi

echo ""
echo "2) Recreating dev environment..."
echo "Removing existing dev/ directory..."
rm -rf "$DEV_DIR"
echo "Copying Laravel template to dev/ directory..."
cp -r "$CACHE_DIR" "$DEV_DIR"

cd "$DEV_DIR"

# Acá un mensaje de confirmación para asegurar que los próximos pasos se ejecuten en el directorio correcto
echo "Current directory: $(pwd)"

echo ""
echo "3) Configuring local USIM repository..."

composer config repositories.usim path ../packages/idei/usim

echo ""
echo "4) Installing USIM..."

composer require idei/usim:@dev

echo ""
echo "Installing Octane..."
composer require laravel/octane
php artisan octane:install --server=roadrunner -n

echo ""
echo "5) Copying ./start.sh to dev/ directory..."
cp "$ROOT_DIR/start.sh" "$DEV_DIR/start.sh"

echo ""
echo "6) Copying ./.env to dev/ directory..."
cp "$ROOT_DIR/.env" "$DEV_DIR/.env"


echo ""
echo "---------------------------------------"
echo "Dev environment ready"
echo "---------------------------------------"
echo ""
echo "Run:"
echo ""
echo "./start.sh"
echo ""
echo "Test installer with:"
echo "php artisan usim:install"
