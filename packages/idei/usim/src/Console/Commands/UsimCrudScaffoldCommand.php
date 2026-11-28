<?php

declare(strict_types=1);

namespace Idei\Usim\Console\Commands;

use Idei\Usim\Console\Commands\Support\BaseScaffoldCommand;
use Idei\Usim\Console\Commands\Support\Crud\CrudFieldDescriptor;
use Idei\Usim\Console\Commands\Support\Crud\CrudSchemaInspector;
use Idei\Usim\Console\Commands\Support\Crud\CrudServiceMetadata;
use Idei\Usim\Console\Commands\Support\Crud\CrudStubRenderer;
use Idei\Usim\Console\Commands\Support\DeclarativeScreenGenerator;
use Idei\Usim\Support\UsimConfig;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Scaffolds capability-driven Master-Detail USIM Screens backed by DTO-oriented Services.
 *
 * Supports two workflows:
 * 1. Existing Service (`--service=...`): inspects capabilities (`Listable`, `Detailable`,
 *    `CreatableDetail`, `UpdatableDetail`, `DeletableDetail`) and DTO properties/relations via Reflection.
 * 2. Rapid Prototyping (`--with-stack=json|session|memory --fields=...`): generates the clean
 *    `DTO + RepositoryInterface + MockRepository + Service` stack first, then scaffolds the UI layer.
 */
class UsimCrudScaffoldCommand extends BaseScaffoldCommand
{
    private const SUPPORTED_STACK_DRIVERS = ['json', 'session', 'memory'];

    protected $signature = 'usim:crud
        {name? : The name of the Master-Detail screen to scaffold (e.g. Catalog/ProductManager)}
        {--service= : Existing Service class (FQCN or relative to App\\Services, e.g. Catalog/ProductService)}
        {--with-stack= : Scaffold DTO + Repository contract + Mock Repository + Service (json, session, memory)}
        {--fields= : Comma-separated DTO fields when using --with-stack (e.g. sku:string,name:string,price:float,active:bool)}
        {--layout=workbench : Page layout to wrap this screen (workbench, default, main, none, or FQCN)}
        {--visibility=authenticated : Screen visibility (public, authenticated, guest)}
        {--no-i18n : Skip generating translation files in lang/}
        {--no-test : Skip generating a sibling Pest feature test}
        {--package-stubs : Ignore published overrides in stubs/usim/ and use built-in package stubs}
        {--discover : Run php artisan usim:discover immediately after scaffolding}
        {--dry-run : Preview detected capabilities and planned files without writing to disk}
        {--interactive : Launch guided interactive wizard}
        {--force : Overwrite existing files if they exist}';

    protected $description = 'Scaffold a capability-driven Master-Detail USIM screen from a DTO-based Service (with optional mock Repository stack).';

    public function __construct(
        UsimConfig $config,
        Filesystem $filesystem,
        protected CrudSchemaInspector $inspector = new CrudSchemaInspector,
        protected CrudStubRenderer $renderer = new CrudStubRenderer,
        protected DeclarativeScreenGenerator $layoutHelper = new DeclarativeScreenGenerator,
    ) {
        parent::__construct($config, $filesystem);
    }

