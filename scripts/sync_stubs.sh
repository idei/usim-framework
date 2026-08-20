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
FILTER_SEARCH=""
CHECK_PATH=""

# --- Contadores de Estadísticas ---
TOTAL_SCANNED=0
TOTAL_PROCESSED=0
TOTAL_UPDATED=0
TOTAL_CREATED=0
TOTAL_SKIPPED=0
declare -A FEATURE_COUNTS
declare -A DIR_INHERITED_COUNTS
declare -A DIR_INHERITED_FEATS
declare -A DIR_INHERITED_TYPES

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
    echo -e "  ${GREEN}-s, --search <patrón>${NC}   Filtra la salida por coincidencia de texto en ruta de archivo"
    echo -e "                           (ej. TranslateManager, hero, permissions)."
    echo -e "  ${GREEN}-c, --check <ruta>${NC}       Diagnóstico puntual de inclusión de un archivo o carpeta específica."
    echo -e "  ${GREEN}-f, --feature <nombre>${NC}   Sincroniza solo los archivos de una feature específica"
    echo -e "                           (ej. core, auth, lang, settings)."
    echo -e "  ${GREEN}-t, --type <tipo>${NC}        Filtra por tipo de recurso"
    echo -e "                           (screen, component, service, controller, model,"
    echo -e "                            migration, seeder, factory, test, view, lang, asset, script)."
    echo -e "  ${GREEN}-n, --dry-run${NC}            Simula la ejecución mostrando los archivos que serían"
    echo -e "                           creados o modificados sin tocar el disco."
    echo -e "  ${GREEN}-l, --list-features${NC}      Escanea el proyecto y lista todas las features y recursos"
    echo -e "                           encontrados sin realizar la sincronización."
    echo -e "  ${GREEN}-v, --verbose${NC}            Muestra información detallada y desglose completo de cada archivo."
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
    echo -e "  ${CYAN}6. Directorios Completos (Sidecar de Directorio .usim-dir.meta):${NC}"
    echo -e "     Crear archivo ${YELLOW}.usim-dir.meta${NC} en la raíz de cualquier carpeta:"
    echo -e "     Ejemplo: ${BLUE}lang/es/.usim-dir.meta${NC} con contenido:"
    echo -e "     @usim: feature=\"core\", type=\"lang\", recursive=true, exclude=\"*.tmp,local_*\""
    echo "     (Aplica a todos los archivos del directorio sin modificar su código fuente)"
    echo ""
    echo -e "${BOLD}EJEMPLOS DE USO:${NC}"
    echo -e "  # Simular la sincronización (modo compacto)"
    echo -e "  ${YELLOW}./scripts/sync_stubs.sh --dry-run${NC}"
    echo ""
    echo -e "  # Buscar si un archivo o término entra en la sincronización"
    echo -e "  ${YELLOW}./scripts/sync_stubs.sh -n -s TranslateManager${NC}"
    echo ""
    echo -e "  # Diagnóstico puntual de un archivo o carpeta"
    echo -e "  ${YELLOW}./scripts/sync_stubs.sh --check app/UI/Screens/Admin/TranslateManager.php${NC}"
    echo -e "  ${YELLOW}./scripts/sync_stubs.sh --check lang/es${NC}"
    echo ""
    echo -e "  # Sincronizar únicamente la feature 'auth'"
    echo -e "  ${YELLOW}./scripts/sync_stubs.sh --feature auth${NC}"
    echo ""
    echo -e "  # Listar todas las features declaradas en el playground"
    echo -e "  ${YELLOW}./scripts/sync_stubs.sh --list-features${NC}"
    echo ""
    echo -e "  # Sincronización completa con desglose detallado archivo por archivo"
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
        -s|--search)
            if [[ -n "${2:-}" && ! "$2" =~ ^- ]]; then
                FILTER_SEARCH="$2"
                shift 2
            else
                echo -e "${RED}Error: --search requiere un término de búsqueda.${NC}"
                exit 1
            fi
            ;;
        -c|--check|--inspect)
            if [[ -n "${2:-}" && ! "$2" =~ ^- ]]; then
                CHECK_PATH="$2"
                shift 2
            else
                echo -e "${RED}Error: --check requiere una ruta de archivo o directorio.${NC}"
                exit 1
            fi
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

