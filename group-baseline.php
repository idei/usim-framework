<?php
/**
 * Analiza el proyecto con PHPStan, regenera el baseline, muestra un resumen
 * general de todos los errores por archivo y genera el prompt con los N
 * errores más prioritarios del proyecto.
 *
 * Uso:
 *   php group-baseline.php
 *       -> Toma por defecto 10 errores
 *
 *   php group-baseline.php 5
 *       -> Toma máximo 5 errores
 *
 *   php group-baseline.php 15
 *       -> Toma máximo 15 errores
 */

const BASELINE_PATH = 'phpstan-9-baseline.neon';

// Permite pasar la cantidad máxima de errores por consola (por defecto 10)
$maxErrors = isset($argv[1]) && is_numeric($argv[1]) ? max(1, (int) $argv[1]) : 10;

echo "Generando baseline con PHPStan (nivel 9)...\n";
$command = sprintf(
    'vendor/bin/phpstan analyze --level 9 --generate-baseline %s 2>&1',
    escapeshellarg(BASELINE_PATH)
);
passthru($command, $exitCode);
echo "\n";

if (!file_exists(BASELINE_PATH)) {
    fwrite(STDERR, "PHPStan no generó el baseline en: " . BASELINE_PATH . " (código de salida: $exitCode)\n");
    exit(1);
}

$content = file_get_contents(BASELINE_PATH);
if ($content === false) {
    fwrite(STDERR, "No se pudo leer: " . BASELINE_PATH . "\n");
    exit(1);
}

// Extraer entradas del baseline
preg_match_all(
    '/-\s*\n\s*message:\s*(?P<message>.+?)\n\s*identifier:\s*(?P<identifier>[^\n]+)\n\s*count:\s*(?P<count>\d+)\n\s*path:\s*(?P<path>[^\n]+)/s',
    $content,
    $matches,
    PREG_SET_ORDER
);

if (empty($matches)) {
    echo "¡No se encontraron errores en el baseline! El proyecto está 100% limpio en Nivel 9.\n";
    exit(0);
}

// 1. Agrupar por archivo
$groupedByFile = [];
foreach ($matches as $m) {
    $identifier = trim($m['identifier']);
    $filePath = trim($m['path']);
    $count = (int) $m['count'];
    $message = trim($m['message'], " \t\n\r\"");

    $groupedByFile[$filePath][] = [
        'identifier' => $identifier,
        'message' => $message,
        'count' => $count,
    ];
}

// 2. Ordenar archivos de MAYOR a MENOR cantidad de errores
$fileTotals = [];
foreach ($groupedByFile as $filePath => $entries) {
    $fileTotals[$filePath] = array_sum(array_column($entries, 'count'));
}
arsort($fileTotals);

$grandTotalErrors = array_sum($fileTotals);

// --- RESUMEN GENERAL POR ARCHIVO ---
echo "--- Resumen por archivo (mayor a menor cantidad de errores) ---\n\n";
foreach ($fileTotals as $filePath => $sum) {
    printf("%5d  %s\n", $sum, $filePath);
}
echo "\nTotal de errores: {$grandTotalErrors} en " . count($fileTotals) . " archivo(s).\n";
echo "---------------------------------------------------------------\n\n";

// 3. Tomar acumulativamente hasta $maxErrors para el prompt
$promptTree = [];
$collected = 0;

foreach ($fileTotals as $filePath => $total) {
    foreach ($groupedByFile[$filePath] as $entry) {
        $remainingSpace = $maxErrors - $collected;
        $take = min($entry['count'], $remainingSpace);

        if ($take > 0) {
            $promptTree[$filePath][$entry['identifier']][] = [
                'message' => $entry['message'],
                'count' => $take,
            ];
            $collected += $take;
        }

        if ($collected >= $maxErrors) {
            break 2; // Salir de ambos bucles
        }
    }
}

// 4. Formatear listas de archivos
$affectedFiles = array_keys($promptTree);
$affectedFilesFormatted = implode(", ", array_map(fn($f) => "`$f`", $affectedFiles));
$affectedFilesCLI = implode(' ', array_map('escapeshellarg', $affectedFiles));

// 5. Formatear bloque de errores
$errorsBlock = '';
foreach ($promptTree as $filePath => $byIdent) {
    $fileErrorSum = 0;
    foreach ($byIdent as $items) {
        $fileErrorSum += array_sum(array_column($items, 'count'));
    }

    $errorsBlock .= "### Archivo: `{$filePath}` ({$fileErrorSum} error(es))\n";
    foreach ($byIdent as $identifier => $items) {
        $errorsBlock .= "## identifier: {$identifier}\n";
        foreach ($items as $e) {
            $errorsBlock .= "- ({$e['count']}x) {$e['message']}\n";
        }
    }
    $errorsBlock .= "\n";
}

