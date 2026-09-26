<?php

namespace Idei\Usim\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Idei\Usim\Support\ScreenDiscoveryService;
use Idei\Usim\Support\UsimConfig;

class DiscoverScreensCommand extends Command
{
    protected $signature = 'usim:discover';
    protected $description = 'Discover UI Screens and cache their metadata';

    public function handle(ScreenDiscoveryService $discoveryService, UsimConfig $usimConfig): int
    {
        $this->checkNotInProduction();

        $this->info('Discovering USIM Screens...');

        $screens = $discoveryService->discover();

        $count = \count($screens);
        $this->info("Found {$count} screens.");

        $pruned = $discoveryService->getLastPrunedCount();
        if ($pruned > 0) {
            $this->warn("Pruned {$pruned} obsolete screen permissions.");
        }

        $this->writeManifest($screens);
        $this->writeScreenTranslations($screens, $usimConfig);

        $this->info('USIM manifest generated successfully!');

        return self::SUCCESS;
    }

    /** @param array<string, mixed> $screens */
    private function writeScreenTranslations(array $screens, UsimConfig $usimConfig): void
    {
        $activeLocales = $usimConfig->activeLanguageCodes;
        if ($activeLocales === []) {
            return;
        }

        $screensNamespace = $usimConfig->screensNamespace;

        foreach (array_keys($screens) as $screenClass) {
            if (trim($screenClass) === '') {
                continue;
            }

            $relativePath = $this->resolveScreenLangRelativePath($screenClass, $screensNamespace);
            $defaultMenuTitle = Str::headline(class_basename($screenClass));
            $defaultIcon = '';

            foreach ($activeLocales as $locale) {
                $langFile = lang_path($locale . DIRECTORY_SEPARATOR . 'screen' . DIRECTORY_SEPARATOR . $relativePath);
                $fileExists = File::exists($langFile);
                $payload = $fileExists ? $this->loadLangArrayFile($langFile) : [];

                $hasMenuTitle = \array_key_exists('menu_title', $payload);
                $hasIcon = \array_key_exists('icon', $payload);

                if ($fileExists && $hasMenuTitle && $hasIcon) {
                    continue;
                }

                $mergedPayload = array_merge(
                    [
                        'menu_title' => $defaultMenuTitle,
                        'icon' => $defaultIcon,
                    ],
                    $payload
                );

                $dir = \dirname($langFile);
                if (!File::isDirectory($dir)) {
                    File::makeDirectory($dir, 0755, true);
                }

                $content = "<?php\n\nreturn " . $this->exportLangArray($mergedPayload) . ";\n";
                File::put($langFile, $content);
            }
        }
    }

    private function resolveScreenLangRelativePath(string $screenClass, string $screensNamespace): string
    {
        $normalizedNamespace = trim($screensNamespace, '\\') . '\\';

        if (str_starts_with($screenClass, $normalizedNamespace)) {
            $relativeClass = substr($screenClass, strlen($normalizedNamespace));
        } elseif (str_contains($screenClass, 'Screens\\')) {
            $relativeClass = Str::after($screenClass, 'Screens\\');
        } else {
            $relativeClass = class_basename($screenClass);
        }

        $segments = array_values(array_filter(
            explode('\\', trim($relativeClass, '\\')),
            static fn(string $segment): bool => $segment !== ''
        ));

        $snakeSegments = array_map(
            static fn(string $segment): string => Str::snake($segment),
            $segments
        );

        return implode(DIRECTORY_SEPARATOR, $snakeSegments) . '.php';
    }

    /**
     * @return array<string, mixed>
     */
    private function loadLangArrayFile(string $path): array
    {
        if (!\is_file($path)) {
            return [];
        }

        $loaded = require $path;

        if (!\is_array($loaded)) {
            return [];
        }

        /** @var array<string, mixed> $loaded */
        return $loaded;
    }

    /**
     * @param array<mixed> $payload
     */
    private function exportLangArray(array $payload, int $indentLevel = 0): string
    {
        if ($payload === []) {
            return '[]';
        }

        $indent = str_repeat('    ', $indentLevel);
        $itemIndent = str_repeat('    ', $indentLevel + 1);

        $lines = ['['];

        foreach ($payload as $key => $value) {
            $serializedKey = \is_int($key) ? (string) $key : var_export((string) $key, true);
            $serializedValue = \is_array($value)
                ? $this->exportLangArray($value, $indentLevel + 1)
                : var_export($value, true);

            $lines[] = "{$itemIndent}{$serializedKey} => {$serializedValue},";
        }

        $lines[] = "{$indent}]";

        return implode("\n", $lines);
    }

    /** @param array<string, mixed> $screens */
    private function writeManifest(array $screens): void
    {
        $path = $this->getManifestPath();

        $content = "<?php\n\nreturn " . $this->formatArrayToShortSyntax($screens) . ";\n";

        file_put_contents($path, $content);

        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($path, true);
        }
    }

    /** @param array<array-key, mixed> $array */
    private function formatArrayToShortSyntax(array $array, int $indentLevel = 0): string
    {
        $indent = str_repeat("\t", $indentLevel);
        $subIndent = str_repeat("\t", $indentLevel + 1);

        $parts = [];
        foreach ($array as $key => $value) {
            // Verificamos si la clave parece una clase de PHP (contiene barras invertidas)
            if (is_string($key) && str_contains($key, '\\')) {
                // Quitamos el escape doble para que quede App\UI\Screens\Home::class
                $formattedKey = "{$key}::class";
            } else {
                $formattedKey = is_int($key) ? $key : "'" . addslashes($key) . "'";
            }

            if (\is_array($value)) {
                $formattedValue = $this->formatArrayToShortSyntax($value, $indentLevel + 1);
            } else {
                $formattedValue = var_export($value, true);
            }

            $parts[] = "{$subIndent}{$formattedKey} => {$formattedValue},";
        }

        if (empty($parts)) {
            return "[]";
        }

        return "[\n" . implode("\n", $parts) . "\n{$indent}]";
    }

    private function getManifestPath(): string
    {
        return app()->bootstrapPath('cache/usim_screens.php');
    }

    private function checkNotInProduction(): void
    {
        if (app()->environment('production')) {
            $this->error('This command cannot be run in production environment.');
            exit(1);
        }
    }
}
