#!/usr/bin/env bash

set -e

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DEV_DIR="$ROOT_DIR/dev"
CACHE_DIR="$ROOT_DIR/.laravel-template"

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

rm -rf "$DEV_DIR"
cp -r "$CACHE_DIR" "$DEV_DIR"

cd "$DEV_DIR"

echo ""
echo "3) Configuring local USIM repository..."

composer config repositories.usim path ../packages/idei/usim

echo ""
echo "4) Installing USIM..."

composer require idei/usim:@dev

echo ""
echo "---------------------------------------"
echo "Dev environment ready"
echo "---------------------------------------"
echo ""
echo "Run:"
echo ""
echo "cd dev"
echo "php artisan serve"
echo ""
echo "Test installer with:"
echo ""
echo "php artisan usim:install"
