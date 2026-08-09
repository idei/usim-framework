<?php
/**
 * Agrupa las entradas de un baseline de PHPStan por ARCHIVO, para poder atacar
 * "todos los errores de un archivo" en un solo lote de Cline (evita releer el
 * mismo archivo en tareas separadas por cada identifier distinto).
 *
 * Uso:
 *   php group-baseline.php
 *       -> resumen: cuántos errores tiene cada archivo, ordenado de mayor a menor
 *
 *   php group-baseline.php app/Http/Controllers/Api/FileController.php
 *       -> detalle completo de ese archivo (todos los identifiers), listo para
 *          pegar como prompt en Cline
 *
 * En ambos casos, antes de leer nada, el script corre:
 *   vendor/bin/phpstan analyze --level 9 --generate-baseline phpstan-9-baseline.neon
 * y sobreescribe siempre ese archivo, así no hace falta generarlo en un paso
 * aparte ni pasarlo como argumento.
 */

const BASELINE_PATH = 'phpstan-9-baseline.neon';

$filterFile = $argv[1] ?? null;

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

// Cada entrada del baseline tiene esta forma (indentación con tabs):
//   -
//       message: "..."
//       identifier: argument.type
//       count: N
//       path: app/Foo.php
preg_match_all(
    '/-\s*\n\s*message:\s*(?P<message>.+?)\n\s*identifier:\s*(?P<identifier>[^\n]+)\n\s*count:\s*(?P<count>\d+)\n\s*path:\s*(?P<path>[^\n]+)/s',
    $content,
    $matches,
    PREG_SET_ORDER
);

if (empty($matches)) {
    fwrite(STDERR, "No se encontraron entradas. Revisá el formato del baseline (puede variar entre versiones de PHPStan).\n");
    exit(1);
}

$grouped = [];
foreach ($matches as $m) {
    $identifier = trim($m['identifier']);
    $filePath = trim($m['path']);
    $count = (int) $m['count'];
    $message = trim($m['message'], " \t\n\r\"");

    $grouped[$filePath][] = [
        'identifier' => $identifier,
        'message' => $message,
        'count' => $count,
    ];
}

if ($filterFile === null) {
    // Resumen general: total de errores por archivo, de mayor a menor
    $totals = [];
    foreach ($grouped as $filePath => $entries) {
        $sum = 0;
        foreach ($entries as $e) {
            $sum += $e['count'];
        }
        $totals[$filePath] = $sum;
    }
    arsort($totals);

    echo "Resumen por archivo (mayor a menor cantidad de errores):\n\n";
    foreach ($totals as $filePath => $sum) {
        $flag = $sum > 15 ? '  <- considerar dividir en sub-lotes' : '';
        printf("%5d  %s%s\n", $sum, $filePath, $flag);
    }
    echo "\nTotal de archivos con errores: " . count($totals) . "\n";
    echo "Ejecutá: php group-baseline.php <ruta/al/archivo.php> para ver el detalle de uno.\n";
    exit(0);
}

// Detalle completo de un archivo puntual, con todos sus identifiers
if (!isset($grouped[$filterFile])) {
    fwrite(STDERR, "No hay entradas para archivo: $filterFile\n");
    exit(1);
}

$entries = $grouped[$filterFile];
$total = array_sum(array_column($entries, 'count'));

// Agrupo por identifier dentro del archivo, para que el prompt sea legible
$byIdentifier = [];
foreach ($entries as $e) {
    $byIdentifier[$e['identifier']][] = $e;
}

$errorsBlock = '';
foreach ($byIdentifier as $identifier => $items) {
    $errorsBlock .= "## identifier: $identifier\n";
    foreach ($items as $e) {
        $errorsBlock .= "- ({$e['count']}x) {$e['message']}\n";
    }
    $errorsBlock .= "\n";
}

$splitNote = '';
if ($total > 15) {
    $splitNote = "\nNota: este archivo tiene más de 15 errores. Si el diff queda muy grande para\n"
        . "revisar de una, dividí el trabajo en 2-3 tandas (por ejemplo, por identifier).\n";
}

$prompt = <<<PROMPT
Corregí los errores de PHPStan nivel 9 del archivo `{$filterFile}` ({$total} errores), siguiendo estas convenciones del proyecto (Idei\\Usim):

Reglas generales:
- No modifiques lógica de negocio. Solo agregá o corregí anotaciones de tipo (PHPDoc) o casteos seguros para satisfacer PHPStan nivel 9.
- No uses `@phpstan-ignore-line` ni agregues entradas manuales al baseline salvo que yo lo indique explícitamente.
- Preferí el casteo explícito y seguro (`(string)`, `(int)`, etc.) antes que `@phpstan-ignore`.

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
Analizá el archivo y los errores de abajo, y mostrame primero un resumen corto pero explícito de qué vas a cambiar y por qué (línea o método afectado, tipo de fix a aplicar). Recién después de mostrar ese resumen, aplicá los cambios.

No toques archivos fuera de `{$filterFile}`.

Al terminar, devolveme un resumen breve de qué se cambió, sin explicaciones largas.

Errores a corregir:

{$errorsBlock}{$splitNote}
PROMPT;

echo "===== PROMPT (copiá y pegá en Cline) =====\n\n";
echo $prompt . "\n";
echo "===== FIN DEL PROMPT =====\n";
