#!/bin/bash

# Forzar UTF-8 para evitar problemas con acentos
export LC_ALL=C.UTF-8
export LANG=C.UTF-8

# Script para sincronizar archivos desde main hacia el branch actual

SCRIPT_PATH="$(realpath "$0")"
SCRIPT_DIR="$(dirname "$SCRIPT_PATH")"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'

SOURCE_BRANCH="main"
SOURCE_REF="origin/main"

IGNORE_FILES=(
    "app/Models/User.php"
    "database/factories/UserFactory.php"
    "database/seeders/DatabaseSeeder.php"
    "database/seeders/RoleSeeder.php"
    "database/seeders/UserSeeder.php"
    "routes/api.php"
    "routes/web.php"
    "config/app.php"
    "config/database.php"
    ".env"
    "composer.json"
    "composer.lock"
    "mainsync.sh"
)

is_ignored_file() {
    local file="$1"
    for ignored in "${IGNORE_FILES[@]}"; do
        if [[ "$file" == "$ignored" ]]; then
            return 0
        fi
    done
    return 1
}

show_help() {
    echo -e "${BLUE}Uso: $(basename "$0") [branch-destino]${NC}"
    echo ""
    echo "Sincroniza archivos desde 'main' hacia el branch especificado"
}

if [[ "$1" == "--help" || "$1" == "-h" ]]; then
    show_help
    exit 0
fi

TARGET_BRANCH=${1:-$(git branch --show-current)}

echo -e "${CYAN}=== Sincronización desde Main ===${NC}"
echo -e "${YELLOW}Branch origen: $SOURCE_REF${NC}"
echo -e "${YELLOW}Branch destino: $TARGET_BRANCH${NC}"
echo ""

# Verificar repositorio
if ! git rev-parse --git-dir > /dev/null 2>&1; then
    echo -e "${RED}Error: No estás en un repositorio Git${NC}"
    exit 1
fi

# Chequeo de cambios sin commit
if [[ -n $(git status --porcelain) ]]; then
    echo -e "${RED}Error: Hay cambios sin commit en el repositorio.${NC}"
    echo ""
    echo -e "${YELLOW}Archivos modificados:${NC}"
    git status --short
    echo ""
    echo -e "${BLUE}Debes hacer commit o stash antes de ejecutar la sincronización.${NC}"
    exit 1
fi

CURRENT_BRANCH=$(git branch --show-current)

if [[ "$CURRENT_BRANCH" != "$TARGET_BRANCH" ]]; then
    echo -e "${YELLOW}Cambiando a branch: $TARGET_BRANCH${NC}"
    git checkout "$TARGET_BRANCH" || exit 1
fi

echo -e "${YELLOW}Actualizando referencias remotas...${NC}"
git fetch origin main

MODIFIED_FILES=()
NEW_FILES=()
UNCHANGED_FILES=()
IGNORED_FILES=()

echo -e "${BLUE}Analizando archivos en main...${NC}"

git ls-tree -r -z --name-only "$SOURCE_REF" |
while IFS= read -r -d '' file
do

    if is_ignored_file "$file"; then
        IGNORED_FILES+=("$file")
        echo -e "  ${CYAN}Ignorado: $file${NC}"
        continue
    fi

    if [[ -f "$file" ]]; then

        if ! git diff --quiet HEAD "$SOURCE_REF" -- "$file" 2>/dev/null; then
            MODIFIED_FILES+=("$file")
            echo -e "  ${YELLOW}Modificado: $file${NC}"
        else
            UNCHANGED_FILES+=("$file")
        fi

    else
        NEW_FILES+=("$file")
        echo -e "  ${GREEN}Nuevo: $file${NC}"
    fi

done

echo ""
echo -e "${CYAN}=== INICIANDO SINCRONIZACIÓN ===${NC}"

ERROR_COUNT=0

for file in "${MODIFIED_FILES[@]}"
do
    echo -e "${YELLOW}Sobreescribiendo: $file${NC}"
    git checkout "$SOURCE_REF" -- "$file" || ((ERROR_COUNT++))
    git add "$file"
done

for file in "${NEW_FILES[@]}"
do
    echo -e "${GREEN}Agregando: $file${NC}"

    dir=$(dirname "$file")
    if [[ "$dir" != "." ]]; then
        mkdir -p "$dir"
    fi

    git checkout "$SOURCE_REF" -- "$file" || ((ERROR_COUNT++))
    git add "$file"
done

if [[ $ERROR_COUNT -gt 0 ]]; then
    echo -e "${RED}Se encontraron errores durante la sincronización${NC}"
    exit 1
fi

echo ""
echo -e "${GREEN}✓ Sincronización completada${NC}"

git status --short

TOTAL=$((${#MODIFIED_FILES[@]} + ${#NEW_FILES[@]}))

if [[ $TOTAL -gt 0 ]]; then
    echo ""
    echo -e "${YELLOW}Para crear el commit:${NC}"
    echo -e "${CYAN}git commit -m \"Sync from main: $TOTAL files updated\"${NC}"
fi

echo ""
echo -e "${CYAN}🎉 Sincronización finalizada${NC}"