    public function handle(): int
    {
        $this->usePackageStubsOnly = (bool) $this->option('package-stubs');

        $rawName = $this->argument('name');
        $name = is_string($rawName) ? trim($rawName) : '';
        $interactive = ((bool) $this->option('interactive') || $name === '') && ! (bool) $this->option('no-interaction');

        $rawService = $this->option('service');
        $serviceOption = is_string($rawService) && trim($rawService) !== '' ? trim($rawService) : null;

        $rawStack = $this->option('with-stack');
        $stackDriver = is_string($rawStack) && trim($rawStack) !== '' ? strtolower(trim($rawStack)) : null;

        $rawFields = $this->option('fields');
        $fieldsOption = is_string($rawFields) && trim($rawFields) !== '' ? trim($rawFields) : null;

        $rawLayout = $this->option('layout');
        $layoutOption = is_string($rawLayout) && trim($rawLayout) !== '' ? trim($rawLayout) : 'workbench';

        $visibility = strtolower(trim((string) $this->option('visibility')));
        $i18n = ! (bool) $this->option('no-i18n');
        $withTest = ! (bool) $this->option('no-test');
        $runDiscover = (bool) $this->option('discover');
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        if ($interactive) {
            [$name, $serviceOption, $stackDriver, $fieldsOption, $visibility, $i18n, $withTest, $runDiscover] = $this->runInteractiveWizard(
                $name,
                $serviceOption,
                $stackDriver,
                $fieldsOption,
                $visibility,
                $i18n,
                $withTest,
                $runDiscover,
            );
        }

        if ($name === '') {
            $this->error('A screen name is required (e.g. php artisan usim:crud Catalog/ProductManager --service=Catalog/ProductService).');

            return self::FAILURE;
        }

        if (! isset(DeclarativeScreenGenerator::VISIBILITY_ENUM_MAP[$visibility])) {
            $this->error("Invalid visibility '{$visibility}'. Allowed values: public, authenticated, guest.");

            return self::FAILURE;
        }
        $visibilityEnum = DeclarativeScreenGenerator::VISIBILITY_ENUM_MAP[$visibility];

        if ($stackDriver !== null && ! in_array($stackDriver, self::SUPPORTED_STACK_DRIVERS, true)) {
            $this->error("Invalid --with-stack driver '{$stackDriver}'. Allowed values: ".implode(', ', self::SUPPORTED_STACK_DRIVERS).'.');

            return self::FAILURE;
        }

        if ($serviceOption === null && $stackDriver === null) {
            $this->error('Either --service=<ServiceClass> or --with-stack=<json|session|memory> must be provided.');

            return self::FAILURE;
        }

        $screenTarget = $this->resolveTarget($name, $this->config->screensNamespace, $this->config->screensPath);
        $screenLangTarget = $this->resolveLangTarget('screens', $name);
        $layoutInfo = $this->layoutHelper->resolveLayoutDeclaration($layoutOption, 'workspace');

        /** @var list<array{label: string, path: string, content: string}> $plannedFiles */
        $plannedFiles = [];
        $bindingHint = null;

        try {
            if ($stackDriver !== null) {
                [$meta, $stackFiles, $bindingHint] = $this->planStackScaffolding(
                    screenName: $name,
                    serviceOption: $serviceOption,
                    driver: $stackDriver,
                    rawFields: $fieldsOption,
                );
                $plannedFiles = array_merge($plannedFiles, $stackFiles);
            } else {
                /** @var string $serviceOption */
                $serviceFqcn = $this->inspector->resolveServiceFqcn($serviceOption);
                $overrideFields = $fieldsOption !== null
                    ? $this->inspector->parseFieldsSpecification($fieldsOption)
                    : null;
                $meta = $this->inspector->inspectService($serviceFqcn, $overrideFields);
            }
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $uiFiles = $this->planUiScaffolding(
            rawScreenName: $name,
            screenTarget: $screenTarget,
            screenLangTarget: $screenLangTarget,
            visibilityEnum: $visibilityEnum,
            layoutInfo: $layoutInfo,
            meta: $meta,
            i18n: $i18n,
            withTest: $withTest,
        );
        $plannedFiles = array_merge($plannedFiles, $uiFiles);

        $this->renderCapabilitiesSummary($meta);

        if ($dryRun) {
            $this->info('Dry-run mode enabled. Planned files:');
            foreach ($plannedFiles as $planned) {
                $this->line("  • [{$planned['label']}] {$planned['path']}");
            }

            return self::SUCCESS;
        }

        if (! $force) {
            foreach ($plannedFiles as $planned) {
                if ($this->filesystem->exists($planned['path'])) {
                    $this->error("File '{$planned['path']}' already exists. Use --force to overwrite.");

                    return self::FAILURE;
                }
            }
        }

        foreach ($plannedFiles as $planned) {
            $this->writePlannedFile($planned['path'], $planned['content']);
            $this->info("{$planned['label']} created: '{$planned['path']}'");
        }

        if ($bindingHint !== null) {
            $this->line("Repository binding tip: {$bindingHint}");
        }

        $routePath = '/'.implode('/', array_filter([
            $screenTarget['subPath'] !== '' ? implode('/', array_map(static fn (string $seg): string => Str::kebab($seg), explode('/', $screenTarget['subPath']))) : '',
            Str::kebab($screenTarget['className']),
        ]));
        $this->line("Route path: {$routePath}");

        if ($runDiscover) {
            $this->call('usim:discover');
        } else {
            $this->info("Don't forget to run php artisan usim:discover to register the new screen.");
        }

        return self::SUCCESS;
    }

    /**
     * @return array{0: CrudServiceMetadata, 1: list<array{label: string, path: string, content: string}>, 2: string}
     */
    protected function planStackScaffolding(
        string $screenName,
        ?string $serviceOption,
        string $driver,
        ?string $rawFields,
    ): array {
        $entityPath = $this->inferEntityBaseName($screenName, $serviceOption);
        $fields = $this->inspector->parseFieldsSpecification($rawFields);

        $dtoTarget = $this->resolveTarget($entityPath, 'App\\DTOs', app_path('DTOs'), forceSuffix: 'DTO');
        $repoInterfaceTarget = $this->resolveTarget($entityPath, 'App\\Contracts\\Repositories', app_path('Contracts/Repositories'), forceSuffix: 'RepositoryInterface');

        $driverPrefix = ucfirst($driver);
        $normalizedParts = array_values(array_filter(explode('/', str_replace('\\', '/', $entityPath))));
        $entityShort = $this->pascalize((string) array_pop($normalizedParts));
        $subPrefix = $normalizedParts !== [] ? implode('/', $normalizedParts).'/' : '';
        $repoConcreteName = $subPrefix.$driverPrefix.$entityShort.'Repository';
        $repoConcreteTarget = $this->resolveTarget($repoConcreteName, 'App\\Repositories', app_path('Repositories'));

        $serviceTargetName = $serviceOption ?? $entityPath;
        $serviceTarget = $this->resolveTarget($serviceTargetName, 'App\\Services', app_path('Services'), forceSuffix: 'Service');

        /** @var class-string $dtoFqcn */
        $dtoFqcn = $dtoTarget['fullNamespace'].'\\'.$dtoTarget['className'];
        $repoInterfaceFqcn = $repoInterfaceTarget['fullNamespace'].'\\'.$repoInterfaceTarget['className'];
        $repoConcreteFqcn = $repoConcreteTarget['fullNamespace'].'\\'.$repoConcreteTarget['className'];
        /** @var class-string $serviceFqcn */
        $serviceFqcn = $serviceTarget['fullNamespace'].'\\'.$serviceTarget['className'];

        $entitySlug = Str::snake(Str::pluralStudly($entityShort));

        $dtoContent = $this->renderStubFile(
            'scaffolding/crud/dto.php.stub',
            $this->renderer->buildDtoReplacements($dtoTarget['fullNamespace'], $dtoTarget['className'], $fields)
        );

        $repoInterfaceContent = $this->renderStubFile(
            'scaffolding/crud/repository_interface.php.stub',
            [
                'namespace' => $repoInterfaceTarget['fullNamespace'],
                'class' => $repoInterfaceTarget['className'],
                'dto_import' => "use {$dtoFqcn};",
                'dto_class' => $dtoTarget['className'],
            ]
        );

        $repoMockContent = $this->renderStubFile(
            "scaffolding/crud/repository_{$driver}.php.stub",
            $this->renderer->buildRepositoryMockReplacements(
                namespace: $repoConcreteTarget['fullNamespace'],
                repoClass: $repoConcreteTarget['className'],
                interfaceFqcn: $repoInterfaceFqcn,
                dtoFqcn: $dtoFqcn,
                entitySlug: $entitySlug,
                fields: $fields,
            )
        );

        $serviceContent = $this->renderStubFile(
            'scaffolding/crud/service.php.stub',
            $this->renderer->buildServiceReplacements(
                namespace: $serviceTarget['fullNamespace'],
                serviceClass: $serviceTarget['className'],
                dtoFqcn: $dtoFqcn,
                repoInterfaceFqcn: $repoInterfaceFqcn,
                defaultRepoFqcn: $repoConcreteFqcn,
                fields: $fields,
            )
        );

        $meta = new CrudServiceMetadata(
            serviceFqcn: $serviceFqcn,
            dtoFqcn: $dtoFqcn,
            isListable: true,
            isDetailable: true,
            isCreatable: true,
            isUpdatable: true,
            isDeletable: true,
            fields: $fields,
        );

        $files = [
            ['label' => 'DTO', 'path' => $dtoTarget['filePath'], 'content' => $dtoContent],
            ['label' => 'Repository Contract', 'path' => $repoInterfaceTarget['filePath'], 'content' => $repoInterfaceContent],
            ['label' => ucfirst($driver).' Repository', 'path' => $repoConcreteTarget['filePath'], 'content' => $repoMockContent],
            ['label' => 'Service', 'path' => $serviceTarget['filePath'], 'content' => $serviceContent],
        ];

        $bindingHint = "\$this->app->bind(\\{$repoInterfaceFqcn}::class, \\{$repoConcreteFqcn}::class);";

        return [$meta, $files, $bindingHint];
    }

    /**
     * @param  array{className: string, fullNamespace: string, subPath: string, filePath: string}  $screenTarget
     * @param  array{langKey: string, relativePath: string, humanName: string}  $screenLangTarget
     * @param  array{declaration: ?string, import: ?string, normalized: string}  $layoutInfo
     * @return list<array{label: string, path: string, content: string}>
     */
    protected function planUiScaffolding(
        string $rawScreenName,
        array $screenTarget,
        array $screenLangTarget,
        string $visibilityEnum,
        array $layoutInfo,
        CrudServiceMetadata $meta,
        bool $i18n,
        bool $withTest,
    ): array {
        $files = [];
        $requestFqcn = null;
        $requestTarget = null;
        $requestLangTarget = null;

        if ($meta->isMutable()) {
            $requestName = ($screenTarget['subPath'] !== '' ? $screenTarget['subPath'].'/' : '').'Save'.$screenTarget['className'].'Request';
            $requestTarget = $this->resolveTarget(
                $requestName,
                $this->config->requestsNamespace,
                $this->config->requestsPath,
                forceSuffix: 'Request',
            );
            $requestLangTarget = $this->resolveLangTarget('requests', $requestName, forceSuffix: 'Request');
            $requestFqcn = $requestTarget['fullNamespace'].'\\'.$requestTarget['className'];
        }

        $screenStub = $meta->isReadOnly()
            ? 'scaffolding/crud/screen_readonly.php.stub'
            : 'scaffolding/crud/screen.php.stub';

        $screenPhp = $this->renderStubFile(
            $screenStub,
            $this->renderer->buildScreenReplacements(
                namespace: $screenTarget['fullNamespace'],
                className: $screenTarget['className'],
                visibilityEnum: $visibilityEnum,
                layoutInfo: $layoutInfo,
                meta: $meta,
                requestFqcn: $requestFqcn,
            )
        );
        $files[] = ['label' => 'Screen', 'path' => $screenTarget['filePath'], 'content' => $screenPhp];

        $yamlPath = dirname($screenTarget['filePath']).'/'.Str::kebab($screenTarget['className']).'.layout.yaml';
        $yamlContent = $this->renderStubFile(
            'scaffolding/crud/screen.layout.yaml.stub',
            $this->renderer->buildLayoutYamlReplacements($screenTarget['className'], $meta)
        );
        $files[] = ['label' => 'Layout YAML', 'path' => $yamlPath, 'content' => $yamlContent];

        if ($requestTarget !== null) {
            $requestPhp = $this->renderStubFile(
                'scaffolding/crud/request.php.stub',
                $this->renderer->buildRequestReplacements(
                    namespace: $requestTarget['fullNamespace'],
                    requestClassName: $requestTarget['className'],
                    meta: $meta,
                    i18n: $i18n,
                )
            );
            $files[] = ['label' => 'UIRequest', 'path' => $requestTarget['filePath'], 'content' => $requestPhp];
        }

        if ($i18n) {
            $langReplacements = $this->renderer->buildLangReplacements($screenLangTarget['humanName'], $meta);
            $baseLangPath = function_exists('lang_path') ? lang_path() : base_path('lang');

            foreach ($this->resolveActiveLocales() as $locale) {
                $screenLangStub = $this->filesystem->exists($this->stubsPath("scaffolding/crud/lang_screen_{$locale}.php.stub"))
                    ? "scaffolding/crud/lang_screen_{$locale}.php.stub"
                    : 'scaffolding/crud/lang_screen_en.php.stub';

                $screenLangPath = rtrim($baseLangPath, '/\\').'/'.$locale.'/'.ltrim($screenLangTarget['relativePath'], '/\\');
                $files[] = [
                    'label' => "Translation ({$locale})",
                    'path' => $screenLangPath,
                    'content' => $this->renderStubFile($screenLangStub, $langReplacements),
                ];

                if ($requestLangTarget !== null) {
                    $reqLangStub = $this->filesystem->exists($this->stubsPath("scaffolding/crud/lang_request_{$locale}.php.stub"))
                        ? "scaffolding/crud/lang_request_{$locale}.php.stub"
                        : 'scaffolding/crud/lang_request_en.php.stub';

                    $reqLangPath = rtrim($baseLangPath, '/\\').'/'.$locale.'/'.ltrim($requestLangTarget['relativePath'], '/\\');
                    $files[] = [
                        'label' => "Request Translation ({$locale})",
                        'path' => $reqLangPath,
                        'content' => $this->renderStubFile($reqLangStub, $langReplacements),
                    ];
                }
            }
        }

        if ($withTest) {
            $screenFqcn = $screenTarget['fullNamespace'].'\\'.$screenTarget['className'];
            $testSubPath = $screenTarget['subPath'] !== '' ? $screenTarget['subPath'].'/' : '';
            $testPath = base_path('tests/Feature/'.$testSubPath.$screenTarget['className'].'ScreenTest.php');
            $testContent = $this->renderStubFile(
                'scaffolding/crud/test.pest.php.stub',
                $this->renderer->buildTestReplacements(
                    screenFqcn: $screenFqcn,
                    screenClassName: $screenTarget['className'],
                    visibilityEnum: $visibilityEnum,
                    meta: $meta,
                )
            );
            $files[] = ['label' => 'Pest Test', 'path' => $testPath, 'content' => $testContent];
        }

        return $files;
    }

    /**
     * @param  array<string, string>  $replacements
     */
    protected function renderStubFile(string $relativeStubPath, array $replacements): string
    {
        $resolvedPath = $this->stubsPath($relativeStubPath);
        if (! $this->filesystem->exists($resolvedPath)) {
            throw new InvalidArgumentException("Stub file [{$resolvedPath}] does not exist.");
        }

        if ($this->hasPublishedStubOverride($relativeStubPath)) {
            $this->line("  <fg=cyan>↺</> Using custom stub: {$resolvedPath}");
        }

        return $this->renderStubContent($this->filesystem->get($resolvedPath), $replacements);
    }

    protected function renderCapabilitiesSummary(CrudServiceMetadata $meta): void
    {
        $caps = [
            'Listable' => $meta->isListable,
            'Detailable' => $meta->isDetailable,
            'Creatable' => $meta->isCreatable,
            'Updatable' => $meta->isUpdatable,
            'Deletable' => $meta->isDeletable,
        ];

        $badges = [];
        foreach ($caps as $label => $enabled) {
            $badges[] = ($enabled ? '<fg=green>✓</>' : '<fg=gray>✗</>').' '.$label;
        }

        $fieldNames = array_map(
            static fn (CrudFieldDescriptor $f): string => "{$f->property}:{$f->widget}",
            $meta->fields
        );

        $this->line('Service: <comment>'.$meta->serviceFqcn.'</comment>');
        $this->line('Detail DTO: <comment>'.$meta->dtoFqcn.'</comment>');
        $this->line('Capabilities: '.implode('  ', $badges));
        $this->line('Fields: '.($fieldNames !== [] ? implode(', ', $fieldNames) : '(none)'));
    }

    /**
     * @return array{0: string, 1: ?string, 2: ?string, 3: ?string, 4: string, 5: bool, 6: bool, 7: bool}
     */
    private function runInteractiveWizard(
        string $name,
        ?string $serviceOption,
        ?string $stackDriver,
        ?string $fieldsOption,
        string $visibility,
        bool $i18n,
        bool $withTest,
        bool $runDiscover,
    ): array {
        if ($name === '') {
            $asked = $this->ask('Screen name (e.g. Catalog/ProductManager)');
            $name = is_string($asked) ? trim($asked) : '';
        }

        if ($serviceOption === null && $stackDriver === null) {
            $candidates = $this->inspector->discoverCandidateServices(app_path('Services'));
            $modeChoices = ['Use an existing Service', 'Scaffold a new DTO + Mock Repository + Service stack'];
            $chosenMode = $this->choice('How should this Master-Detail screen connect to data?', $modeChoices, 0);

            if ($chosenMode === $modeChoices[0]) {
                if ($candidates !== []) {
                    $chosenService = $this->choice('Select an existing Master-Detail Service', $candidates, 0);
                    $serviceOption = is_string($chosenService) ? $chosenService : null;
                } else {
                    $askedService = $this->ask('Service class (FQCN or relative to App\\Services, e.g. Catalog/ProductService)');
                    $serviceOption = is_string($askedService) && trim($askedService) !== '' ? trim($askedService) : null;
                }
            } else {
                $chosenDriver = $this->choice('Select mock persistence driver for the Repository', self::SUPPORTED_STACK_DRIVERS, 0);
                $stackDriver = is_string($chosenDriver) ? $chosenDriver : 'json';

                if ($fieldsOption === null) {
                    $askedFields = $this->ask(
                        'DTO fields (comma-separated, e.g. sku:string,name:string,price:float,active:bool)',
                        'name:string,description:text?,active:bool'
                    );
                    $fieldsOption = is_string($askedFields) ? trim($askedFields) : null;
                }
            }
        }

        $chosenVis = $this->choice('Screen visibility?', ['public', 'authenticated', 'guest'], $visibility);
        $visibility = is_string($chosenVis) ? $chosenVis : $visibility;

        $i18n = $this->confirm('Generate i18n translation files?', $i18n);
        $withTest = $this->confirm('Generate a Pest feature test?', $withTest);
        $runDiscover = $this->confirm('Run php artisan usim:discover now?', $runDiscover);

        return [$name, $serviceOption, $stackDriver, $fieldsOption, $visibility, $i18n, $withTest, $runDiscover];
    }

    private function inferEntityBaseName(string $screenName, ?string $serviceOption): string
    {
        $source = $serviceOption ?? $screenName;
        $normalized = str_replace('\\', '/', trim($source));
        $parts = array_values(array_filter(explode('/', $normalized)));
        $rawBase = (string) array_pop($parts);

        foreach (['Manager', 'Explorer', 'Crud', 'Screen', 'Admin', 'Service'] as $suffix) {
            if (str_ends_with($rawBase, $suffix) && strlen($rawBase) > strlen($suffix)) {
                $rawBase = substr($rawBase, 0, -strlen($suffix));
                break;
            }
        }

        $entity = Str::singular($this->pascalize($rawBase));
        if ($entity === '') {
            $entity = 'Item';
        }

        return ($parts !== [] ? implode('/', $parts).'/' : '').$entity;
    }

    private function writePlannedFile(string $filePath, string $content): void
    {
        $directory = dirname($filePath);
        if (! $this->filesystem->isDirectory($directory)) {
            $this->filesystem->makeDirectory($directory, 0755, true);
        }

        $this->filesystem->put($filePath, $content);
    }
}
