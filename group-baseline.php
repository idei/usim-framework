<?php
/**
 * Agrupa las entradas de un baseline de PHPStan por ARCHIVO, para poder atacar
 * "todos los errores de un archivo" en un solo lote de Cline (evita releer el
 * mismo archivo en tareas separadas por cada identifier distinto).
 *
 * Uso:
 *   php group-baseline.php phpstan-9-baseline.neon
 *       -> resumen: cuántos errores tiene cada archivo, ordenado de mayor a menor
 *
 *   php group-baseline.php phpstan-9-baseline.neon app/Http/Controllers/Api/FileController.php
 *       -> detalle completo de ese archivo (todos los identifiers), listo para
 *          pegar como prompt en Cline
 */

if ($argc < 2) {
    fwrite(STDERR, "Uso: php group-baseline.php <baseline.neon> [ruta/al/archivo.php]\n");
    exit(1);
}

$path = $argv[1];
$filterFile = $argv[2] ?? null;

$content = file_get_contents($path);
if ($content === false) {
    fwrite(STDERR, "No se pudo leer: $path\n");
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
    echo "Ejecutá: php group-baseline.php $path <ruta/al/archivo.php> para ver el detalle de uno.\n";
    exit(0);
}

// Detalle completo de un archivo puntual, con todos sus identifiers
if (!isset($grouped[$filterFile])) {
    fwrite(STDERR, "No hay entradas para archivo: $filterFile\n");
    exit(1);
}

$entries = $grouped[$filterFile];
$total = array_sum(array_column($entries, 'count'));

echo "# Archivo: $filterFile ($total errores)\n\n";

// Agrupo por identifier dentro del archivo, para que el prompt sea legible
$byIdentifier = [];
foreach ($entries as $e) {
    $byIdentifier[$e['identifier']][] = $e;
}

foreach ($byIdentifier as $identifier => $items) {
    echo "## identifier: $identifier\n";
    foreach ($items as $e) {
        echo "- ({$e['count']}x) {$e['message']}\n";
    }
    echo "\n";
}

if ($total > 15) {
    echo "NOTA: este archivo tiene más de 15 errores. Considerá dividirlo en 2-3 tandas\n";
    echo "(por ejemplo, por identifier) para que el diff sea más fácil de revisar.\n";
}