# Extrae la directiva @usim o @usim-stub de la primera porción de un archivo (optimizado en bash puro)
get_directive_line() {
    local file="$1"
    [ -f "$file" ] || return 0
    local count=0
    local line=""
    while IFS= read -r line || [[ -n "$line" ]]; do
        if [[ "$line" =~ (@usim:|@usim-stub:) ]]; then
            echo "$line"
            return 0
        fi
        count=$((count + 1))
        [[ $count -ge 20 ]] && break
    done < "$file" 2>/dev/null
    return 0
}

# Busca el archivo .usim-dir.meta en el directorio actual o en sus ancestros hasta $ROOT_DIR
find_ancestor_dir_meta() {
    local target_file="$1"
    local cur_dir
    cur_dir="$(dirname "$target_file")"
    while [[ -n "$cur_dir" && "$cur_dir" != "/" && "$cur_dir" != "." && "$cur_dir" != "\\" ]]; do
        if [ -f "$cur_dir/.usim-dir.meta" ]; then
            echo "$cur_dir/.usim-dir.meta"
            return 0
        fi
        if [[ "$cur_dir" == "$ROOT_DIR" || "$cur_dir" == "$ROOT_DIR/" ]]; then
            break
        fi
        local parent_dir
        parent_dir="$(dirname "$cur_dir")"
        [[ "$parent_dir" == "$cur_dir" ]] && break
        cur_dir="$parent_dir"
    done
    return 0
}

