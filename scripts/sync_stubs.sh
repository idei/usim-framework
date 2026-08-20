#!/usr/bin/env bash
# ==============================================================================
# USIM Framework - Stub Synchronization & Feature Packaging Engine
# ==============================================================================
# Escanea el Playground ("central production laboratory"), detecta directivas
# de metadatos (@usim), transforma código vivo en stubs parametrizados y los
# organiza por feature en packages/idei/usim/stubs/.
# ==============================================================================

set -eo pipefail

export LC_ALL=C.UTF-8
export LANG=C.UTF-8

SCRIPT_PATH="$(realpath "$0" 2>/dev/null || readlink -f "$0" 2>/dev/null || echo "$0")"
SCRIPT_DIR="$(dirname "$SCRIPT_PATH")"
ROOT_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
STUBS_DIR="$ROOT_DIR/packages/idei/usim/stubs"

# --- Paleta de Colores y Estilos ANSI ---
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
MAGENTA='\033[0;35m'
CYAN='\033[0;36m'
GRAY='\033[0;90m'
BOLD='\033[1m'
NC='\033[0m'

# --- Variables de Configuración / CLI Flags ---
DRY_RUN=false
VERBOSE=false
FORCE=false
LIST_FEATURES=false
FILTER_FEATURE=""
FILTER_TYPE=""

# --- Contadores de Estadísticas ---
TOTAL_SCANNED=0
TOTAL_PROCESSED=0
TOTAL_UPDATED=0
TOTAL_CREATED=0
TOTAL_SKIPPED=0
declare -A FEATURE_COUNTS

