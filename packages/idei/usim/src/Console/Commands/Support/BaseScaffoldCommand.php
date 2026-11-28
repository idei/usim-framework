<?php

namespace Idei\Usim\Console\Commands\Support;

use Idei\Usim\Support\UsimConfig;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

abstract class BaseScaffoldCommand extends Command
{
    /**
     * When true, bypasses published application stubs in `stubs/usim/` and uses package defaults.
     */
    protected bool $usePackageStubsOnly = false;

    public function __construct(
        protected UsimConfig $config,
        protected Filesystem $filesystem
    ) {
        parent::__construct();
    }

    /**
     * Resolve the absolute path to a stub file, checking published overrides in
     * `base_path('stubs/usim/...')` first (file-by-file cascade) before falling back to package stubs.
     */
    public function stubsPath(string $path = ''): string
    {
        $normalized = str_replace('\\', '/', ltrim($path, '/\\'));

        if (! $this->usePackageStubsOnly && $normalized !== '' && function_exists('base_path')) {
            $scaffoldingRelative = str_starts_with($normalized, 'scaffolding/')
                ? substr($normalized, strlen('scaffolding/'))
                : $normalized;

            $candidates = array_unique([
                base_path('stubs/usim/'.$scaffoldingRelative),
                base_path('stubs/usim/'.$normalized),
            ]);

            foreach ($candidates as $candidate) {
                if ($this->filesystem->exists($candidate)) {
                    return $candidate;
                }
            }
        }

        return dirname(__DIR__, 4).'/stubs/'.$normalized;
    }

    /**
     * Check whether a given relative stub currently resolves to a user-published override in `stubs/usim/`.
     */
    public function hasPublishedStubOverride(string $path): bool
    {
        if ($this->usePackageStubsOnly || ! function_exists('base_path')) {
            return false;
        }

        $resolved = str_replace('\\', '/', $this->stubsPath($path));
        $customRoot = str_replace('\\', '/', base_path('stubs/usim/'));

        return str_starts_with($resolved, $customRoot);
    }

    /**
     * Render a stub template string with indent-aware multiline block replacement and inline placeholders.
     *
     * Standalone placeholder lines (`    {{ block }}`) preserve leading indentation across every line
     * of a multiline replacement and are cleanly omitted when the replacement value is empty.
     *
     * @param  array<string, string>  $replacements
     */
    public function renderStubContent(string $template, array $replacements): string
    {
        $lines = preg_split("/\r\n|\n|\r/", $template);
        if ($lines === false) {
            $lines = [$template];
        }

        $renderedLines = [];

        foreach ($lines as $line) {
            if (preg_match('/^([ \t]*)\{\{\s*([a-zA-Z0-9_]+)\s*\}\}[ \t]*$/', $line, $matches) === 1) {
                $indent = $matches[1];
                $key = $matches[2];

                if (array_key_exists($key, $replacements)) {
                    $val = $replacements[$key];
                    if ($val === '') {
                        continue;
                    }

                    $subLines = preg_split("/\r\n|\n|\r/", $val);
                    if ($subLines === false) {
                        $subLines = [$val];
                    }

                    foreach ($subLines as $subLine) {
                        $renderedLines[] = $subLine === '' ? '' : $indent.$subLine;
                    }

                    continue;
                }
            }

            $renderedLines[] = $line;
        }

        $content = implode("\n", $renderedLines);

        foreach ($replacements as $key => $val) {
            $content = str_replace(["{{ {$key} }}", "{{{$key}}}"], $val, $content);
        }

        return $content;
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

            $content = $this->renderStubContent($this->filesystem->get($stubPath), $replacements);

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

        $content = $this->renderStubContent($this->filesystem->get($stubPath), $replacements);

        $directory = dirname($filePath);
        if (! $this->filesystem->isDirectory($directory)) {
            $this->filesystem->makeDirectory($directory, 0755, true);
        }

        $this->filesystem->put($filePath, $content);

        return true;
    }
}
