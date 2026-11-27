<?php

declare(strict_types=1);

/**
 * CRAP (Change Risk Anti-Patterns) analyzer for the framework.
 *
 * Usage:
 *   php scripts/crap.php [options]
 *
 * Options:
 *   --skip-tests          No ejecuta Pest; analiza el clover.xml existente.
 *   --threshold=30        Umbral de CRAP para considerar un método crítico (default: 30).
 *   --top=15              Cantidad de métodos y clases a mostrar en el ranking (default: 15).
 *   --clover=PATH         Ruta al archivo clover.xml (default: build/logs/clover.xml).
 *   --path=DIR            Filtra el análisis a un módulo/directorio específico (ej: src/Core).
 *   --fail-over=PERCENT   Falla con exit(1) si el % de métodos críticos supera este valor (CI/CD).
 */
$options = getopt('', ['skip-tests', 'threshold:', 'top:', 'clover:', 'path:', 'fail-over:']);

$skipTests = array_key_exists('skip-tests', $options);
$threshold = isset($options['threshold']) ? (float) $options['threshold'] : 30.0;
$topLimit = isset($options['top']) ? max(1, (int) $options['top']) : 15;
$cloverPath = isset($options['clover']) ? (string) $options['clover'] : __DIR__.'/../build/logs/clover.xml';
$pathFilter = isset($options['path']) ? trim(str_replace('\\', '/', (string) $options['path']), '/') : null;
$failOver = isset($options['fail-over']) ? (float) $options['fail-over'] : null;

if (! $skipTests || ! file_exists($cloverPath)) {
    $cloverDir = dirname($cloverPath);
    if (! is_dir($cloverDir)) {
        mkdir($cloverDir, 0775, true);
    }

    echo "\033[1;34m▶ Ejecutando tests con cobertura (Pest + PCOV)...\033[0m\n";
    $cmd = sprintf('./vendor/bin/pest --coverage-clover %s', escapeshellarg($cloverPath));
    passthru($cmd, $exitCode);

    if ($exitCode !== 0) {
        fwrite(STDERR, "\033[1;31m✖ Error al ejecutar los tests (código {$exitCode}).\033[0m\n");
        exit($exitCode);
    }
    echo "\n";
}

if (! file_exists($cloverPath)) {
    fwrite(STDERR, "\033[1;31m✖ No se encontró el archivo Clover XML en: {$cloverPath}\033[0m\n");
    exit(1);
}

$xml = simplexml_load_file($cloverPath);
if ($xml === false) {
    fwrite(STDERR, "\033[1;31m✖ No se pudo parsear el archivo Clover XML.\033[0m\n");
    exit(1);
}

/**
 * Calcula el CRAP Load (esfuerzo estimado para bajar del umbral CRAP).
 */
function calculateCrapLoad(float $crap, int $ccn, float $coveragePercent, float $threshold): float
{
    if ($crap < $threshold || $threshold <= 0.0) {
        return 0.0;
    }

    return ($ccn * (1.0 - ($coveragePercent / 100.0))) + ($ccn / $threshold);
}

function colorForCrap(float $crap, float $threshold): string
{
    if ($crap >= $threshold) {
        return "\033[1;31m"; // Rojo
    }
    if ($crap >= $threshold / 2) {
        return "\033[1;33m"; // Amarillo
    }

    return "\033[0;32m"; // Verde
}

$projectRoot = realpath(__DIR__.'/..');
$projectRootPrefix = $projectRoot !== false ? str_replace('\\', '/', $projectRoot).'/' : '';

$methods = [];
$classes = [];
$totalFiles = 0;
$totalStatements = 0;
$coveredStatements = 0;
$totalComplexity = 0;
$totalCrap = 0.0;
$totalCrapLoad = 0.0;
$crapMethodCount = 0;

$fileNodes = $xml->xpath('//file') ?: [];