# ==============================================================================
# Ayuda y Documentación CLI
# ==============================================================================
show_help() {
    echo -e "${CYAN}${BOLD}==============================================================================${NC}"
    echo -e "${CYAN}${BOLD} USIM Framework - Sincronizador de Stubs y Empaquetador de Features           ${NC}"
    echo -e "${CYAN}${BOLD}==============================================================================${NC}"
    echo ""
    echo -e "${BOLD}USO:${NC}"
    echo -e "  $(basename "$0") [OPCIONES]"
    echo ""
    echo -e "${BOLD}DESCRIPCIÓN:${NC}"
    echo -e "  Escanea el Playground/Laboratorio central en busca de directivas de metadatos"
    echo -e "  (${YELLOW}@usim${NC} / ${YELLOW}@usim-stub${NC}), convierte el código vivo en stubs parametrizados"
    echo -e "  con placeholders ({{ namespace }}, {{ screensNamespace }}, etc.) y los"
    echo -e "  distribuye en ${BLUE}packages/idei/usim/stubs/${NC}."
    echo ""
    echo -e "${BOLD}OPCIONES:${NC}"
    echo -e "  ${GREEN}-f, --feature <nombre>${NC}   Sincroniza solo los archivos de una feature específica"
    echo -e "                           (ej. core, auth, lang, settings)."
    echo -e "  ${GREEN}-t, --type <tipo>${NC}        Filtra por tipo de recurso"
    echo -e "                           (screen, component, service, controller, model,"
    echo -e "                            migration, seeder, factory, test, view, lang, asset, script)."
    echo -e "  ${GREEN}-n, --dry-run${NC}            Simula la ejecución mostrando los archivos que serían"
    echo -e "                           creados o modificados sin tocar el disco."
    echo -e "  ${GREEN}-l, --list-features${NC}      Escanea el proyecto y lista todas las features y recursos"
    echo -e "                           encontrados sin realizar la sincronización."
    echo -e "  ${GREEN}-v, --verbose${NC}            Muestra información detallada de cada transformación."
    echo -e "  ${GREEN}--force${NC}                  Fuerza la sobrescritura de todos los stubs coincidentes."
    echo -e "  ${GREEN}-h, --help${NC}               Muestra este mensaje de ayuda detallado."
    echo ""
    echo -e "${BOLD}SINTAXIS DE DIRECTIVAS EN ARCHIVOS DEL PLAYGROUND:${NC}"
    echo ""
    echo -e "  ${CYAN}1. Código PHP (Screens, Services, Models, Controllers, Tests, Seeders):${NC}"
    echo -e "     // @usim: feature=\"auth\", type=\"screen\""
    echo -e "     // @usim: feature=\"auth\", type=\"screen\", subpath=\"Admin/UsersManager.php\", target=\"screens/Admin/Dashboard.php.stub\""
    echo ""
    echo -e "  ${CYAN}2. Vistas Blade (resources/views):${NC}"
    echo -e "     {{-- @usim: feature=\"core\", type=\"view\" --}}"
    echo ""
    echo -e "  ${CYAN}3. Scripts Bash (scripts/):${NC}"
    echo -e "     # @usim: feature=\"core\", type=\"script\""
    echo ""
    echo -e "  ${CYAN}4. Gráficos Vectoriales SVG (public/):${NC}"
    echo -e "     <!-- @usim: feature=\"core\", type=\"asset\" -->"
    echo ""
    echo -e "  ${CYAN}5. Medias Binarios (Imágenes PNG/JPG/WebP, Audios MP3/WAV):${NC}"
    echo -e "     Crear archivo sidecar hermano con extensión ${YELLOW}.meta${NC}:"
    echo -e "     Ejemplo: ${BLUE}public/images/logo.png.meta${NC} con contenido:"
    echo -e "     @usim: feature=\"core\", type=\"asset\", target=\"assets/core/images/logo.png\""
    echo ""
    echo -e "${BOLD}EJEMPLOS DE USO:${NC}"
    echo -e "  # Simular la sincronización de todo el proyecto"
    echo -e "  ${YELLOW}./scripts/sync_stubs.sh --dry-run${NC}"
    echo ""
    echo -e "  # Sincronizar únicamente la feature 'auth'"
    echo -e "  ${YELLOW}./scripts/sync_stubs.sh --feature auth${NC}"
    echo ""
    echo -e "  # Listar todas las features declaradas en el playground"
    echo -e "  ${YELLOW}./scripts/sync_stubs.sh --list-features${NC}"
    echo ""
    echo -e "  # Sincronización completa en modo detallado"
    echo -e "  ${YELLOW}./scripts/sync_stubs.sh -v${NC}"
    echo ""
}

# ==============================================================================
# Parseo de Argumentos de Línea de Comandos
# ==============================================================================
while [[ $# -gt 0 ]]; do
    case "$1" in
        -h|--help)
            show_help
            exit 0
            ;;
        -n|--dry-run)
            DRY_RUN=true
            shift
            ;;
        -v|--verbose)
            VERBOSE=true
            shift
            ;;
        -l|--list-features)
            LIST_FEATURES=true
            shift
            ;;
        --force)
            FORCE=true
            shift
            ;;
        -f|--feature)
            if [[ -n "${2:-}" && ! "$2" =~ ^- ]]; then
                FILTER_FEATURE="$2"
                shift 2
            else
                echo -e "${RED}Error: --feature requiere un nombre de feature.${NC}"
                exit 1
            fi
            ;;
        -t|--type)
            if [[ -n "${2:-}" && ! "$2" =~ ^- ]]; then
                FILTER_TYPE="$2"
                shift 2
            else
                echo -e "${RED}Error: --type requiere un tipo válido.${NC}"
                exit 1
            fi
            ;;
        *)
            echo -e "${RED}Opción desconocida: $1${NC}"
            echo -e "Usa ${YELLOW}$(basename "$0") --help${NC} para ver las opciones disponibles."
            exit 1
            ;;
    esac
done

# ==============================================================================
# Funciones Utilitarias de Parseo de Metadatos
# ==============================================================================

