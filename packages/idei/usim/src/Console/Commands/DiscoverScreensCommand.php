<?php

namespace Idei\Usim\Console\Commands;

use Illuminate\Console\Command;
use Idei\Usim\Support\ScreenDiscoveryService;

class DiscoverScreensCommand extends Command
{
    protected $signature = 'usim:discover';
    protected $description = 'Discover UI Screens and cache their metadata';

    public function handle(ScreenDiscoveryService $discoveryService)
    {
        $this->checkNotInProduction();

        $this->info('Discovering USIM Screens...');

        $screens = $discoveryService->discover();

        $count = \count($screens);
        $this->info("Found {$count} screens.");

        $this->writeManifest($screens);

        $this->info('USIM manifest generated successfully!');
    }

    private function writeManifest(array $screens): void
    {
        $path = $this->getManifestPath();

        $content = "<?php\n\nreturn " . $this->formatArrayToShortSyntax($screens) . ";\n";

        file_put_contents($path, $content);

        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($path, true);
        }
    }

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
                $formattedValue = \is_int($value) || \is_float($value) ? $value : "'" . addslashes($value) . "'";
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