foreach ($fileNodes as $fileNode) {
    $filePath = str_replace('\\', '/', (string) $fileNode['name']);
    $relativePath = $projectRootPrefix !== '' && str_starts_with($filePath, $projectRootPrefix)
        ? substr($filePath, strlen($projectRootPrefix))
        : $filePath;

    if ($pathFilter !== null && $pathFilter !== '' && ! str_starts_with($relativePath, $pathFilter)) {
        continue;
    }

    $totalFiles++;

    $fileMetrics = $fileNode->metrics[0] ?? null;
    if ($fileMetrics !== null) {
        $totalStatements += (int) $fileMetrics['statements'];
        $coveredStatements += (int) $fileMetrics['coveredstatements'];
    }

    $classNode = $fileNode->class[0] ?? null;
    $className = $classNode ? (string) $classNode['name'] : $relativePath;
    $classMetrics = $classNode ? ($classNode->metrics[0] ?? null) : $fileMetrics;

    $classComplexity = $classMetrics ? (int) $classMetrics['complexity'] : 0;
    $classStatements = $classMetrics ? (int) $classMetrics['statements'] : 0;
    $classCoveredStatements = $classMetrics ? (int) $classMetrics['coveredstatements'] : 0;
    $classCoverage = $classStatements > 0 ? ($classCoveredStatements / $classStatements) * 100.0 : 100.0;

    $lines = [];
    foreach ($fileNode->line as $lineNode) {
        $lines[] = $lineNode;
    }

    $fileMethodsCrapSum = 0.0;
    $fileMethodsMaxCrap = 0.0;
    $fileMethodsCrapLoadSum = 0.0;
    $fileCrappyMethods = 0;
    $fileMethodsCount = 0;
    $fileComplexitySum = 0;

    for ($i = 0, $n = count($lines); $i < $n; $i++) {
        $line = $lines[$i];
        if ((string) $line['type'] !== 'method') {
            continue;
        }

        $methodName = (string) $line['name'];
        $lineNum = (int) $line['num'];
        $ccn = (int) $line['complexity'];
        $crap = (float) $line['crap'];

        $stmtTotal = 0;
        $stmtCovered = 0;
        for ($j = $i + 1; $j < $n; $j++) {
            if ((string) $lines[$j]['type'] === 'method') {
                break;
            }
            if ((string) $lines[$j]['type'] === 'stmt') {
                $stmtTotal++;
                if ((int) $lines[$j]['count'] > 0) {
                    $stmtCovered++;
                }
            }
        }

        $methodCoverage = $stmtTotal > 0
            ? ($stmtCovered / $stmtTotal) * 100.0
            : ((int) $line['count'] > 0 ? 100.0 : 0.0);

        $crapLoad = calculateCrapLoad($crap, $ccn, $methodCoverage, $threshold);

        $methods[] = [
            'class' => $className,
            'method' => $methodName,
            'full' => "{$className}::{$methodName}",
            'file' => $relativePath,
            'line' => $lineNum,
            'ccn' => $ccn,
            'coverage' => $methodCoverage,
            'crap' => $crap,
            'crapLoad' => $crapLoad,
        ];

        $totalComplexity += $ccn;
        $totalCrap += $crap;
        $totalCrapLoad += $crapLoad;
        $fileMethodsCrapSum += $crap;
        $fileMethodsMaxCrap = max($fileMethodsMaxCrap, $crap);
        $fileMethodsCrapLoadSum += $crapLoad;
        $fileComplexitySum += $ccn;
        $fileMethodsCount++;

        if ($crap >= $threshold) {
            $crapMethodCount++;
            $fileCrappyMethods++;
        }
    }

    if ($classNode || $fileMethodsCount > 0) {
        if ($classComplexity === 0 && $fileMethodsCount > 0) {
            $classComplexity = $fileComplexitySum;
        }

        $classes[] = [
            'class' => $className,
            'file' => $relativePath,
            'methods' => $fileMethodsCount,
            'ccn' => $classComplexity,
            'coverage' => $classCoverage,
            'maxMethodCrap' => $fileMethodsMaxCrap,
            'sumMethodCrap' => $fileMethodsCrapSum,
            'sumCrapLoad' => $fileMethodsCrapLoadSum,
            'crappyMethods' => $fileCrappyMethods,
        ];
    }
}

if ($totalFiles === 0) {
    $scope = $pathFilter !== null ? " para el filtro --path={$pathFilter}" : '';
    fwrite(STDERR, "\033[1;33m⚠ No se encontraron archivos en el Clover XML{$scope}.\033[0m\n");
    exit(1);
}

$totalClasses = count($classes);
$totalMethods = count($methods);
$appCoverage = $totalStatements > 0 ? ($coveredStatements / $totalStatements) * 100.0 : 0.0;
$avgMethodCrap = $totalMethods > 0 ? $totalCrap / $totalMethods : 0.0;
$crapMethodPercent = $totalMethods > 0 ? ($crapMethodCount / $totalMethods) * 100.0 : 0.0;

// Ordenamiento de métodos: mayor CRAP, luego mayor CCN
usort($methods, fn (array $a, array $b): int => $b['crap'] <=> $a['crap'] ?: $b['ccn'] <=> $a['ccn']);

// Ordenamiento de clases: peor método (maxMethodCrap), luego riesgo acumulado (sumMethodCrap)
usort($classes, fn (array $a, array $b): int => $b['maxMethodCrap'] <=> $a['maxMethodCrap']
    ?: $b['sumMethodCrap'] <=> $a['sumMethodCrap']
    ?: $b['ccn'] <=> $a['ccn']);

$maxMethodCrap = $methods[0]['crap'] ?? 0.0;