# Verifica si un archivo coincide con los patrones de exclusión de .usim-dir.meta
matches_exclude_pattern() {
    local filename="$1"
    local rel_to_dir="$2"
    local patterns="$3"
    
    [[ -z "$patterns" ]] && return 1

    # Normalizar separadores y quitar comillas/corchetes
    local clean_patterns="${patterns//,/ }"
    clean_patterns="${clean_patterns//\[/}"
    clean_patterns="${clean_patterns//\]/}"
    clean_patterns="${clean_patterns//\"/}"
    clean_patterns="${clean_patterns//\'/}"
    
    for pat in $clean_patterns; do
        [[ -z "$pat" ]] && continue
        # shellcheck disable=SC2053
        if [[ "$filename" == $pat || "$rel_to_dir" == $pat ]]; then
            return 0
        fi
    done
    return 1
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
            echo "screens/${rel}.stub"
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
            local rel="${src_rel#app/Http/Controllers/}"
            echo "controllers/${rel}.stub"
            ;;
        model)
            local rel="${src_rel#app/Models/}"
            echo "models/${rel}.stub"
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
# Diagnóstico e Inspección Puntual (--check / --inspect)
# ==============================================================================
inspect_path() {
    local raw_path="$1"
    local full_path=""
    if [[ "$raw_path" = /* || "$raw_path" =~ ^[a-zA-Z]: ]]; then
        full_path="$raw_path"
    else
        full_path="$ROOT_DIR/$raw_path"
    fi

    echo ""
    echo -e "${CYAN}${BOLD}==============================================================================${NC}"
    echo -e "${CYAN}${BOLD} DIAGNÓSTICO DE INCLUSIÓN DE STUBS                                            ${NC}"
    echo -e "${CYAN}${BOLD}==============================================================================${NC}"

    if [ ! -e "$full_path" ]; then
        echo -e "${RED}Error: La ruta especificada no existe:${NC} $raw_path"
        exit 1
    fi

    local rel_path="${full_path#$ROOT_DIR/}"

    # Caso A: Es un Directorio
    if [ -d "$full_path" ]; then
        echo -e "${YELLOW}Tipo de objetivo:${NC} Directorio"
        echo -e "${YELLOW}Ruta consultada:${NC}  $rel_path"
        echo ""

        local dir_meta_file="$full_path/.usim-dir.meta"
        if [ -f "$dir_meta_file" ]; then
            local dir_directive="$(get_directive_line "$dir_meta_file")"
            echo -e "${GREEN}✓ Posee archivo de metadatos de directorio:${NC} $rel_path/.usim-dir.meta"
            echo -e "  ${GRAY}Directiva:${NC} $dir_directive"
        else
            local ancestor_meta="$(find_ancestor_dir_meta "$full_path/dummy" || true)"
            if [[ -n "$ancestor_meta" && -f "$ancestor_meta" ]]; then
                echo -e "${BLUE}ℹ Hereda de ancestro:${NC} ${ancestor_meta#$ROOT_DIR/}"
                echo -e "  ${GRAY}Directiva:${NC} $(get_directive_line "$ancestor_meta")"
            else
                echo -e "${GRAY}ℹ No posee .usim-dir.meta directo ni ancestro.${NC}"
            fi
        fi

        echo ""
        echo -e "${BOLD}Archivos encontrados en este directorio:${NC}"
        printf "${BOLD}%-55s %-10s %-12s %-20s${NC}\n" "ARCHIVO" "FEATURE" "TIPO" "ESTADO"
        echo "------------------------------------------------------------------------------------------------"

        local count_matched=0
        local count_skipped=0
        while IFS= read -r -d '' f; do
            local base="$(basename "$f")"
            [[ "$base" == ".usim-dir.meta" ]] && continue
            local f_rel="${f#$ROOT_DIR/}"
            local directive="$(get_directive_line "$f")"
            local origin="Directiva individual"

            if [[ -z "$directive" ]]; then
                local f_ancestor="$(find_ancestor_dir_meta "$f" || true)"
                if [[ -n "$f_ancestor" && -f "$f_ancestor" ]]; then
                    directive="$(get_directive_line "$f_ancestor")"
                    origin="Heredado de ${f_ancestor#$ROOT_DIR/}"
                    local f_meta_dir="$(dirname "$f_ancestor")"
                    local f_rec="$(extract_meta_attr "$directive" "recursive")"
                    f_rec="${f_rec:-true}"
                    if [[ "$f_rec" == "false" && "$(dirname "$f")" != "$f_meta_dir" ]]; then
                        directive=""
                    fi
                    local f_exc="$(extract_meta_attr "$directive" "exclude")"
                    local f_rel_meta="${f#$f_meta_dir/}"
                    if matches_exclude_pattern "$base" "$f_rel_meta" "$f_exc"; then
                        directive=""
                        origin="Excluido por regla"
                    fi
                fi
            fi

            if [[ -n "$directive" ]]; then
                local f_feat="$(extract_meta_attr "$directive" "feature")"
                local f_type="$(extract_meta_attr "$directive" "type")"
                f_feat="${f_feat:-core}"
                f_type="${f_type:-$(infer_type_from_path "$f_rel")}"
                printf "%-55s %-10s %-12s ${GREEN}%-20s${NC}\n" "$f_rel" "$f_feat" "$f_type" "✓ Incluido"
                count_matched=$((count_matched + 1))
            else
                printf "%-55s %-10s %-12s ${GRAY}%-20s${NC}\n" "$f_rel" "-" "-" "✗ Omitido"
                count_skipped=$((count_skipped + 1))
            fi
        done < <(find "$full_path" -type f ! -name ".DS_Store" \( -name "*.php" -o -name "*.blade.php" -o -name "*.sh" -o -name "*.svg" -o -name "*.meta" -o -name "*.json" -o -name "*.png" -o -name "*.jpg" -o -name "*.jpeg" -o -name "*.webp" -o -name "*.gif" -o -name "*.ico" -o -name "*.mp3" -o -name "*.wav" -o -name "*.md" -o -name "*.stub" \) -print0 2>/dev/null)

        echo "------------------------------------------------------------------------------------------------"
        echo -e "Total incluidos: ${GREEN}${BOLD}$count_matched${NC} | Total omitidos: ${GRAY}$count_skipped${NC}"
        echo ""
        exit 0
    fi

    # Caso B: Es un Archivo Individual
    echo -e "${YELLOW}Tipo de objetivo:${NC} Archivo Individual"
    echo -e "${YELLOW}Ruta del archivo:${NC} $rel_path"
    echo ""

    local directive="$(get_directive_line "$full_path")"
    local origin="Directiva individual en cabecera"

    if [[ -z "$directive" ]]; then
        local ancestor_meta="$(find_ancestor_dir_meta "$full_path" || true)"
        if [[ -n "$ancestor_meta" && -f "$ancestor_meta" ]]; then
            directive="$(get_directive_line "$ancestor_meta")"
            origin="Heredado de sidecar: ${ancestor_meta#$ROOT_DIR/}"
            local meta_folder="$(dirname "$ancestor_meta")"
            local rec="$(extract_meta_attr "$directive" "recursive")"
            rec="${rec:-true}"
            if [[ "$rec" == "false" && "$(dirname "$full_path")" != "$meta_folder" ]]; then
                echo -e "Estado:          ${RED}✗ OMITIDO${NC}"
                echo -e "Motivo:          Directorio ancestro tiene recursive=false y el archivo está en subcarpeta."
                exit 0
            fi
            local exc="$(extract_meta_attr "$directive" "exclude")"
            local rel_to_meta="${full_path#$meta_folder/}"
            if matches_exclude_pattern "$(basename "$full_path")" "$rel_to_meta" "$exc"; then
                echo -e "Estado:          ${RED}✗ OMITIDO${NC}"
                echo -e "Motivo:          Coincide con patrón de exclusión (exclude=\"$exc\") en ${ancestor_meta#$ROOT_DIR/}"
                exit 0
            fi
        fi
    fi

    if [[ -z "$directive" ]]; then
        echo -e "Estado:          ${RED}✗ OMITIDO DE SINCRONIZACIÓN${NC}"
        echo -e "Motivo:          No contiene directiva @usim individual ni ancestro con .usim-dir.meta."
        echo ""
        echo -e "${BOLD}Cómo incluirlo:${NC}"
        echo -e "  Opción 1: Agrega ${YELLOW}// @usim: feature=\"core\", type=\"screen\"${NC} en la cabecera del archivo."
        echo -e "  Opción 2: Agrega un archivo ${YELLOW}.usim-dir.meta${NC} en su carpeta contenedora."
        echo ""
        exit 0
    fi

    local feature="$(extract_meta_attr "$directive" "feature")"
    local type="$(extract_meta_attr "$directive" "type")"
    local target="$(extract_meta_attr "$directive" "target")"
    local subpath="$(extract_meta_attr "$directive" "subpath")"
    local skip="$(extract_meta_attr "$directive" "skip")"

    feature="${feature:-core}"
    type="${type:-$(infer_type_from_path "$rel_path")}"

    local dest_rel=""
    if [[ -n "$target" ]]; then
        dest_rel="$target"
    else
        dest_rel="$(resolve_default_target "$rel_path" "$type" "$subpath")"
    fi
    local dest_full="$STUBS_DIR/$dest_rel"

    echo -e "Estado:          ${GREEN}✅ INCLUIDO EN SINCRONIZACIÓN${NC}"
    echo -e "Feature:         ${BOLD}${feature}${NC}"
    echo -e "Tipo:            ${BOLD}${type}${NC}"
    echo -e "Origen Regla:    $origin"
    echo -e "Destino Stub:    ${BLUE}packages/idei/usim/stubs/$dest_rel${NC}"
    if [ -f "$dest_full" ]; then
        echo -e "Estado Destino:  ${BLUE}Existe (será sincronizado / actualizado)${NC}"
    else
        echo -e "Estado Destino:  ${GREEN}Nuevo (será creado)${NC}"
    fi
    if [[ "$skip" == "true" ]]; then
        echo -e "Flag Skip:       ${YELLOW}Marcado como skip=true (se ignorará durante la ejecución)${NC}"
    fi
    echo ""
    echo -e "Directiva:       ${GRAY}$directive${NC}"
    echo ""
    exit 0
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
                content="$(echo "$content" | sed -E "s~//[[:space:]]*(@usim:|@usim-stub:).*~${meta_header}~g")"
            elif [[ "$content" == *"<?php"* ]]; then
                content="$(echo "$content" | sed -E "s~<\?php~<\?php\n\n${meta_header}~1")"
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
    local base_name="$(basename "$src_file")"

    # Ignorar el propio archivo de metadatos de directorio
    if [[ "$base_name" == ".usim-dir.meta" ]]; then
        return
    fi

    local is_meta_sidecar=false
    if [[ "$src_file" == *.meta ]]; then
        is_meta_sidecar=true
    fi

    TOTAL_SCANNED=$((TOTAL_SCANNED + 1))

    # 1. Leer directiva individual del archivo o de su sidecar
    local directive=""
    directive="$(get_directive_line "$src_file")"

    local inherited_from_dir=false
    local dir_meta_file=""

    # 2. Si no tiene directiva individual, buscar en ancestros un .usim-dir.meta
    if [[ -z "$directive" && "$is_meta_sidecar" = false ]]; then
        dir_meta_file="$(find_ancestor_dir_meta "$src_file" || true)"
        if [[ -n "$dir_meta_file" && -f "$dir_meta_file" ]]; then
            directive="$(get_directive_line "$dir_meta_file")"
            if [[ -n "$directive" ]]; then
                local dir_meta_folder="$(dirname "$dir_meta_file")"
                local dir_recursive="$(extract_meta_attr "$directive" "recursive")"
                dir_recursive="${dir_recursive:-true}"

                # Si no es recursivo y el archivo no está directamente en la carpeta del .meta, omitir
                if [[ "$dir_recursive" == "false" || "$dir_recursive" == "0" ]]; then
                    if [[ "$(dirname "$src_file")" != "$dir_meta_folder" ]]; then
                        return
                    fi
                fi

                # Verificar exclusiones
                local dir_exclude="$(extract_meta_attr "$directive" "exclude")"
                local rel_to_meta_dir="${src_file#$dir_meta_folder/}"
                if matches_exclude_pattern "$base_name" "$rel_to_meta_dir" "$dir_exclude"; then
                    if [ "$VERBOSE" = true ]; then
                        echo -e "  ${GRAY}[EXCLUDE] $rel_to_meta_dir (coincide con exclude en $dir_meta_file)${NC}"
                    fi
                    return
                fi

                inherited_from_dir=true
            fi
        fi
    fi

    # Si no tiene directiva individual ni heredada, omitir
    if [[ -z "$directive" ]]; then
        return
    fi

    local rel_src="${src_file#$ROOT_DIR/}"

    # 3. Extraer atributos de la directiva
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

    # 4. Determinar destino final en stubs
    local dest_rel=""
    if [[ -n "$target" ]]; then
        dest_rel="$target"
    else
        dest_rel="$(resolve_default_target "$rel_src" "$type" "$subpath")"
    fi

    # Aplicar filtro de búsqueda por texto (-s, --search)
    if [[ -n "$FILTER_SEARCH" ]]; then
        local search_lower="${FILTER_SEARCH,,}"
        local src_lower="${rel_src,,}"
        local dest_lower="${dest_rel,,}"
        if [[ "$src_lower" != *"$search_lower"* && "$dest_lower" != *"$search_lower"* ]]; then
            return
        fi
    fi

    local dest_full="$STUBS_DIR/$dest_rel"
    local is_new_file=false
    if [ ! -f "$dest_full" ]; then
        is_new_file=true
    fi

    # 5. Detectar si el archivo debe tratarse como binario
    local is_binary=false
    local binary_src=""
    if [ "$is_meta_sidecar" = true ]; then
        is_binary=true
        binary_src="${src_file%.meta}"
    elif [[ "$src_file" =~ \.(png|jpg|jpeg|webp|gif|ico|svg|mp3|wav|ogg|pdf|zip|tar|gz)$ ]]; then
        is_binary=true
        binary_src="$src_file"
    fi

    # 6. Procesar según sea binario o texto
    if [ "$is_binary" = true ]; then
        if [ ! -f "$binary_src" ]; then
            echo -e "  ${RED}[ERROR] Archivo binario compañero no encontrado: $binary_src${NC}"
            return
        fi

        local binary_rel="${binary_src#$ROOT_DIR/}"

        if [ "$DRY_RUN" = true ]; then
            if [ "$inherited_from_dir" = false ] || [ "$VERBOSE" = true ] || [ -n "$FILTER_SEARCH" ]; then
                echo -e "  ${CYAN}[DRY-RUN] Copiaría binario [${feature}|${type}]: ${YELLOW}$binary_rel${NC} -> ${BLUE}$dest_rel${NC}"
            fi
        else
            mkdir -p "$(dirname "$dest_full")"
            cp "$binary_src" "$dest_full"
            if [ "$inherited_from_dir" = false ] || [ "$VERBOSE" = true ] || [ -n "$FILTER_SEARCH" ]; then
                if [ "$is_new_file" = true ]; then
                    echo -e "  ${GREEN}[+] Creado asset [${feature}]:${NC} $dest_rel"
                else
                    echo -e "  ${BLUE}[✓] Actualizado asset [${feature}]:${NC} $dest_rel"
                fi
            fi
        fi

        if [ "$inherited_from_dir" = true ]; then
            DIR_INHERITED_COUNTS["$dir_meta_folder"]=$(( ${DIR_INHERITED_COUNTS["$dir_meta_folder"]:-0} + 1 ))
            DIR_INHERITED_FEATS["$dir_meta_folder"]="$feature"
            DIR_INHERITED_TYPES["$dir_meta_folder"]="$type"
        fi

        if [ "$is_new_file" = true ]; then
            TOTAL_CREATED=$((TOTAL_CREATED + 1))
        else
            TOTAL_UPDATED=$((TOTAL_UPDATED + 1))
        fi

        TOTAL_PROCESSED=$((TOTAL_PROCESSED + 1))
        return
    fi

    # Archivo de texto: aplicar transformaciones (perezoso: solo si va a escribir a disco o mostrar preview)
    local transformed_content=""
    if [ "$DRY_RUN" = false ] || [ "$VERBOSE" = true ]; then
        transformed_content="$(transform_content_to_stub "$src_file" "$type" "$feature" "$subpath")"
    fi

    if [ "$DRY_RUN" = true ]; then
        if [ "$inherited_from_dir" = false ] || [ "$VERBOSE" = true ] || [ -n "$FILTER_SEARCH" ]; then
            echo -e "  ${CYAN}[DRY-RUN] [${feature}|${type}] ${YELLOW}$rel_src${NC} -> ${BLUE}$dest_rel${NC}"
            if [ "$VERBOSE" = true ]; then
                echo -e "${GRAY}--- Vista previa cabecera ---${NC}"
                echo "$transformed_content" | head -n 10
                echo -e "${GRAY}----------------------------${NC}"
            fi
        fi
    else
        mkdir -p "$(dirname "$dest_full")"
        echo "$transformed_content" > "$dest_full"

        # Conservar permisos de ejecución para scripts shell
        if [[ "$type" == "script" || "$src_file" == *.sh ]]; then
            chmod +x "$dest_full" 2>/dev/null || true
        fi

        if [ "$inherited_from_dir" = false ] || [ "$VERBOSE" = true ] || [ -n "$FILTER_SEARCH" ]; then
            if [ "$is_new_file" = true ]; then
                echo -e "  ${GREEN}[+] Creado [${feature}|${type}]:${NC} $dest_rel ${GRAY}($rel_src)${NC}"
            else
                echo -e "  ${BLUE}[✓] Sincronizado [${feature}|${type}]:${NC} $dest_rel"
            fi
        fi
    fi

    if [ "$inherited_from_dir" = true ]; then
        DIR_INHERITED_COUNTS["$dir_meta_folder"]=$(( ${DIR_INHERITED_COUNTS["$dir_meta_folder"]:-0} + 1 ))
        DIR_INHERITED_FEATS["$dir_meta_folder"]="$feature"
        DIR_INHERITED_TYPES["$dir_meta_folder"]="$type"
    fi

    if [ "$is_new_file" = true ]; then
        TOTAL_CREATED=$((TOTAL_CREATED + 1))
    else
        TOTAL_UPDATED=$((TOTAL_UPDATED + 1))
    fi

    TOTAL_PROCESSED=$((TOTAL_PROCESSED + 1))
}

# ==============================================================================
# Flujo Principal de Ejecución
# ==============================================================================

# Si se solicitó diagnóstico puntual (--check / --inspect), ejecutarlo y salir
if [[ -n "$CHECK_PATH" ]]; then
    inspect_path "$CHECK_PATH"
fi

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
    echo -e "${BLUE}Filtro Feature:  ${BOLD}${FILTER_FEATURE}${NC}"
fi
if [[ -n "$FILTER_TYPE" ]]; then
    echo -e "${BLUE}Filtro Tipo:     ${BOLD}${FILTER_TYPE}${NC}"
fi
if [[ -n "$FILTER_SEARCH" ]]; then
    echo -e "${BLUE}Filtro Búsqueda: ${BOLD}${FILTER_SEARCH}${NC}"
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
    done < <(find "$full_scan_dir" -type f ! -name ".DS_Store" \( -name "*.php" -o -name "*.blade.php" -o -name "*.sh" -o -name "*.svg" -o -name "*.meta" -o -name "*.json" -o -name "*.png" -o -name "*.jpg" -o -name "*.jpeg" -o -name "*.webp" -o -name "*.gif" -o -name "*.ico" -o -name "*.mp3" -o -name "*.wav" -o -name "*.md" -o -name "*.stub" \) -print0 2>/dev/null)
done

# En modo compacto (sin -v y sin -s), mostrar resumen de directorios con herencia masiva
if [ "$VERBOSE" = false ] && [ -z "$FILTER_SEARCH" ] && [ "$LIST_FEATURES" = false ] && [ ${#DIR_INHERITED_COUNTS[@]} -gt 0 ]; then
    for folder in "${!DIR_INHERITED_COUNTS[@]}"; do
        local_f_rel="${folder#$ROOT_DIR/}"
        local_f_cnt="${DIR_INHERITED_COUNTS[$folder]}"
        local_f_feat="${DIR_INHERITED_FEATS[$folder]}"
        local_f_type="${DIR_INHERITED_TYPES[$folder]}"
        if [ "$DRY_RUN" = true ]; then
            echo -e "  ${CYAN}[DRY-RUN] [${local_f_feat}|${local_f_type}] 📁 ${YELLOW}${local_f_rel}/${NC} (${local_f_cnt} archivos incluidos vía .usim-dir.meta)"
        else
            echo -e "  ${GREEN}[✓] Sincronizado [${local_f_feat}|${local_f_type}]: 📁 ${local_f_rel}/${NC} (${local_f_cnt} archivos vía .usim-dir.meta)"
        fi
    done
fi

echo ""

# Si el modo fue listar features, mostrar la tabla de resumen
if [ "$LIST_FEATURES" = true ]; then
    echo -e "${CYAN}${BOLD}=== CATÁLOGO DE FEATURES ENCONTRADAS EN EL PLAYGROUND ===${NC}"
    printf "${BOLD}%-20s %-15s${NC}\n" "FEATURE" "TOTAL ARCHIVOS"
    echo "-----------------------------------------"
    total_feat_files=0
    for feat in "${!FEATURE_COUNTS[@]}"; do
        printf "%-20s %-15s\n" "$feat" "${FEATURE_COUNTS[$feat]}"
        total_feat_files=$((total_feat_files + FEATURE_COUNTS[$feat]))
    done
    echo "-----------------------------------------"
    echo -e "Total archivos con directivas @usim: ${BOLD}$total_feat_files${NC}"
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
