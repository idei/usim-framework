#!/bin/bash
set -euo pipefail

# Production deploy script for Nginx + PHP-FPM.
# Includes git pull and Laravel/USIM post-deploy steps.

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m'

print_color() {
    local message=${1:-}
    local color=${2:-$NC}
    echo -e "${color}${message}${NC}"
}

show_help() {
    cat <<'EOF'
Usage: scripts/deploy_production_nginx.sh [options]

Options:
  -b <branch>          Branch to deploy (default: main)
  -r <remote>          Git remote (default: origin)
  -p <php_fpm_service> PHP-FPM service to reload (default: php8.2-fpm)
  -n                   Reload nginx at the end
  -m                   Run migrations with --force
  -u                   Publish usim-config with --force
  -s                   Skip composer install
  -h                   Show help

Examples:
  scripts/deploy_production_nginx.sh
  scripts/deploy_production_nginx.sh -b main -m -u -n
  scripts/deploy_production_nginx.sh -p php8.3-fpm -m
EOF
}

BRANCH='main'
REMOTE='origin'
PHP_FPM_SERVICE='php8.2-fpm'
RUN_MIGRATIONS=false
PUBLISH_USIM_CONFIG=false
RELOAD_NGINX=false
SKIP_COMPOSER=false

while getopts 'b:r:p:nmush' opt; do
    case "$opt" in
        b) BRANCH="$OPTARG" ;;
        r) REMOTE="$OPTARG" ;;
        p) PHP_FPM_SERVICE="$OPTARG" ;;
        n) RELOAD_NGINX=true ;;
        m) RUN_MIGRATIONS=true ;;
        u) PUBLISH_USIM_CONFIG=true ;;
        s) SKIP_COMPOSER=true ;;
        h)
            show_help
            exit 0
            ;;
        *)
            show_help
            exit 1
            ;;
    esac
done

if ! git rev-parse --git-dir >/dev/null 2>&1; then
    print_color 'Error: run this script from inside the git repository.' "$RED"
    exit 1
fi

ROOT_DIR=$(git rev-parse --show-toplevel)
cd "$ROOT_DIR"

print_color "Deploying branch '$BRANCH' from remote '$REMOTE'..." "$CYAN"

print_color 'Fetching latest changes...' "$CYAN"
git fetch "$REMOTE" --prune

git checkout "$BRANCH"
git pull "$REMOTE" "$BRANCH"

if [[ "$SKIP_COMPOSER" == false ]]; then
    print_color 'Installing PHP dependencies...' "$CYAN"
    composer install --no-dev --optimize-autoloader --no-interaction
else
    print_color 'Skipping composer install (-s).' "$YELLOW"
fi

print_color 'Refreshing package discovery...' "$CYAN"
php artisan package:discover --ansi

print_color 'Publishing USIM assets...' "$CYAN"
php artisan vendor:publish --tag=usim-assets --force

if [[ "$PUBLISH_USIM_CONFIG" == true ]]; then
    print_color 'Publishing USIM config...' "$CYAN"
    php artisan vendor:publish --tag=usim-config --force
fi

if [[ "$RUN_MIGRATIONS" == true ]]; then
    print_color 'Running migrations...' "$CYAN"
    php artisan migrate --force
fi

print_color 'Refreshing USIM manifest...' "$CYAN"
php artisan usim:discover

print_color 'Clearing and warming Laravel caches...' "$CYAN"
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

print_color "Reloading PHP-FPM service: $PHP_FPM_SERVICE" "$CYAN"
sudo systemctl reload "$PHP_FPM_SERVICE"

if [[ "$RELOAD_NGINX" == true ]]; then
    print_color 'Reloading nginx...' "$CYAN"
    sudo systemctl reload nginx
fi

print_color 'Deployment completed successfully.' "$GREEN"
