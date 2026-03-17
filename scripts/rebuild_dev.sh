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
    composer create-project laravel/laravel:^12.0 "$CACHE_DIR" --prefer-dist
    cd "$CACHE_DIR"

    rm composer.lock

    # Install Pest
    composer require pestphp/pest --dev -w --no-interaction

    # Initialize Pest
    ./vendor/bin/pest --init --no-interaction

    # Now Install Octane in the cached template to speed up future installs
    composer require laravel/octane
    php artisan octane:install --server=roadrunner -n

    # Config the storagele symlink for the cached template
    php artisan storage:link
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

echo ""
echo "3) Configuring local USIM repository..."

composer config repositories.usim path ../packages/idei/usim
composer require idei/usim:@dev

echo ""
echo "4) Copying ./start.sh and ./env to $DEV_DIR directory..."
cp "$ROOT_DIR/start.sh" "$DEV_DIR/start.sh"
cp "$ROOT_DIR/.env" "$DEV_DIR/.env"

echo ""
echo "---------------------------------------"
echo "Dev environment ready"
echo "---------------------------------------"
echo ""
echo "Test installer with:"
echo "php artisan usim:install"
echo ""
echo "Run:"
echo "./start.sh -r"