# Extrae el valor de un atributo de la directiva (ej. feature="auth" -> auth)
extract_meta_attr() {
    local line="$1"
    local attr="$2"
    local val=""

    if [[ "$line" =~ $attr=[\"\']([^\"\']+)[\"\'] ]]; then
        val="${BASH_REMATCH[1]}"
    fi
    echo "$val"
}

# Extrae la directiva @usim o @usim-stub de la primera porción de un archivo
get_directive_line() {
    local file="$1"
    # Buscar en las primeras 20 líneas del archivo
    head -n 20 "$file" 2>/dev/null | grep -E '(@usim:|@usim-stub:)' | head -n 1 || true
}

# Determina el tipo de recurso por defecto según la ubicación del archivo
infer_type_from_path() {
    local rel_path="$1"
    case "$rel_path" in
        app/UI/Screens/*)     echo "screen" ;;
        app/UI/Components/*)  echo "component" ;;
        app/Services/*)       echo "service" ;;
        app/Http/Controllers/*) echo "controller" ;;
        app/Models/*)         echo "model" ;;
        database/migrations/*)echo "migration" ;;
        database/seeders/*)   echo "seeder" ;;
        database/factories/*) echo "factory" ;;
        tests/*)              echo "test" ;;
        resources/views/*)    echo "view" ;;
        lang/*)               echo "lang" ;;
        scripts/*)            echo "script" ;;
        public/*)             echo "asset" ;;
        *)                    echo "generic" ;;
    esac
}

# Resuelve la ruta relativa destino en stubs/ según tipo y ruta origen
resolve_default_target() {
    local src_rel="$1"
    local type="$2"
    local subpath="$3"

    if [[ -n "$subpath" ]]; then
        case "$type" in
            screen)     echo "screens/${subpath%.stub}.stub" ;;
            component)  echo "components/${subpath%.stub}.stub" ;;
            service)    echo "services/${subpath%.stub}.stub" ;;
            controller) echo "controllers/${subpath%.stub}.stub" ;;
            model)      echo "models/${subpath%.stub}.stub" ;;
            migration)  echo "migrations/${subpath%.stub}.stub" ;;
            seeder)     echo "seeders/${subpath%.stub}.stub" ;;
            factory)    echo "factories/${subpath%.stub}.stub" ;;
            test)       echo "tests/${subpath%.stub}.stub" ;;
            view)       echo "views/$subpath" ;;
            lang)       echo "lang/$subpath" ;;
            script)     echo "scripts/${subpath%.stub}.stub" ;;
            asset)      echo "assets/$subpath" ;;
            *)          echo "$subpath" ;;
        esac
        return
    fi

    case "$type" in
        screen)
            local rel="${src_rel#app/UI/Screens/}"
            # Caso especial de alias para UsersManager -> Dashboard
            if [[ "$rel" == "Admin/UsersManager.php" ]]; then
                echo "screens/Admin/Dashboard.php.stub"
            else
                echo "screens/${rel}.stub"
            fi
            ;;
        component)
            local rel="${src_rel#app/UI/Components/}"
            echo "components/${rel}.stub"
            ;;
        service)
            local rel="${src_rel#app/Services/}"
            echo "services/${rel}.stub"
            ;;
        controller)
            local filename="$(basename "$src_rel")"
            echo "controllers/${filename}.stub"
            ;;
        model)
            local filename="$(basename "$src_rel")"
            echo "models/${filename}.stub"
            ;;
        migration)
            local filename="$(basename "$src_rel")"
            # Eliminar prefijo de timestamp: YYYY_MM_DD_HHMMSS_
            local clean_name="$(echo "$filename" | sed -E 's/^[0-9]{4}_[0-9]{2}_[0-9]{2}_[0-9]{6}_//')"
            echo "migrations/${clean_name}.stub"
            ;;
        seeder)
            local filename="$(basename "$src_rel")"
            echo "seeders/${filename}.stub"
            ;;
        factory)
            local filename="$(basename "$src_rel")"
            echo "factories/${filename}.stub"
            ;;
        test)
            local rel="${src_rel#tests/}"
            echo "tests/${rel}.stub"
            ;;
        view)
            local rel="${src_rel#resources/views/}"
            echo "views/$rel"
            ;;
        lang)
            local rel="${src_rel#lang/}"
            echo "lang/$rel"
            ;;
        script)
            local filename="$(basename "$src_rel")"
            echo "scripts/${filename}.stub"
            ;;
        asset)
            local rel="${src_rel#public/}"
            echo "assets/$rel"
            ;;
        *)
            echo "misc/$(basename "$src_rel").stub"
            ;;
    esac
}

# ==============================================================================
# Motor de Transformación de Placeholders
# ==============================================================================
transform_content_to_stub() {
    local src_file="$1"
    local type="$2"
    local feature="$3"
    local subpath="$4"

    local content
    content="$(cat "$src_file")"

    # 1. Transformaciones específicas según el tipo de recurso
    case "$type" in
        screen)
            # Reemplazar declaración de namespace
            content="$(echo "$content" | sed -E 's|^namespace App\\UI\\Screens(\\[a-zA-Z0-9_]+)*;|namespace {{ namespace }};|g')"
            # Reemplazar imports cruzados de Screens
            content="$(echo "$content" | sed -E 's|use App\\UI\\Screens\\|use {{ screensNamespace }}\\|g')"
            # Reemplazar imports de Components
            content="$(echo "$content" | sed -E 's|use App\\UI\\Components\\|use {{ componentsNamespace }}\\|g')"
            # Reemplazar imports de modelo User
            content="$(echo "$content" | sed -E 's|use App\\Models\\User;|use {{ userModel }};|g')"
            ;;

        component)
            # Reemplazar declaración de namespace de componentes
            content="$(echo "$content" | sed -E 's|^namespace App\\UI\\Components(\\[a-zA-Z0-9_]+)*;|namespace {{ componentsNamespace }};|g')"
            # Reemplazar imports de Components
            content="$(echo "$content" | sed -E 's|use App\\UI\\Components\\|use {{ componentsNamespace }}\\|g')"
            # Reemplazar imports cruzados de Screens
            content="$(echo "$content" | sed -E 's|use App\\UI\\Screens\\|use {{ screensNamespace }}\\|g')"
            # Reemplazar imports de modelo User
            content="$(echo "$content" | sed -E 's|use App\\Models\\User;|use {{ userModel }};|g')"
            ;;

        service)
            # Reemplazar declaración de namespace de servicios
            content="$(echo "$content" | sed -E 's|^namespace App\\Services(\\[a-zA-Z0-9_]+)*;|namespace {{ namespace }};|g')"
            # Reemplazar imports de modelo User
            content="$(echo "$content" | sed -E 's|use App\\Models\\User;|use {{ userModel }};|g')"
            ;;

        controller)
            # Reemplazar declaración de namespace de controladores
            content="$(echo "$content" | sed -E 's|^namespace App\\Http\\Controllers(\\[a-zA-Z0-9_]+)*;|namespace {{ namespace }};|g')"
            # Reemplazar imports de modelo User
            content="$(echo "$content" | sed -E 's|use App\\Models\\User;|use {{ userModel }};|g')"
            ;;

        model)
            # Reemplazar declaración de namespace del modelo
            content="$(echo "$content" | sed -E 's|^namespace App\\Models(\\[a-zA-Z0-9_]+)*;|namespace {{ namespace }};|g')"
            ;;

        test)
            # Reemplazar imports en tests
            content="$(echo "$content" | sed -E 's|use App\\Models\\User;|use {{ userModel }};|g')"
            content="$(echo "$content" | sed -E 's|use App\\UI\\Screens\\|use {{ screensNamespace }}\\|g')"
            content="$(echo "$content" | sed -E 's|use App\\UI\\Components\\|use {{ componentsNamespace }}\\|g')"
            ;;
    esac

    # 2. Normalizar la directiva @usim en la cabecera del stub resultante
    # para que sea auto-descriptivo cuando el instalador de PHP lo lea.
    local meta_header="// @usim: feature=\"${feature}\", type=\"${type}\""
    if [[ -n "$subpath" ]]; then
        meta_header="// @usim: feature=\"${feature}\", type=\"${type}\", subpath=\"${subpath}\""
    fi

    case "$type" in
        view)
            # Para vistas Blade, remover comentario @usim y dejar Blade limpio
            content="$(echo "$content" | sed -E '/\{\{--\s*(@usim:|@usim-stub:).*--\}\}/d')"
            ;;
        script)
            # Para scripts shell, poner directiva con #
            content="$(echo "$content" | sed -E '/^#\s*(@usim:|@usim-stub:)/d')"
            content="$(echo "$content" | sed -E "1a\\# @usim: feature=\"${feature}\", type=\"${type}\"" || echo "$content")"
            ;;
        *)
            # Para archivos PHP, actualizar directiva en la cabecera
            if echo "$content" | grep -qE '//\s*(@usim:|@usim-stub:)'; then
                content="$(echo "$content" | sed -E "s|//\s*(@usim:|@usim-stub:).*|${meta_header}|g")"
            elif [[ "$content" == *"<?php"* ]]; then
                content="$(echo "$content" | sed -E "s|<\?php|<\?php\n\n${meta_header}|1")"
            fi
            ;;
    esac

    echo "$content"
}

# ==============================================================================
# Función de Procesamiento de un Archivo Individual
# ==============================================================================
process_file() {
    local src_file="$1"
    local is_meta_sidecar=false

    if [[ "$src_file" == *.meta ]]; then
        is_meta_sidecar=true
    fi

    TOTAL_SCANNED=$((TOTAL_SCANNED + 1))

    # 1. Leer directiva
    local directive=""
    if [ "$is_meta_sidecar" = true ]; then
        directive="$(cat "$src_file" | head -n 5 | grep -E '(@usim:|@usim-stub:)' | head -n 1 || true)"
    else
        directive="$(get_directive_line "$src_file")"
    fi

    # Si no tiene directiva, omitir
    if [[ -z "$directive" ]]; then
        return
    fi

    local rel_src="$(realpath --relative-to="$ROOT_DIR" "$src_file")"

    # 2. Extraer atributos de la directiva
    local feature="$(extract_meta_attr "$directive" "feature")"
    local type="$(extract_meta_attr "$directive" "type")"
    local target="$(extract_meta_attr "$directive" "target")"
    local subpath="$(extract_meta_attr "$directive" "subpath")"
    local skip="$(extract_meta_attr "$directive" "skip")"

    # Valores por defecto
    feature="${feature:-core}"
    if [[ -z "$type" ]]; then
        type="$(infer_type_from_path "$rel_src")"
    fi

    # Si está marcado como skip, omitir
    if [[ "$skip" == "true" || "$skip" == "1" ]]; then
        if [ "$VERBOSE" = true ]; then
            echo -e "  ${GRAY}[SKIP] $rel_src (marcado como skip)${NC}"
        fi
        TOTAL_SKIPPED=$((TOTAL_SKIPPED + 1))
        return
    fi

    # Registrar en contadores de feature
    FEATURE_COUNTS["$feature"]=$(( ${FEATURE_COUNTS["$feature"]:-0} + 1 ))

    # Aplicar filtros si fueron pasados por CLI
    if [[ -n "$FILTER_FEATURE" && "$feature" != "$FILTER_FEATURE" ]]; then
        return
    fi
    if [[ -n "$FILTER_TYPE" && "$type" != "$FILTER_TYPE" ]]; then
        return
    fi

    # Si solo se solicitó listar features, no continuar con la sincronización
    if [ "$LIST_FEATURES" = true ]; then
        return
    fi

    # 3. Determinar destino final en stubs
    local dest_rel=""
    if [[ -n "$target" ]]; then
        dest_rel="$target"
    else
        dest_rel="$(resolve_default_target "$rel_src" "$type" "$subpath")"
    fi

    local dest_full="$STUBS_DIR/$dest_rel"
    local is_new_file=false
    if [ ! -f "$dest_full" ]; then
        is_new_file=true
    fi

    # 4. Procesar según sea binario (sidecar .meta) o texto
    if [ "$is_meta_sidecar" = true ]; then
        local binary_src="${src_file%.meta}"
        if [ ! -f "$binary_src" ]; then
            echo -e "  ${RED}[ERROR] Archivo binario compañero no encontrado: $binary_src${NC}"
            return
        fi

        local binary_rel="$(realpath --relative-to="$ROOT_DIR" "$binary_src")"

        if [ "$DRY_RUN" = true ]; then
            echo -e "  ${CYAN}[DRY-RUN] Copiaría binario [${feature}|${type}]: ${YELLOW}$binary_rel${NC} -> ${BLUE}$dest_rel${NC}"
        else
            mkdir -p "$(dirname "$dest_full")"
            cp "$binary_src" "$dest_full"
            if [ "$is_new_file" = true ]; then
                echo -e "  ${GREEN}[+] Creado asset [${feature}]:${NC} $dest_rel"
                TOTAL_CREATED=$((TOTAL_CREATED + 1))
            else
                echo -e "  ${BLUE}[✓] Actualizado asset [${feature}]:${NC} $dest_rel"
                TOTAL_UPDATED=$((TOTAL_UPDATED + 1))
            fi
        fi

        TOTAL_PROCESSED=$((TOTAL_PROCESSED + 1))
        return
    fi

    # Archivo de texto: aplicar transformaciones
    local transformed_content
    transformed_content="$(transform_content_to_stub "$src_file" "$type" "$feature" "$subpath")"

    if [ "$DRY_RUN" = true ]; then
        echo -e "  ${CYAN}[DRY-RUN] [${feature}|${type}] ${YELLOW}$rel_src${NC} -> ${BLUE}$dest_rel${NC}"
        if [ "$VERBOSE" = true ]; then
            echo -e "${GRAY}--- Vista previa cabecera ---${NC}"
            echo "$transformed_content" | head -n 10
            echo -e "${GRAY}----------------------------${NC}"
        fi
    else
        mkdir -p "$(dirname "$dest_full")"
        echo "$transformed_content" > "$dest_full"

        # Conservar permisos de ejecución para scripts shell
        if [[ "$type" == "script" || "$src_file" == *.sh ]]; then
            chmod +x "$dest_full" 2>/dev/null || true
        fi

        if [ "$is_new_file" = true ]; then
            echo -e "  ${GREEN}[+] Creado [${feature}|${type}]:${NC} $dest_rel ${GRAY}($rel_src)${NC}"
            TOTAL_CREATED=$((TOTAL_CREATED + 1))
        else
            echo -e "  ${BLUE}[✓] Sincronizado [${feature}|${type}]:${NC} $dest_rel"
            TOTAL_UPDATED=$((TOTAL_UPDATED + 1))
        fi
    fi

    TOTAL_PROCESSED=$((TOTAL_PROCESSED + 1))
}

# ==============================================================================
# Flujo Principal de Ejecución
# ==============================================================================

echo ""
echo -e "${CYAN}${BOLD}==============================================================================${NC}"
echo -e "${CYAN}${BOLD}   USIM Framework - Sincronizador de Stubs y Features                         ${NC}"
echo -e "${CYAN}${BOLD}==============================================================================${NC}"
echo -e "${YELLOW}Playground Root:${NC} $ROOT_DIR"
echo -e "${YELLOW}Stubs Target:   ${NC} $STUBS_DIR"

if [ "$DRY_RUN" = true ]; then
    echo -e "${MAGENTA}${BOLD}MODO: DRY-RUN (Simulación sin escritura en disco)${NC}"
fi
if [[ -n "$FILTER_FEATURE" ]]; then
    echo -e "${BLUE}Filtro Feature: ${BOLD}${FILTER_FEATURE}${NC}"
fi
if [[ -n "$FILTER_TYPE" ]]; then
    echo -e "${BLUE}Filtro Tipo:    ${BOLD}${FILTER_TYPE}${NC}"
fi
echo ""

# Escanear directorios clave del monorepo
SCAN_DIRS=(
    "app/UI"
    "app/Services"
    "app/Http/Controllers"
    "app/Models"
    "database/migrations"
    "database/seeders"
    "database/factories"
    "tests"
    "resources/views"
    "lang"
    "scripts"
    "public"
)

echo -e "${BLUE}🔍 Escaneando archivos con directivas @usim...${NC}"

for dir in "${SCAN_DIRS[@]}"; do
    full_scan_dir="$ROOT_DIR/$dir"
    [ -d "$full_scan_dir" ] || continue

    while IFS= read -r -d '' file; do
        process_file "$file"
    done < <(find "$full_scan_dir" -type f \( -name "*.php" -o -name "*.blade.php" -o -name "*.sh" -o -name "*.svg" -o -name "*.meta" \) -print0 2>/dev/null)
done

echo ""

# Si el modo fue listar features, mostrar la tabla de resumen
if [ "$LIST_FEATURES" = true ]; then
    echo -e "${CYAN}${BOLD}=== CATÁLOGO DE FEATURES ENCONTRADAS EN EL PLAYGROUND ===${NC}"
    printf "${BOLD}%-20s %-15s${NC}\n" "FEATURE" "TOTAL ARCHIVOS"
    echo "-----------------------------------------"
    for feat in "${!FEATURE_COUNTS[@]}"; do
        printf "%-20s %-15s\n" "$feat" "${FEATURE_COUNTS[$feat]}"
    done
    echo "-----------------------------------------"
    echo -e "Total archivos con directivas @usim: ${BOLD}$TOTAL_PROCESSED${NC}"
    exit 0
fi

# ==============================================================================
# Resumen Final
# ==============================================================================
echo -e "${CYAN}${BOLD}=== RESUMEN DE SINCRONIZACIÓN ===${NC}"
echo -e "  Archivos analizados:   ${BOLD}$TOTAL_SCANNED${NC}"
echo -e "  Stubs procesados:      ${GREEN}${BOLD}$TOTAL_PROCESSED${NC}"
if [ "$DRY_RUN" = false ]; then
    echo -e "  Stubs creados nuevos:  ${GREEN}${BOLD}$TOTAL_CREATED${NC}"
    echo -e "  Stubs actualizados:    ${BLUE}${BOLD}$TOTAL_UPDATED${NC}"
fi
if [ "$TOTAL_SKIPPED" -gt 0 ]; then
    echo -e "  Stubs omitidos (skip): ${GRAY}$TOTAL_SKIPPED${NC}"
fi
echo ""

if [ "$TOTAL_PROCESSED" -eq 0 ]; then
    echo -e "${YELLOW}ℹ️  No se encontraron archivos con directivas @usim para procesar.${NC}"
    echo -e "Agrega ${YELLOW}// @usim: feature=\"core\", type=\"screen\"${NC} en la cabecera de tus archivos del playground."
else
    if [ "$DRY_RUN" = true ]; then
        echo -e "${GREEN}✓ Simulación completada con éxito. Ejecuta sin --dry-run para aplicar los cambios.${NC}"
    else
        echo -e "${GREEN}🎉 Sincronización completada exitosamente.${NC}"
    fi
fi
echo ""
