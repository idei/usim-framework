<?php

namespace Idei\Usim\Console\Commands;

use Idei\Usim\Console\Commands\Concerns\InstallsDatabaseScaffolding;
use Idei\Usim\Console\Commands\Concerns\InstallsLangStubs;
use Idei\Usim\Console\Commands\Concerns\InstallsTranslationManagerScaffolding;
use Idei\Usim\Console\Commands\Concerns\RegistersPackageHelperAutoload;
use Idei\Usim\Console\Commands\Support\SeedAccessControl;
use Idei\Usim\Console\Commands\Support\InstallAppScaffoldingManager;
use Idei\Usim\Console\Commands\Support\InstallContextResolver;
use Idei\Usim\Console\Commands\Support\InstallEnvironmentManager;
use Idei\Usim\Console\Commands\Support\InstallExecutionRollbackManager;
use Idei\Usim\Console\Commands\Support\InstallMigrationStatusChecker;
use Idei\Usim\Console\Commands\Support\InstallScaffoldingManager;
use Idei\Usim\Console\Commands\Support\InstallStateManager;
use Idei\Usim\Console\Commands\Support\InstallStubPublisher;
use Idei\Usim\Console\Commands\Support\InstallWorkflowBuilder;
use Idei\Usim\Console\Commands\Support\MissingDatabaseException;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Throwable;

class InstallCommand extends Command
{
    use InstallsDatabaseScaffolding;
    use InstallsLangStubs;
    use InstallsTranslationManagerScaffolding;
    use RegistersPackageHelperAutoload;

    protected $signature = 'usim:install
                            {--force : Overwrite existing files}';

    protected $description = 'Install the USIM framework scaffolding';

    protected Filesystem $files;
    /** @var object */
    protected $installStateManager;
    /** @var object */
    protected $installAppScaffoldingManager;
    /** @var object */
    protected $installAccessSynchronizer;
    /** @var object */
    protected $installContextResolver;
    /** @var object */
    protected $installEnvironmentManager;
    /** @var object */
    protected $installExecutionRollbackManager;
    /** @var object */
    protected $installMigrationStatusChecker;
    /** @var object */
    protected $installScaffoldingManager;
    /** @var object */
    protected $installWorkflowBuilder;
    /** @var object */
    protected $installStubPublisher;
    protected bool $force;
    /** @var array<string, string> */
    protected array $rootUserEnvValues = [];

    /** @var array{permissions_created:int,permissions_total:int,roles_created:int,roles_total:int,users_created:int,users_updated:int,languages_created:int,languages_updated:int} */
    protected array $syncStats = [
        'permissions_created' => 0,
        'permissions_total' => 0,
        'roles_created' => 0,
        'roles_total' => 0,
        'users_created' => 0,
        'users_updated' => 0,
        'languages_created' => 0,
        'languages_updated' => 0,
    ];

    /**
     * Namespace configuration — derived from the app's screens config.
     */
    protected string $screensNamespace;
    protected string $screensPath;
    protected string $componentsNamespace;
    protected string $componentsPath;

    public function __construct(
        Filesystem $files
    )
    {
        parent::__construct();
        $this->files = $files;
        $this->installStateManager = app(InstallStateManager::class);
        $this->installAppScaffoldingManager = app(InstallAppScaffoldingManager::class);
        $this->installAccessSynchronizer = app(SeedAccessControl::class);
        $this->installContextResolver = app(InstallContextResolver::class);
        $this->installEnvironmentManager = app(InstallEnvironmentManager::class);
        $this->installExecutionRollbackManager = app(InstallExecutionRollbackManager::class);
        $this->installMigrationStatusChecker = app(InstallMigrationStatusChecker::class);
        $this->installScaffoldingManager = app(InstallScaffoldingManager::class);
        $this->installStubPublisher = app(InstallStubPublisher::class);
        $this->installWorkflowBuilder = app(InstallWorkflowBuilder::class);
    }