// 6. Construir el prompt para GitHub Copilot
$prompt = <<<PROMPT
Corregí los siguientes {$collected} errores de PHPStan nivel 9 (total de errores restantes en el proyecto: {$grandTotalErrors}).

Archivos a modificar:
{$affectedFilesFormatted}

Reglas generales:
- No modifiques lógica de negocio. Solo agregá o corregí anotaciones de tipo (PHPDoc) o casteos seguros para satisfacer PHPStan nivel 9.
- No uses `@phpstan-ignore-line` ni agregues entradas manuales al baseline salvo que yo lo indique explícitamente.
- Preferí el casteo explícito y seguro (`(string)`, `(int)`, etc.) antes que `@phpstan-ignore`.
- Si no encontrás el origen de un error directamente en el archivo indicado, verificá los **Traits** que dicho archivo utiliza. Si el error proviene de un Trait, tenés permitido modificarlo para solucionar el problema.

Convenciones de arrays:
- Configuración de componentes: `array<string, mixed>`
- Listas secuenciales de strings: `list<string>`

Patrones conocidos para `argument.type`:
- `Request::input()`, `config()`, `session()->get()` y `Cache::get()` devuelven `mixed`. Si el punto de uso espera un tipo concreto, casteá ahí mismo (`(string) \$request->input('campo')`), o usá el segundo argumento como valor por defecto cuando ayude a la inferencia.
- Si `config()` se usa para obtener un array que después se accede por offset o se itera, NO castees cada acceso individual. Poné una anotación `@var` precisa en el punto donde se asigna la variable, ej. `/** @var array<string, string> \$nombre */ \$nombre = config('x.y');`. Revisá el archivo de config real para elegir el tipo correcto del value, no lo asumas.

Cuándo NO castear automáticamente:
- Si el error dice `mixed given`, aplicá el cast directo según las reglas de arriba.
- Si el error dice `string|false given` (o cualquier unión que NO sea `mixed`), NO apliques un cast ciego. Mostrame la línea y el contexto, explicá de dónde viene ese valor no-mixed, y proponé el fix sin aplicarlo todavía.

Antes de tocar nada:
Analizá el/los archivo(s) y los errores de abajo, y mostrame primero un resumen corto pero explícito de qué vas a cambiar y por qué (línea o método afectado, tipo de fix a aplicar). Recién después de mostrar ese resumen, aplicá los cambios.

No toques archivos fuera de: {$affectedFilesFormatted} (salvo Traits utilizados directamente por ellos si allí reside el error).

Al terminar:
- Corré, con límite de tiempo para que el comando no quede colgado: `timeout 120 vendor/bin/phpstan analyze --level 9 {$affectedFilesCLI}` y verificá que la cantidad de errores en estos archivos se redujo o eliminó.
- Corré, también con límite de tiempo: `timeout 300 php artisan test` y confirmá que no queda ningún test roto por tus cambios.
- Si un comando se corta por el timeout sin devolver resultado, NO lo reintentes en loop: avisame que se cortó, mostrame el output parcial que haya, y esperá indicación mía antes de seguir.
- Si te quedás iterando más de 2 veces sobre el mismo error puntual sin resolverlo, no sigas insistiendo: dejalo documentado en el resumen final (archivo, línea, qué probaste) y seguí con el resto de los errores.
- Si algo de esto falla por un error real (no por timeout), no lo des por terminado: mostrame el error y corregilo antes de terminar.
- Devolveme un resumen breve de qué se cambió, sin explicaciones largas.

Errores a corregir:

{$errorsBlock}
PROMPT;

echo "===== RESUMEN DE ERRORES SELECCIONADOS PARA ESTE PROMPT ({$collected} de {$grandTotalErrors} totales, límite: {$maxErrors}) =====\n";
foreach ($promptTree as $f => $byIdent) {
    $cnt = 0;
    foreach ($byIdent as $items) {
        $cnt += array_sum(array_column($items, 'count'));
    }
    echo " - {$cnt} error(es) -> {$f}\n";
}
echo "=========================================================================\n\n";

echo "===== PROMPT (copiá y pegá en GitHub Copilot) =====\n\n";
echo $prompt . "\n";
echo "===== FIN DEL PROMPT =====\n";
