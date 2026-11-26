<?php

namespace Idei\Usim\Console\Commands;

use Idei\Usim\Console\Commands\Support\BaseScaffoldCommand;
use Idei\Usim\Console\Commands\Support\DeclarativeScreenGenerator;
use Idei\Usim\Support\UsimConfig;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class UsimScreenScaffoldCommand extends BaseScaffoldCommand
{
    protected $signature = 'usim:screen
        {name? : The name of the screen to scaffold (e.g. Orders/OrderExplorer)}
        {--type=basic : The template preset to use (basic, workspace, modal, kiosk)}
        {--layout= : Page layout (default, workbench, main, none, or FQCN)}
        {--slots= : Comma-separated Workbench slots to project (top_bar,left_panel,right_panel,bottom_panel,status_bar,status_bar_right,all,none)}
        {--visibility=public : Screen visibility (public, authenticated, guest)}
        {--yaml : Generate a declarative sibling .layout.yaml file}
        {--imperative : Force imperative PHP startingUI() instead of .layout.yaml}
        {--request : Also scaffold a matching UIRequest}
        {--i18n : Generate corresponding language files in lang/ compatible with t() / i18n: auto}
        {--test : Generate a sibling Pest feature test in tests/Feature/}
        {--discover : Run php artisan usim:discover immediately after scaffolding}
        {--interactive : Launch guided interactive wizard}
        {--force : Overwrite existing files if they exist}';

    /**
     * The console command aliases.
     *
     * @var array<int, string>
     */
    protected $aliases = ['usim:scaf'];

    protected $description = 'Scaffold a new USIM screen (optionally with page layout, declarative .layout.yaml, Workbench slots, i18n, and Pest test).';

    public function __construct(
        UsimConfig $config,
        Filesystem $filesystem,
        protected DeclarativeScreenGenerator $declarativeGenerator = new DeclarativeScreenGenerator
    ) {
        parent::__construct($config, $filesystem);
    }

    public function handle(): int
    {
        $rawName = $this->argument('name');
        $name = \is_string($rawName) ? trim($rawName) : '';
        $interactive = (bool) $this->option('interactive') || $name === '';

        $type = (string) $this->option('type');
        $rawLayout = $this->option('layout');
        $layoutOption = \is_string($rawLayout) && trim($rawLayout) !== '' ? trim($rawLayout) : null;
        $rawSlots = $this->option('slots');
        $slotsOption = \is_string($rawSlots) && trim($rawSlots) !== '' ? trim($rawSlots) : null;
        $visibility = strtolower(trim((string) $this->option('visibility')));
        $yaml = (bool) $this->option('yaml');
        $imperative = (bool) $this->option('imperative');
        $force = (bool) $this->option('force');
        $i18n = (bool) $this->option('i18n');
        $withRequest = (bool) $this->option('request');
        $withTest = (bool) $this->option('test');
        $runDiscover = (bool) $this->option('discover');

        if ($interactive) {
            if ($name === '') {
                $askedName = $this->ask('Screen name (e.g. Orders/OrderExplorer)');
                $name = \is_string($askedName) ? trim($askedName) : '';
            }

            if ($name === '') {
                $this->error('A screen name is required.');

                return 1;
            }

            if ($layoutOption === null) {
                $chosenLayout = $this->choice(
                    'Which page layout should wrap this screen?',
                    ['default', 'workbench', 'main', 'none'],
                    'workbench'
                );
                $layoutOption = \is_string($chosenLayout) ? $chosenLayout : 'default';
            }

            if ($type === 'basic') {
                $defaultType = $layoutOption === 'workbench' ? 'workspace' : ($layoutOption === 'none' ? 'modal' : 'basic');
                $chosenType = $this->choice(
                    'Which template preset do you want?',
                    ['basic', 'workspace', 'modal', 'kiosk'],
                    $defaultType
                );
                $type = \is_string($chosenType) ? $chosenType : 'basic';
            }

            if ($slotsOption === null && ($layoutOption === 'workbench' || $type === 'workspace')) {
                $chosenSlots = $this->ask(
                    'Workbench slots to project (comma-separated: top_bar,left_panel,right_panel,bottom_panel,status_bar,status_bar_right or none)',
                    'top_bar,left_panel,right_panel,status_bar'
                );
                $slotsOption = \is_string($chosenSlots) ? trim($chosenSlots) : 'none';
            }

            $chosenVisibility = $this->choice(
                'Screen visibility?',
                ['public', 'authenticated', 'guest'],
                $layoutOption === 'workbench' ? 'authenticated' : 'public'
            );
            $visibility = \is_string($chosenVisibility) ? $chosenVisibility : 'public';

            if (! $imperative && ! $yaml) {
                $yaml = $this->confirm('Generate declarative sibling .layout.yaml?', true);
                if (! $yaml) {
                    $imperative = true;
                }
            }

            if (! $i18n) {
                $i18n = $this->confirm('Generate i18n language files (en/es)?', true);
            }

            if (! $withTest) {
                $withTest = $this->confirm('Generate a Pest feature test?', true);
            }

            if (! $runDiscover) {
                $runDiscover = $this->confirm('Run php artisan usim:discover now?', false);
            }
        }

        if (! isset(DeclarativeScreenGenerator::VISIBILITY_ENUM_MAP[$visibility])) {
            $this->error("Invalid visibility '{$visibility}'. Allowed values: public, authenticated, guest.");

            return 1;
        }
        $visibilityEnum = DeclarativeScreenGenerator::VISIBILITY_ENUM_MAP[$visibility];

        $this->info('Scaffolding a new screen...');

        $target = $this->resolveTarget(
            $name,
            $this->config->screensNamespace,
            $this->config->screensPath
        );
        $langTarget = $this->resolveLangTarget('screens', $name);

        $isDeclarative = ! $imperative && (
            $yaml
            || $layoutOption !== null
            || $slotsOption !== null
            || in_array($type, ['workspace', 'modal', 'kiosk'], true)
        );

        $layoutInfo = $this->declarativeGenerator->resolveLayoutDeclaration($layoutOption, $type);
        $slots = [];

        if ($isDeclarative) {
            if (! in_array($type, DeclarativeScreenGenerator::DECLARATIVE_TYPES, true)) {
                $this->error("Template preset '{$type}' does not exist. Available presets: ".implode(', ', DeclarativeScreenGenerator::DECLARATIVE_TYPES).'.');

                return 1;
            }

            if ($slotsOption !== null) {
                $parsed = $this->declarativeGenerator->parseSlots($slotsOption);
                if ($parsed['invalid'] !== []) {
                    $this->error('Unknown Workbench slot(s): '.implode(', ', $parsed['invalid']).'. Allowed: '.implode(', ', DeclarativeScreenGenerator::SUPPORTED_SLOTS).', all, none.');

                    return 1;
                }
                $slots = $parsed['slots'];
            } elseif ($type === 'workspace') {
                $slots = ['top_bar', 'left_panel', 'right_panel', 'status_bar'];
            } elseif ($layoutInfo['normalized'] === 'workbench') {
                $slots = ['left_panel', 'right_panel', 'status_bar'];
            }

            $yamlPath = dirname($target['filePath']).'/'.Str::kebab($target['className']).'.layout.yaml';

            if (! $force) {
                if ($this->filesystem->exists($target['filePath'])) {
                    $this->error("File '{$target['filePath']}' already exists.");

                    return 1;
                }
                if ($this->filesystem->exists($yamlPath)) {
                    $this->error("File '{$yamlPath}' already exists.");

                    return 1;
                }
            }

            $phpContent = $this->declarativeGenerator->buildScreenPhp(
                namespace: $target['fullNamespace'],
                className: $target['className'],
                humanName: $langTarget['humanName'],
                type: $type,
                layoutDeclaration: $layoutInfo['declaration'],
                layoutImport: $layoutInfo['import'],
                visibilityEnum: $visibilityEnum,
                slots: $slots,
                i18n: $i18n,
            );

            $yamlContent = $this->declarativeGenerator->buildLayoutYaml(
                className: $target['className'],
                humanName: $langTarget['humanName'],
                type: $type,
                slots: $slots,
                i18n: $i18n,
            );

            $this->writeTargetFile($target['filePath'], $phpContent);
            $this->writeTargetFile($yamlPath, $yamlContent);

            $this->info("Screen '{$target['className']}' has been scaffolded at '{$target['filePath']}'.");
            $this->info("Layout YAML created: '{$yamlPath}'");

            if ($i18n) {
                $createdLangFiles = $this->writeDeclarativeLangFiles(
                    relativeLangPath: $langTarget['relativePath'],
                    humanName: $langTarget['humanName'],
                    type: $type,
                    slots: $slots,
                    force: $force,
                );

                foreach ($createdLangFiles as $langFile) {
                    $this->info("Translation created: '{$langFile}' (Key: '{$langTarget['langKey']}')");
                }
            }
        } else {
            $stubRelativePath = "scaffolding/{$type}.php.stub";
            if ($i18n) {
                $i18nStubCandidate = "scaffolding/{$type}_i18n.php.stub";
                if ($this->filesystem->exists($this->stubsPath($i18nStubCandidate))) {
                    $stubRelativePath = $i18nStubCandidate;
                }
            }

            $replacements = [
                'name' => $target['className'],
                'namespace' => $target['fullNamespace'],
                'langKey' => $langTarget['langKey'],
                'humanName' => $langTarget['humanName'],
            ];

            $success = $this->generateFromStub(
                $stubRelativePath,
                $target['filePath'],
                $replacements,
                $force
            );

            if (! $success) {
                return 1;
            }

            $this->applyImperativeOverrides($target['filePath'], $visibilityEnum, $layoutInfo);

            $this->info("Screen '{$target['className']}' has been scaffolded at '{$target['filePath']}'.");

            if ($i18n) {
                $createdLangFiles = $this->generateLangFiles(
                    element: 'screen',
                    relativeLangPath: $langTarget['relativePath'],
                    replacements: $replacements,
                    force: $force
                );

                foreach ($createdLangFiles as $langFile) {
                    $this->info("Translation created: '{$langFile}' (Key: '{$langTarget['langKey']}')");
                }
            }
        }

        if ($withTest) {
            $screenFqcn = $target['fullNamespace'].'\\'.$target['className'];
            $testSubPath = $target['subPath'] !== '' ? $target['subPath'].'/' : '';
            $testPath = base_path('tests/Feature/'.$testSubPath.$target['className'].'ScreenTest.php');

            if ($this->filesystem->exists($testPath) && ! $force) {
                $this->warn("Test file '{$testPath}' already exists. Skipped (use --force to overwrite).");
            } else {
                $testContent = $this->declarativeGenerator->buildFeatureTestPhp(
                    screenFqcn: $screenFqcn,
                    className: $target['className'],
                    humanName: $langTarget['humanName'],
                    normalizedLayout: $layoutInfo['normalized'],
                    visibilityEnum: $visibilityEnum,
                    slots: $slots,
                );
                $this->writeTargetFile($testPath, $testContent);
                $this->info("Test created: '{$testPath}'");
            }
        }

        $routePath = $this->resolvePreviewRoutePath($target['subPath'], $target['className']);
        $this->line("Route path: {$routePath}");

        if ($runDiscover) {
            $this->call('usim:discover');
        } else {
            $this->info("Don't forget to make php artisan usim:discover to register the new screen.");
        }

        if ($withRequest) {
            $normalized = str_replace('\\', '/', trim($name));
            $parts = array_values(array_filter(explode('/', $normalized)));
            $rawClass = array_pop($parts) ?? 'Screen';
            $requestName = ($parts !== [] ? implode('/', $parts).'/' : '').$rawClass.'Request';

            $this->call('usim:request', [
                'name' => $requestName,
                '--force' => $force,
                '--i18n' => $i18n,
            ]);
        }

        return 0;
    }

    protected function writeTargetFile(string $filePath, string $content): void
    {
        $directory = dirname($filePath);
        if (! $this->filesystem->isDirectory($directory)) {
            $this->filesystem->makeDirectory($directory, 0755, true);
        }

        $this->filesystem->put($filePath, $content);
    }

    /**
     * @param  list<string>  $slots
     * @return list<string>
     */
    protected function writeDeclarativeLangFiles(
        string $relativeLangPath,
        string $humanName,
        string $type,
        array $slots,
        bool $force
    ): array {
        $baseLangPath = function_exists('lang_path') ? lang_path() : base_path('lang');
        $locales = $this->resolveActiveLocales();
        $created = [];

        foreach ($locales as $locale) {
            $filePath = rtrim($baseLangPath, '/\\').'/'.$locale.'/'.ltrim($relativeLangPath, '/\\');

            if ($this->filesystem->exists($filePath) && ! $force) {
                $this->warn("Language file '{$filePath}' already exists. Skipped (use --force to overwrite).");

                continue;
            }

            $content = $this->declarativeGenerator->buildLangPhp($locale, $humanName, $type, $slots);
            $this->writeTargetFile($filePath, $content);
            $created[] = $filePath;
        }

        return $created;
    }

    /**
     * Apply optional `--visibility` and `--layout` declarations when `--imperative` is used.
     *
     * @param  array{declaration: ?string, import: ?string, normalized: string}  $layoutInfo
     */
    protected function applyImperativeOverrides(string $filePath, string $visibilityEnum, array $layoutInfo): void
    {
        if ($visibilityEnum === 'PUBLIC' && $layoutInfo['declaration'] === null) {
            return;
        }

        $content = $this->filesystem->get($filePath);

        if ($visibilityEnum !== 'PUBLIC') {
            $content = str_replace(
                'public static Visibility $visibility = Visibility::PUBLIC;',
                "public static Visibility \$visibility = Visibility::{$visibilityEnum};",
                $content
            );
        }

        if ($layoutInfo['import'] !== null && ! str_contains($content, "use {$layoutInfo['import']};")) {
            $content = str_replace(
                "use Idei\\Usim\\Screen;\n",
                "use {$layoutInfo['import']};\nuse Idei\\Usim\\Screen;\n",
                $content
            );
        }

        if ($layoutInfo['declaration'] !== null && ! str_contains($content, 'public static ?string $layout')) {
            $content = str_replace(
                "public static Visibility \$visibility = Visibility::{$visibilityEnum};",
                "public static Visibility \$visibility = Visibility::{$visibilityEnum};\n\n    {$layoutInfo['declaration']}",
                $content
            );
        }

        $this->filesystem->put($filePath, $content);
    }

    protected function resolvePreviewRoutePath(string $subPath, string $className): string
    {
        $segments = array_values(array_filter(explode('/', $subPath)));
        $segments[] = $className;

        return '/'.implode('/', array_map(static fn (string $seg): string => Str::kebab($seg), $segments));
    }
}