    public function handle(): int
    {
        $this->force = $this->option('force');
        $this->initializeNamespaces();

        $this->info('Installing USIM scaffolding...');
        $this->showExistingStateIfPresent();
        $this->newLine();

        $envPath = '';

        $this->installExecutionRollbackManager->begin($this->rollbackTrackedPaths());

        $steps = $this->buildWorkflowSteps($envPath);

        $this->installStateManager->start(count($steps));

        try {
            $totalSteps = count($steps);

            foreach ($steps as $index => $step) {
                $this->runInstallStep(
                    stepKey: (string) $step['key'],
                    label: (string) $step['label'],
                    index: $index + 1,
                    total: $totalSteps,
                    callback: $step['run']
                );
            }

            $this->installStateManager->finish($this->syncStats);
            $this->installExecutionRollbackManager->cleanup();
        } catch (Throwable $exception) {
            if ($exception instanceof MissingDatabaseException) {
                $this->installExecutionRollbackManager->rollback();
                $this->installStateManager->markRolledBack($exception->getMessage());
                $this->installExecutionRollbackManager->cleanup();

                $statePath = $this->installStateManager->getPath();
                $this->components->error("USIM installation was rolled back because database is missing. State was persisted in {$statePath}.");
                $this->components->error($exception->getMessage());

                return self::FAILURE;
            }

            $this->installExecutionRollbackManager->cleanup();
            $this->installStateManager->fail($exception);
            $statePath = $this->installStateManager->getPath();
            $this->components->error("USIM installation failed. State was persisted for troubleshooting in {$statePath}.");
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('USIM installed successfully!');
        $this->newLine();
        $this->printPostInstallInstructions();

        return self::SUCCESS;
    }

    protected function initializeNamespaces(): void
    {
        $namespaces = $this->installContextResolver->resolveNamespaces();
        $this->screensNamespace = $namespaces['screensNamespace'];
        $this->screensPath = $namespaces['screensPath'];
        $this->componentsNamespace = $namespaces['componentsNamespace'];
        $this->componentsPath = $namespaces['componentsPath'];
    }

    protected function installCoreScreens(): void
    {
        $this->installAppScaffoldingManager->installCoreScreens(
            context: $this->buildScaffoldingContext(),
            publishStub: function (string $stubPath, string $targetPath, bool $autoForce, array $replacements): void {
                $this->publishStub($stubPath, $targetPath, $autoForce, $replacements);
            },
            line: function (string $message): void {
                $this->line($message);
            },
            installTranslationManagerScaffolding: function (): void {
                $this->installTranslationManagerScaffolding();
            }
        );
    }

    protected function installLanguageStubsStep(): void
    {
        $this->installLangStubs();
    }

    protected function runDiscoverScreens(): void
    {
        $this->call('usim:discover');
    }

    /**
     * @param string $envPath
     * @return array<int, array{key:string,label:string,run:callable():void}>
     */
    protected function buildWorkflowSteps(string &$envPath): array
    {
        return $this->installWorkflowBuilder->build(
            checkEnvironment: function (): void {
                $this->installEnvironmentManager->assertNotProductionEnvironment();
            },
            checkDatabaseReadiness: function (): void {
                $this->assertDatabaseMigrationReadyForInstall();
            },
            publishConfig: function (): void {
                $this->installScaffoldingManager->publishConfig(
                    force: $this->force,
                    info: function (string $message): void {
                        $this->info($message);
                    },
                    line: function (string $message): void {
                        $this->line($message);
                    },
                    callSilently: function (string $command, array $arguments): void {
                        $this->callSilently($command, $arguments);
                    }
                );
            },
            publishAssets: function (): void {
                $this->installScaffoldingManager->publishAssets(
                    info: function (string $message): void {
                        $this->info($message);
                    },
                    line: function (string $message): void {
                        $this->line($message);
                    },
                    callSilently: function (string $command, array $arguments): void {
                        $this->callSilently($command, $arguments);
                    }
                );
            },
            installCoreScreens: function (): void {
                $this->installCoreScreens();
            },
            installAuthScaffolding: function (): void {
                $this->installAuthScaffolding();
            },
            installViews: function (): void {
                $this->installScaffoldingManager->installViews(
                    newLine: function (): void {
                        $this->newLine();
                    },
                    info: function (string $message): void {
                        $this->info($message);
                    },
                    line: function (string $message): void {
                        $this->line($message);
                    },
                    stubsPath: fn (string $path): string => $this->stubsPath($path),
                    publishStub: function (string $stubPath, string $targetPath, bool $autoForce, array $replacements): void {
                        $this->publishStub($stubPath, $targetPath, $autoForce, $replacements);
                    }
                );
            },
            installLanguageStubs: function (): void {
                $this->installLanguageStubsStep();
            },
            installWebRoutes: function (): void {
                $this->installScaffoldingManager->installWebRoutes(
                    newLine: function (): void {
                        $this->newLine();
                    },
                    info: function (string $message): void {
                        $this->info($message);
                    },
                    line: function (string $message): void {
                        $this->line($message);
                    },
                    stubsPath: fn (string $path): string => $this->stubsPath($path)
                );
            },
            appendEnvVars: function () use (&$envPath): void {
                $envPath = $this->installEnvironmentManager->appendUsimEnvVariables(
                    $this->stubsPath('env.stub'),
                    function (string $message): void {
                        $this->info($message);
                    },
                    function (string $message): void {
                        $this->line($message);
                    }
                );
            },
            configureRoot: function () use (&$envPath): void {
                if ($envPath === '') {
                    $envPath = $this->installEnvironmentManager->resolveEnvPath(
                        true,
                        function (string $message): void {
                            $this->line($message);
                        }
                    ) ?? '';
                }

                if ($envPath === '') {
                    throw new \RuntimeException('Unable to locate or create a .env file for root configuration.');
                }

                $this->rootUserEnvValues = $this->installEnvironmentManager->promptAndPersistRootUserEnv(
                    envPath: $envPath,
                    interactive: $this->input->isInteractive(),
                    ask: fn (string $question, string $default): string => (string) $this->ask($question, $default),
                    secret: fn (string $prompt): string => (string) $this->secret($prompt),
                    error: function (string $message): void {
                        $this->components->error($message);
                    },
                    line: function (string $message): void {
                        $this->line($message);
                    }
                );
            },
            registerHelpers: function (): void {
                $this->registerPackageHelpersAutoload();
            },
            upsertAccessAndLanguages: function (): void {
                $this->upsertAccessAndLanguages();
            },
            discoverScreens: function (): void {
                $this->runDiscoverScreens();
            },
        );
    }

    protected function runInstallStep(string $stepKey, string $label, int $index, int $total, callable $callback): void
    {
        $this->newLine();
        $this->info("[{$index}/{$total}] {$label}...");
        $this->installStateManager->setCurrentStep($stepKey, $label, $index, $total);
        $callback();
        $this->installStateManager->markStepCompleted($stepKey);
        $this->line('  <fg=green>✓</> Step completed');
    }

    protected function showExistingStateIfPresent(): void
    {
        $state = $this->installStateManager->read();
        if ($state === null) {
            return;
        }

        $status = (string) ($state['status'] ?? 'unknown');
        $current = (string) ($state['current_step']['label'] ?? 'n/a');
        $this->line("Previous install state detected: <fg=blue>{$status}</> (last step: {$current})");
    }

    protected function upsertAccessAndLanguages(): void
    {
        $this->ensureRequiredTables();

        $this->syncStats = $this->installAccessSynchronizer->seed(
            rootUserEnvValues: $this->rootUserEnvValues,
            userModelClass: $this->resolveUserModelImport(),
            line: function (string $message): void {
                $this->line($message);
            }
        );

        $this->line('  <fg=green>✓</> Access and languages upsert completed');
    }

    protected function ensureRequiredTables(): void
    {
        $this->assertDatabaseMigrationReadyForInstall();
    }

    protected function assertDatabaseMigrationReadyForInstall(): void
    {
        $assessment = $this->installMigrationStatusChecker->assess();

        $databaseExists = (bool) ($assessment['database_exists'] ?? true);
        if (!$databaseExists) {
            $databaseIssue = (string) ($assessment['database_issue'] ?? 'Database does not exist or is not reachable with current .env settings.');
            throw new MissingDatabaseException(
                $databaseIssue . ' Migration is not completed for this environment. Create/configure the database and run `php artisan migrate` before continuing with `php artisan usim:install`.'
            );
        }

        if (($assessment['is_ready'] ?? false) === true) {
            return;
        }

        $details = [];

        $missingTables = $assessment['missing_tables'] ?? [];
        if (is_array($missingTables) && $missingTables !== []) {
            $details[] = 'missing tables: ' . implode(', ', $missingTables);
        }

        $missingColumns = $assessment['missing_columns'] ?? [];
        if (is_array($missingColumns)) {
            foreach ($missingColumns as $table => $columns) {
                if (!is_string($table) || !is_array($columns) || $columns === []) {
                    continue;
                }

                $details[] = 'missing columns in ' . $table . ': ' . implode(', ', $columns);
            }
        }

        $missingMigrations = $assessment['missing_migrations'] ?? [];
        if (is_array($missingMigrations) && $missingMigrations !== []) {
            $details[] = 'critical migrations not executed: ' . implode(', ', $missingMigrations);
        }

        $notes = $assessment['notes'] ?? [];
        if (is_array($notes)) {
            foreach ($notes as $note) {
                if (is_string($note) && trim($note) !== '') {
                    $details[] = trim($note);
                }
            }
        }

        if ($details === []) {
            $details[] = 'database schema validation failed';
        }

        throw new \RuntimeException(
            'Database migration is not ready for USIM install (' . implode('; ', $details) . '). ' .
            'Run `php artisan migrate` before continuing.'
        );
    }

    /**
     * @return array<int, string>
     */
    protected function rollbackTrackedPaths(): array
    {
        return [
            '.env',
            'config/usim.php',
            'routes/web.php',
            'composer.json',
            'bootstrap/providers.php',
            'app/UI/Screens/Home.php',
            'app/UI/Screens/Menu.php',
            'app/UI/Screens/Admin',
            'app/UI/Screens/Auth',
            'app/UI/Components/Modals',
            'app/UI/Components/DataTable',
            'app/Services/Auth',
            'app/Services/User',
            'app/Http/Controllers/Api/AuthController.php',
            'app/Providers/EventServiceProvider.php',
            'resources/views/emails/verify-email.blade.php',
            'resources/views/emails/reset-password.blade.php',
            'resources/views/terms.blade.php',
            'resources/views/landing.blade.php',
            'tests/Pest.php',
            'tests/TestCase.php',
            'tests/Support',
            'tests/Traits',
            'tests/Feature/HomeMenuScreenTest.php',
            'tests/Feature/LoginScreenTest.php',
            'tests/Feature/PasswordRecoveryUiTest.php',
            'tests/Feature/UiAuthEventsContractTest.php',
            'lang/en/landing.php',
            'lang/en/modal/edit_translation_dialog.php',
            'lang/en/screen/permissions.php',
            'lang/es/landing.php',
            'lang/es/modal/edit_translation_dialog.php',
            'database/migrations',
        ];
    }

    // =========================================================================
    // Auth scaffolding
    // =========================================================================

    protected function installAuthScaffolding(): void
    {
        $this->installAppScaffoldingManager->installAuthScaffolding(
            context: $this->buildScaffoldingContext(),
            publishStub: function (
                string $stubPath,
                string $targetPath,
                bool $autoForce,
                array $replacements,
                ?callable $postInstallCallback = null
            ): void {
                $this->publishStub($stubPath, $targetPath, $autoForce, $replacements, $postInstallCallback);
            },
            line: function (string $message): void {
                $this->line($message);
            },
            info: function (string $message): void {
                $this->info($message);
            },
            newLine: function (): void {
                $this->newLine();
            },
            installMigrations: function (): void {
                $this->installMigrations();
            },
            installUsersConfigNotice: function (): void {
                $this->installScaffoldingManager->installUsersConfigNotice(
                    line: function (string $message): void {
                        $this->line($message);
                    }
                );
            }
        );
    }

    /**
     * @return array<string, string|bool>
     */
    protected function buildScaffoldingContext(): array
    {
        return $this->installContextResolver->buildScaffoldingContext(
            stubsBasePath: $this->stubsPath(),
            namespaces: [
                'screensNamespace' => $this->screensNamespace,
                'screensPath' => $this->screensPath,
                'componentsNamespace' => $this->componentsNamespace,
                'componentsPath' => $this->componentsPath,
            ],
            userModelImport: $this->resolveUserModelImport(),
            userModelClass: $this->resolveUserModelClass(),
            force: $this->force
        );
    }
    // =========================================================================
    // Post-Install Instructions
    // =========================================================================

    protected function printPostInstallInstructions(): void
    {
        $this->components->info('Next steps:');

        $steps = [];

        $steps[] = "Run <fg=yellow>php artisan usim:discover</> after creating new screens\n";
        $steps[] = "Run <fg=yellow>./start.sh [-r]</> to start the development server.\n" .
            "     <fg=gray>Note: -r option removes database and starts fresh)</fg=gray>";
        $steps[] = "Installer state is tracked at <fg=yellow>{$this->installStateManager->getPath()}</>";
        $steps[] = "Upsert summary: permissions {$this->syncStats['permissions_total']} ({$this->syncStats['permissions_created']} new), roles {$this->syncStats['roles_total']} ({$this->syncStats['roles_created']} new), users {$this->syncStats['users_created']} new / {$this->syncStats['users_updated']} updated, languages {$this->syncStats['languages_created']} new / {$this->syncStats['languages_updated']} updated";

        foreach ($steps as $i => $step) {
            $num = $i + 1;
            $this->line("  {$num}. {$step}");
        }

        $this->newLine();
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    protected function installScreen(string $stub, string $fileName, ?string $subDirectory = null): void
    {
        $context = $this->buildScaffoldingContext();
        $targetDir = $subDirectory
            ? $context['screensPath'] . '/' . $subDirectory
            : $context['screensPath'];

        $this->publishStub(
            'screens/' . $stub,
            $targetDir . '/' . $fileName,
            false,
            [
                '{{ screensNamespace }}' => $context['screensNamespace'] . ($subDirectory ? "\\{$subDirectory}" : ''),
            ]
        );

        $relativePath = 'app/UI/Screens/' . ($subDirectory ? $subDirectory . '/' : '') . $fileName;
        $this->line("  <fg=green>✓</> {$relativePath}");
    }

    protected function installComponent(string $stub, string $fileName, ?string $subDirectory = null): void
    {
        $context = $this->buildScaffoldingContext();
        $targetDir = $subDirectory
            ? $context['componentsPath'] . '/' . $subDirectory
            : $context['componentsPath'];

        $this->publishStub(
            'components/' . $stub,
            $targetDir . '/' . $fileName,
            false,
            [
                '{{ componentsNamespace }}' => $context['componentsNamespace'] . ($subDirectory ? "\\{$subDirectory}" : ''),
            ]
        );

        $relativePath = 'app/UI/Components/' . ($subDirectory ? $subDirectory . '/' : '') . $fileName;
        $this->line("  <fg=green>✓</> {$relativePath}");
    }

    protected function stubsPath(string $path = ''): string
    {
        return $this->installContextResolver->stubsPath($path);
    }

    protected function publishStub(string $stubPath, string $targetPath, bool $autoForce = false, array $replacements = [], ?callable $postInstallCallback = null): void
    {
        $this->installStubPublisher->publish(
            stubPath: $stubPath,
            targetPath: $targetPath,
            force: $this->force,
            autoForce: $autoForce,
            replacements: $replacements,
            postInstallCallback: $postInstallCallback
        );
    }

    protected function resolveUserModelImport(): string
    {
        // Check if the app has a custom User model location
        $authConfig = \config('auth.providers.users.model', 'App\\Models\\User');

        return \is_string($authConfig) && $authConfig !== ''
            ? $authConfig
            : 'App\\Models\\User';
    }

    protected function resolveUserModelClass(): string
    {
        $fullClass = $this->resolveUserModelImport();
        return \class_basename($fullClass);
    }
}
