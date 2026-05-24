<?php

namespace Idei\Usim\Console\Commands\Support;

use Illuminate\Filesystem\Filesystem;

class InstallScaffoldingManager
{
    public function __construct(private readonly Filesystem $files)
    {
    }

    public function publishConfig(bool $force, callable $info, callable $line, callable $callSilently): void
    {
        $info('Publishing USIM configuration...');

        $callSilently('vendor:publish', [
            '--tag' => 'usim-config',
            '--force' => $force,
        ]);

        $line('  <fg=green>✓</> Config published');
    }

    public function publishAssets(callable $info, callable $line, callable $callSilently): void
    {
        $info('Publishing USIM assets...');

        $callSilently('vendor:publish', [
            '--tag' => 'usim-assets',
            '--force' => true,
        ]);

        $line('  <fg=green>✓</> Assets published');
    }

    public function installViews(callable $newLine, callable $info, callable $line, callable $stubsPath, callable $publishStub): void
    {
        $newLine();
        $info('Publishing email and page views...');

        $views = [
            'emails/verify-email.blade.php' => resource_path('views/emails/verify-email.blade.php'),
            'emails/reset-password.blade.php' => resource_path('views/emails/reset-password.blade.php'),
            'terms.blade.php' => resource_path('views/terms.blade.php'),
            'landing.blade.php' => resource_path('views/landing.blade.php'),
        ];

        foreach ($views as $stub => $target) {
            $stubPath = $stubsPath("views/{$stub}");
            $publishStub($stubPath, $target, false, []);
            $relativePath = str_replace(base_path() . '/', '', $target);
            $line("  <fg=green>✓</> {$relativePath}");
        }
    }

    public function installUsersConfigNotice(callable $line): void
    {
        $line('  <fg=blue>→</> Users config now lives in config/usim.php (usim.users.*)');
    }

    public function installWebRoutes(callable $newLine, callable $info, callable $line, callable $stubsPath): void
    {
        $newLine();
        $info('Installing web routes...');

        $webRoutesPath = base_path('routes/web.php');
        $contents = $this->files->exists($webRoutesPath) ? $this->files->get($webRoutesPath) : '';

        [$contents, $disabledDefaultWelcomeRoute] = $this->disableDefaultWelcomeRoute($contents);
        if ($disabledDefaultWelcomeRoute) {
            $this->files->put($webRoutesPath, $contents);
            $line('  <fg=green>✓</> Default welcome route disabled in routes/web.php');
        }

        if (str_contains($contents, 'ui.catchall')) {
            $line('  <fg=blue>→</> Catch-all route already exists in routes/web.php');
            return;
        }

        $stubContent = $this->files->get($stubsPath('routes/web.php.stub'));

        $this->files->append($webRoutesPath, "\n" . $stubContent);
        $line('  <fg=green>✓</> Catch-all route added to routes/web.php');
    }

    /**
     * @return array{0: string, 1: bool}
     */
    private function disableDefaultWelcomeRoute(string $contents): array
    {
        $patterns = [
            '/Route::get\(\s*["\']\/["\']\s*,\s*function\s*\(\)\s*\{\s*return\s+view\(\s*["\']welcome["\']\s*\)\s*;\s*\}\s*\)\s*;\s*/s',
            '/Route::view\(\s*["\']\/["\']\s*,\s*["\']welcome["\']\s*\)\s*;\s*/s',
        ];

        $disabled = false;

        foreach ($patterns as $pattern) {
            $contents = preg_replace_callback(
                $pattern,
                static function (array $matches) use (&$disabled): string {
                    $disabled = true;

                    $lines = preg_split('/\R/', trim($matches[0])) ?: [];
                    $commentedRoute = implode("\n", array_map(
                        static fn (string $line): string => '// ' . $line,
                        $lines
                    ));

                    return "// Disabled by usim:install to allow USIM catch-all route.\n{$commentedRoute}\n\n";
                },
                $contents,
                1
            ) ?? $contents;
        }

        return [$contents, $disabled];
    }
}
