<?php

namespace Idei\Usim\Console\Commands\Support;

use Idei\Usim\Support\UsimConfig;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

abstract class BaseScaffoldCommand extends Command
{
    public function __construct(
        protected UsimConfig $config,
        protected Filesystem $filesystem
    ) {
        parent::__construct();
    }

    /**
     * Get the absolute path to a stub file within the package.
     */
    public function stubsPath(string $path = ''): string
    {
        return dirname(__DIR__, 4).'/stubs/'.ltrim($path, '/\\');
    }

    /**
     * Convert strings like "user_name" or "user-name" into "UserName".
     */
    public function pascalize(string $string): string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $string)));
    }

    /**
     * Resolves the target class name, full namespace, and target file path.
     *
     * @return array{className: string, fullNamespace: string, subPath: string, filePath: string}
     */
    public function resolveTarget(
        string $rawName,
        string $baseNamespace,
        string $basePath,
        ?string $forceSuffix = null
    ): array {
        $normalized = str_replace('\\', '/', trim($rawName));
        $parts = array_values(array_filter(explode('/', $normalized)));
        $rawClass = array_pop($parts) ?? 'ScaffoldedClass';
        $className = $this->pascalize($rawClass);

        if ($forceSuffix !== null && ! str_ends_with($className, $forceSuffix)) {
            $className .= $forceSuffix;
        }

        $subNamespace = implode('\\', array_map(fn (string $p): string => $this->pascalize($p), $parts));
        $fullNamespace = $subNamespace !== '' ? rtrim($baseNamespace, '\\').'\\'.$subNamespace : rtrim($baseNamespace, '\\');

        $subPath = implode('/', array_map(fn (string $p): string => $this->pascalize($p), $parts));
        $filePath = rtrim($basePath, '/\\').'/'.($subPath !== '' ? $subPath.'/' : '').$className.'.php';

        return [
            'className' => $className,
            'fullNamespace' => $fullNamespace,
            'subPath' => $subPath,
            'filePath' => $filePath,
        ];
    }

    /**
     * Resolve the translation key prefix, relative lang file path, and humanized name.
     *
     * @return array{langKey: string, relativePath: string, humanName: string}
     */
    public function resolveLangTarget(string $type, string $rawName, ?string $forceSuffix = null): array
    {
        $normalized = str_replace('\\', '/', trim($rawName));
        $parts = array_values(array_filter(explode('/', $normalized)));
        $rawClass = array_pop($parts) ?? ($type === 'requests' ? 'Request' : 'Screen');
        $className = $this->pascalize($rawClass);

        if ($forceSuffix !== null && ! str_ends_with($className, $forceSuffix)) {
            $className .= $forceSuffix;
        }

        $snakeParts = array_map(fn (string $p): string => Str::snake($p), $parts);
        $snakeClass = Str::snake($className);

        $prefix = ($snakeParts !== [] ? implode('/', $snakeParts).'/' : '').$snakeClass;
        $dotKey = ($snakeParts !== [] ? implode('.', $snakeParts).'.' : '').$snakeClass;

        $relativePath = $type.'/'.$prefix.'.php';
        $langKey = $type.'.'.$dotKey;
        $humanName = Str::headline(str_replace(['_', '-'], ' ', $rawClass));

        return [
            'langKey' => $langKey,
            'relativePath' => $relativePath,
            'humanName' => $humanName,
        ];
    }

    /**
     * Resolve active locales configured for the application.
     *
     * @return list<string>
     */
    public function resolveActiveLocales(): array
    {
        $locales = [];

        foreach ($this->config->i18n->languages as $lang) {
            if ($lang->active) {
                $locales[] = $lang->code;
            }
        }

        if ($locales === []) {
            $default = $this->config->i18n->defaultLocale;
            $fallback = $this->config->i18n->fallbackLocale;
            $appLocale = config('app.locale', 'en');
            $rawList = [$default, $fallback, is_string($appLocale) ? $appLocale : 'en', 'es', 'en'];

            /** @var list<string> $locales */
            $locales = array_values(array_unique(array_filter($rawList, fn (string $v): bool => $v !== '')));
        }

        return $locales;
    }

    /**
     * Generate language files for all active locales.
     *
     * @param  array<string, string>  $replacements
     * @return list<string> Absolute paths of created language files
     */
    public function generateLangFiles(
        string $element,
        string $relativeLangPath,
        array $replacements,
        bool $force = false
    ): array {
        $baseLangPath = function_exists('lang_path') ? lang_path() : base_path('lang');
        $locales = $this->resolveActiveLocales();
        $createdFiles = [];

        foreach ($locales as $locale) {
            $stubCandidate = "scaffolding/lang_{$element}_{$locale}.php.stub";
            $stubPath = $this->stubsPath($stubCandidate);
            if (! $this->filesystem->exists($stubPath)) {
                $stubPath = $this->stubsPath("scaffolding/lang_{$element}_en.php.stub");
            }

            if (! $this->filesystem->exists($stubPath)) {
                continue;
            }

            $filePath = rtrim($baseLangPath, '/\\').'/'.$locale.'/'.ltrim($relativeLangPath, '/\\');

            if ($this->filesystem->exists($filePath) && ! $force) {
                $this->warn("Language file '{$filePath}' already exists. Skipped (use --force to overwrite).");

                continue;
            }

            $content = $this->filesystem->get($stubPath);
            foreach ($replacements as $key => $val) {
                $content = str_replace("{{ {$key} }}", $val, $content);
            }

            $directory = dirname($filePath);
            if (! $this->filesystem->isDirectory($directory)) {
                $this->filesystem->makeDirectory($directory, 0755, true);
            }

            $this->filesystem->put($filePath, $content);
            $createdFiles[] = $filePath;
        }

        return $createdFiles;
    }

    /**
     * Generate file from a stub with placeholder replacements.
     *
     * @param  array<string, string>  $replacements
     */
    public function generateFromStub(
        string $stubRelativePath,
        string $filePath,
        array $replacements,
        bool $force = false
    ): bool {
        $stubPath = $this->stubsPath($stubRelativePath);

        if (! $this->filesystem->exists($stubPath)) {
            $this->error("Stub file '{$stubPath}' does not exist.");

            return false;
        }

        if ($this->filesystem->exists($filePath) && ! $force) {
            $this->error("File '{$filePath}' already exists.");

            return false;
        }

        $content = $this->filesystem->get($stubPath);
        foreach ($replacements as $key => $val) {
            $content = str_replace("{{ {$key} }}", $val, $content);
        }

        $directory = dirname($filePath);
        if (! $this->filesystem->isDirectory($directory)) {
            $this->filesystem->makeDirectory($directory, 0755, true);
        }

        $this->filesystem->put($filePath, $content);

        return true;
    }
}