$reset = "\033[0m";
$bold = "\033[1m";
$cyan = "\033[1;36m";
$scopeLabel = $pathFilter !== null && $pathFilter !== '' ? " [Filtro: {$pathFilter}]" : ' [Global]';

echo "{$cyan}========================================================================================{$reset}\n";
echo "{$bold}  REPORTE DE CRAP DEL FRAMEWORK{$scopeLabel}{$reset}\n";
echo "{$cyan}========================================================================================{$reset}\n";
printf("  Archivos analizados       : %d\n", $totalFiles);
printf("  Clases / Unidades         : %d\n", $totalClasses);
printf("  Métodos totales           : %d\n", $totalMethods);
printf("  Complejidad Ciclomática   : %d (promedio %.2f por método)\n", $totalComplexity, $totalMethods > 0 ? $totalComplexity / $totalMethods : 0.0);
printf("  Cobertura de sentencias   : %.2f%% (%d / %d)\n", $appCoverage, $coveredStatements, $totalStatements);
echo "----------------------------------------------------------------------------------------\n";
printf("  {$bold}CRAP Total (suma métodos){$reset} : %.2f\n", $totalCrap);
printf("  {$bold}CRAP Promedio por método{$reset}  : %s%.2f{$reset}\n", colorForCrap($avgMethodCrap, $threshold), $avgMethodCrap);
printf("  {$bold}CRAP Máximo (peor método){$reset} : %s%.2f{$reset}\n", colorForCrap($maxMethodCrap, $threshold), $maxMethodCrap);
printf(
    "  {$bold}Métodos críticos (>= %d){$reset}  : %s%d de %d (%.2f%%){$reset} [Meta: < %.2f%%]\n",
    (int) $threshold,
    $crapMethodPercent > ($failOver ?? 5.0) ? "\033[1;31m" : "\033[0;32m",
    $crapMethodCount,
    $totalMethods,
    $crapMethodPercent,
    $failOver ?? 5.0
);
printf("  {$bold}CRAP Load (deuda técnica){$reset} : %.0f\n", round($totalCrapLoad));
echo "{$cyan}========================================================================================{$reset}\n\n";

echo "{$bold}TOP {$topLimit} MÉTODOS CON MAYOR ÍNDICE CRAP:{$reset}\n";
echo str_repeat('-', 105)."\n";
printf("  %-8s | %-5s | %-9s | %-9s | %s\n", 'CRAP', 'CCN', 'Cobertura', 'CRAP Load', 'Método (Archivo:Línea)');
echo str_repeat('-', 105)."\n";

foreach (array_slice($methods, 0, $topLimit) as $m) {
    $color = colorForCrap($m['crap'], $threshold);
    printf(
        "  %s%8.2f{$reset} | %5d | %8.1f%% | %9.1f | %s (%s:%d)\n",
        $color,
        $m['crap'],
        $m['ccn'],
        $m['coverage'],
        $m['crapLoad'],
        $m['full'],
        $m['file'],
        $m['line']
    );
}
echo str_repeat('-', 105)."\n\n";

echo "{$bold}TOP {$topLimit} CLASES CON MAYOR RIESGO (ordenadas por peor método):{$reset}\n";
echo str_repeat('-', 105)."\n";
printf("  %-10s | %-10s | %-9s | %-5s | %-9s | %-10s | %s\n", 'Max CRAP', 'Σ CRAP Mét', 'CRAP Load', 'CCN', 'Cobertura', 'Mét >= '.(int) $threshold, 'Clase');
echo str_repeat('-', 105)."\n";

foreach (array_slice($classes, 0, $topLimit) as $c) {
    $color = colorForCrap($c['maxMethodCrap'], $threshold);
    printf(
        "  %s%10.2f{$reset} | %10.2f | %9.1f | %5d | %8.1f%% | %4d / %-3d | %s\n",
        $color,
        $c['maxMethodCrap'],
        $c['sumMethodCrap'],
        $c['sumCrapLoad'],
        $c['ccn'],
        $c['coverage'],
        $c['crappyMethods'],
        $c['methods'],
        $c['class']
    );
}
echo str_repeat('-', 105)."\n";

if ($failOver !== null) {
    if ($crapMethodPercent > $failOver) {
        fwrite(
            STDERR,
            sprintf(
                "\n\033[1;31m✖ QUALITY GATE FALLIDO: %.2f%% de métodos críticos (>= %.0f) supera el máximo permitido de %.2f%%.\033[0m\n",
                $crapMethodPercent,
                $threshold,
                $failOver
            )
        );
        exit(1);
    }

    echo sprintf(
        "\n\033[0;32m✔ QUALITY GATE APROBADO: %.2f%% de métodos críticos (máximo permitido: %.2f%%).\033[0m\n",
        $crapMethodPercent,
        $failOver
    );
}
