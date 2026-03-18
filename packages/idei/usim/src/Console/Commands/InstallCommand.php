<?php

namespace Idei\Usim\Console\Commands;

use Idei\Usim\Support\CodeModifier\ClassModifier;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

class InstallCommand extends Command
{
    protected $signature = 'usim:install
                            {--force : Overwrite existing files}';

    protected $description = 'Install the USIM framework scaffolding';

    protected Filesystem $files;
    protected bool $force;

    /**
     * Namespace configuration — derived from the app's screens config.
     */
    protected string $screensNamespace;
    protected string $screensPath;
    protected string $componentsNamespace;
    protected string $componentsPath;

    public function __construct(Filesystem $files)
    {
        parent::__construct();
        $this->files = $files;
    }

    public function handle(): int
    {
        $this->force = $this->option('force');

        $this->info('Installing USIM scaffolding...');
        $this->newLine();

        // --- Resolve namespaces ---
        $this->screensNamespace = \config('ui-services.screens_namespace', 'App\\UI\\Screens');
        $this->screensPath = \config('ui-services.screens_path', \app_path('UI/Screens'));
        $this->componentsNamespace = Str::beforeLast($this->screensNamespace, '\\Screens') . '\\Components';
        $this->componentsPath = Str::beforeLast($this->screensPath, '/Screens') . '/Components';

        // === STEP 1: Publish USIM config and assets ===
        $this->publishConfig();
        $this->publishAssets();

        // === STEP 2: Install core screens ===
        $this->installScreen('Home.php.stub', 'Home.php');

        $this->installScreen('Menu.php.stub', 'Menu.php');
        $this->installScreen('Admin/Dashboard.php.stub', 'Dashboard.php', 'Admin');

        // === STEP 3: Install auth scaffolding, controller, model, and supporting files ===
        $this->installAuthScaffolding();

        // === STEP 4: Install email and page views ===
        $this->installViews();

        // === STEP 5: Install web routes (catch-all) ===
        $this->installWebRoutes();

        // === STEP 6: Append .env variables ===
        $this->appendEnvVariables();

        // === STEP 7: Run usim:discover ===
        $this->call('usim:discover');

        // === STEP 8: Summary ===
        $this->newLine();
        $this->info('USIM installed successfully!');
        $this->newLine();
        $this->printPostInstallInstructions();

        return self::SUCCESS;
    }

    // =========================================================================
    // Publish config and assets
    // =========================================================================

    protected function publishConfig(): void
    {
        $this->info('Publishing USIM configuration...');

        $this->callSilently('vendor:publish', [
            '--tag' => 'usim-config',
            '--force' => $this->force,
        ]);

        $this->line('  <fg=green>✓</> Config published');
    }

    protected function publishAssets(): void
    {
        $this->info('Publishing USIM assets...');

        $this->callSilently('vendor:publish', [
            '--tag' => 'usim-assets',
            '--force' => true, // Always overwrite assets
        ]);

        $this->line('  <fg=green>✓</> Assets published');
    }

    // =========================================================================
    // Views Installation
    // =========================================================================

    protected function installViews(): void
    {
        $this->newLine();
        $this->info('Publishing email and page views...');

        $views = [
            'emails/verify-email.blade.php' => \resource_path('views/emails/verify-email.blade.php'),
            'emails/reset-password.blade.php' => \resource_path('views/emails/reset-password.blade.php'),
            'terms.blade.php' => \resource_path('views/terms.blade.php'),
        ];

        foreach ($views as $stub => $target) {
            $stubPath = $this->stubsPath("views/{$stub}");
            $this->publishStub($stubPath, $target, []);
            $relativePath = str_replace(\base_path() . '/', '', $target);
            $this->line("  <fg=green>✓</> {$relativePath}");
        }
    }

    // =========================================================================
    // Screen Installation
    // =========================================================================

    protected function installScreen(string $stubName, string $targetName, ?string $subdirectory = null): void
    {
        $stubPath = $this->stubsPath("screens/{$stubName}");

        $targetDir = $subdirectory
            ? $this->screensPath . '/' . $subdirectory
            : $this->screensPath;

        $targetFile = $targetDir . '/' . $targetName;

        $namespace = $subdirectory
            ? $this->screensNamespace . '\\' . str_replace('/', '\\', $subdirectory)
            : $this->screensNamespace;

        $this->publishStub($stubPath, $targetFile, [
            '{{ namespace }}' => $namespace,
            '{{ screensNamespace }}' => $this->screensNamespace,
            '{{ componentsNamespace }}' => $this->componentsNamespace,
            '{{ userModel }}' => $this->resolveUserModelImport(),
            '{{ userModelClass }}' => $this->resolveUserModelClass(),
        ]);

        $relativePath = str_replace(\base_path() . '/', '', $targetFile);
        $this->line("  <fg=green>✓</> {$relativePath}");
    }

    // =========================================================================
    // Auth scaffolding
    // =========================================================================

    protected function installAuthScaffolding(): void
    {
        $this->newLine();
        $this->info('Installing Auth services...');
        $this->installAuthServices();

        $this->newLine();
        $this->info('Installing Auth screens...');

        // Auth Screens
        $this->installScreen('Auth/Login.php.stub', 'Login.php', 'Auth');
        $this->installScreen('Auth/ForgotPassword.php.stub', 'ForgotPassword.php', 'Auth');
        $this->installScreen('Auth/ResetPassword.php.stub', 'ResetPassword.php', 'Auth');
        $this->installScreen('Auth/EmailVerified.php.stub', 'EmailVerified.php', 'Auth');
        $this->installScreen('Auth/Profile.php.stub', 'Profile.php', 'Auth');

        // Modals
        $this->newLine();
        $this->info('Installing Modal components...');
        $this->installComponent('Modals/LoginDialog.php.stub', 'LoginDialog.php', 'Modals');
        $this->installComponent('Modals/RegisterDialog.php.stub', 'RegisterDialog.php', 'Modals');
        $this->installComponent('Modals/EditUserDialog.php.stub', 'EditUserDialog.php', 'Modals');

        $this->newLine();
        $this->info('Installing DataTable components...');
        $this->installComponent('DataTable/UserApiTableModel.php.stub', 'UserApiTableModel.php', 'DataTable');

        // AuthController
        $this->newLine();
        $this->info('Installing AuthController...');
        $this->installAuthController();

        // User model
        $this->newLine();
        $this->info('Configuring User model...');
        $this->configureUserModel();

        // EventServiceProvider
        $this->newLine();
        $this->info('Installing EventServiceProvider...');
        $this->installEventServiceProvider();
        $this->registerBootstrapProviders();

        // Migrations
        $this->newLine();
        $this->info('Publishing migrations...');
        $this->installMigrations();

        // Seeders
        $this->newLine();
        $this->info('Publishing seeders...');
        $this->installSeeders();

        // Users config
        $this->newLine();
        $this->info('Publishing users config...');
        $this->installUsersConfig();

        // API Auth routes
        $this->newLine();
        $this->info('Installing API auth routes...');
        $this->installApiAuthRoutes();

        // Tests scaffolding
        $this->newLine();
        $this->info('Publishing test stubs...');
        $this->installTestStubs();
    }

    // =========================================================================
    // Component Installation
    // =========================================================================

    protected function installComponent(string $stubName, string $targetName, ?string $subdirectory = null): void
    {
        $stubPath = $this->stubsPath("components/{$stubName}");

        $targetDir = $subdirectory
            ? $this->componentsPath . '/' . $subdirectory
            : $this->componentsPath;

        $targetFile = $targetDir . '/' . $targetName;

        $namespace = $subdirectory
            ? $this->componentsNamespace . '\\' . str_replace('/', '\\', $subdirectory)
            : $this->componentsNamespace;

        $this->publishStub($stubPath, $targetFile, [
            '{{ componentsNamespace }}' => $namespace,
        ]);

        $relativePath = str_replace(\base_path() . '/', '', $targetFile);
        $this->line("  <fg=green>✓</> {$relativePath}");
    }

    protected function installAuthServices(): void
    {
        $this->installService('Auth/AuthSessionService.php.stub', 'AuthSessionService.php', 'Auth');
        $this->installService('Auth/LoginService.php.stub', 'LoginService.php', 'Auth');
        $this->installService('Auth/RegisterService.php.stub', 'RegisterService.php', 'Auth');
        $this->installService('Auth/PasswordService.php.stub', 'PasswordService.php', 'Auth');
        $this->installService('User/UserService.php.stub', 'UserService.php', 'User');
    }

    protected function installService(string $stubName, string $targetName, ?string $subdirectory = null): void
    {
        $stubPath = $this->stubsPath("services/{$stubName}");

        $targetDir = $subdirectory
            ? \app_path('Services/' . $subdirectory)
            : \app_path('Services');

        $targetFile = $targetDir . '/' . $targetName;

        $namespace = $subdirectory
            ? 'App\\Services\\' . str_replace('/', '\\', $subdirectory)
            : 'App\\Services';

        $this->publishStub($stubPath, $targetFile, [
            '{{ namespace }}' => $namespace,
            '{{ userModel }}' => $this->resolveUserModelImport(),
            '{{ userModelClass }}' => $this->resolveUserModelClass(),
        ]);

        $relativePath = str_replace(\base_path() . '/', '', $targetFile);
        $this->line("  <fg=green>✓</> {$relativePath}");
    }

    protected function installTestStubs(): void
    {
        $this->installTestFile('Support/usim_bootstrap.php.stub', 'usim_bootstrap.php', 'Support');
        $this->installTestFile('Traits/UsimTestHelpers.php.stub', 'UsimTestHelpers.php', 'Traits');
        $this->installTestFile('Pest.php.stub', 'Pest.php', null, fn($path) => $this->addRequireToPest($path));
        $this->installTestFile('TestCase.php.stub', 'TestCase.php', null, fn($path) => $this->addUsimTestHelpersToTestCase($path));

        $this->installTestFile('Support/UiScreenTestHelpers.php.stub', 'UiScreenTestHelpers.php', 'Support');
        $this->installTestFile('Support/UiMemoryRenderer.php.stub', 'UiMemoryRenderer.php', 'Support');
        $this->installTestFile('Support/UiComponentRef.php.stub', 'UiComponentRef.php', 'Support');
        $this->installTestFile('Support/UiScenario.php.stub', 'UiScenario.php', 'Support');
        $this->installTestFile('Support/UiPayloadHelpers.php.stub', 'UiPayloadHelpers.php', 'Support');

        $this->installTestFile('Feature/HomeMenuScreenTest.php.stub', 'HomeMenuScreenTest.php', 'Feature');
        $this->installTestFile('Feature/LoginScreenTest.php.stub', 'LoginScreenTest.php', 'Feature');
        $this->installTestFile('Feature/PasswordRecoveryUiTest.php.stub', 'PasswordRecoveryUiTest.php', 'Feature');
        $this->installTestFile('Feature/UiAuthEventsContractTest.php.stub', 'UiAuthEventsContractTest.php', 'Feature');
    }

    protected function installTestFile(
        string $stubName,
        string $targetName,
        ?string $subdirectory = null,
        ?callable $postInstallCallback = null
    ): void {
        $stubPath = $this->stubsPath("tests/{$stubName}");

        $targetDir = $subdirectory
            ? \base_path('tests/' . $subdirectory)
            : \base_path('tests');

        $targetFile = $targetDir . '/' . $targetName;

        $this->publishStub($stubPath, $targetFile, [
            '{{ userModel }}' => $this->resolveUserModelImport(),
            '{{ userModelClass }}' => $this->resolveUserModelClass(),
        ], $postInstallCallback);

        $relativePath = str_replace(\base_path() . '/', '', $targetFile);
        $this->line("  <fg=green>✓</> {$relativePath}");
    }

    protected function addUsimTestHelpersToTestCase(string $path): void
    {
        $this->line("  <fg=green>✓</> Adding traits to TestCase.php...");
        ClassModifier::addTraitToClass($path, 'TestCase', RefreshDatabase::class);
        ClassModifier::addTraitToClass($path, 'TestCase', "Tests\\Traits\\UsimTestHelpers");
    }

    protected function addRequireToPest(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }

        $content = file_get_contents($path);

        $line = "require_once __DIR__ . '/Support/usim_bootstrap.php';";

        // Evitar duplicados
        if (str_contains($content, $line)) {
            return;
        }

        // Insertar después de <?php
        $content = preg_replace(
            '/<\?php\s*/',
            "<?php\n\n{$line}\n\n",
            $content,
            1
        );

        file_put_contents($path, $content);
    }

    // =========================================================================
    // AuthController
    // =========================================================================

    protected function installAuthController(): void
    {
        $controllerPath = \app_path('Http/Controllers/Api/AuthController.php');
        $stubPath = $this->stubsPath('controllers/AuthController.php.stub');

        $this->publishStub($stubPath, $controllerPath, [
            '{{ namespace }}' => 'App\\Http\\Controllers\\Api',
            '{{ userModel }}' => $this->resolveUserModelImport(),
            '{{ userModelClass }}' => $this->resolveUserModelClass(),
        ]);

        $relativePath = str_replace(\base_path() . '/', '', $controllerPath);
        $this->line("  <fg=green>✓</> {$relativePath}");
    }

    // =========================================================================
    // User Model Configuration
    // =========================================================================
    protected function configureUserModel(): void
    {
        $userModelPath = \app_path('Models/User.php');

        if (!$this->files->exists($userModelPath)) {
            $stubPath = $this->stubsPath('models/User.php.stub');

            $this->publishStub($stubPath, $userModelPath, [
                '{{ namespace }}' => 'App\\Models',
            ]);

            $this->line('  <fg=green>✓</> User model created with USIM auth defaults');
            return;
        }

        ClassModifier::addTraitToClass($userModelPath, 'User', \Laravel\Sanctum\HasApiTokens::class);
        ClassModifier::addTraitToClass($userModelPath, 'User', \Spatie\Permission\Traits\HasRoles::class);
        ClassModifier::addTraitToClass($userModelPath, 'User', \Idei\Usim\Traits\UsimUser::class);

        ClassModifier::addInterface($userModelPath, 'User', \Illuminate\Contracts\Auth\MustVerifyEmail::class);
        ClassModifier::addInterface($userModelPath, 'User', \Illuminate\Contracts\Auth\CanResetPassword::class);

        ClassModifier::addPropertyArrayValue($userModelPath, 'User', 'fillable', 'terms_accepted_at');
        ClassModifier::addCast($userModelPath, 'User', 'terms_accepted_at', 'datetime');

        $this->line('  <fg=green>✓</> User model updated with USIM auth defaults');
    }

    // =========================================================================
    // EventServiceProvider
    // =========================================================================

    protected function installEventServiceProvider(): void
    {
        $targetPath = \app_path('Providers/EventServiceProvider.php');
        $stubPath = $this->stubsPath('providers/EventServiceProvider.php.stub');
        $this->publishStub($stubPath, $targetPath, []);
        $relativePath = str_replace(\base_path() . '/', '', $targetPath);
        $this->line("  <fg=green>✓</> {$relativePath}");
    }

    protected function registerBootstrapProviders(): void
    {
        $providersPath = \base_path('bootstrap/providers.php');

        if (!$this->files->exists($providersPath)) {
            $this->line('  <fg=yellow>!</> bootstrap/providers.php not found, skipping');
            return;
        }

        $contents = $this->files->get($providersPath);

        if (str_contains($contents, 'EventServiceProvider')) {
            $this->line('  <fg=blue>→</> EventServiceProvider already in bootstrap/providers.php');
            return;
        }

        // Insert EventServiceProvider::class before the closing ];
        $pos = strrpos($contents, '];');
        if ($pos !== false) {
            $contents = substr($contents, 0, $pos)
                . "    App\\Providers\\EventServiceProvider::class,\n];"
                . substr($contents, $pos + 2);
            $this->files->put($providersPath, $contents);
            $this->line('  <fg=green>✓</> EventServiceProvider registered in bootstrap/providers.php');
        }
    }

    // =========================================================================
    // Migrations
    // =========================================================================

    protected function installMigrations(): void
    {
        $migrationsPath = \database_path('migrations');

        // temporary_uploads
        if (!$this->migrationExists('create_temporary_uploads_table')) {
            $timestamp = date('Y_m_d_His', time());
            $stubPath = $this->stubsPath('migrations/create_temporary_uploads_table.php.stub');
            $target = $migrationsPath . "/{$timestamp}_create_temporary_uploads_table.php";
            $this->publishStub($stubPath, $target, []);
            $this->line('  <fg=green>✓</> create_temporary_uploads_table migration');
        } else {
            $this->line('  <fg=blue>→</> create_temporary_uploads_table already exists');
        }

        // profile_image column
        if (!$this->migrationExists('add_profile_image_to_users_table')) {
            $timestamp = date('Y_m_d_His', time() + 1);
            $stubPath = $this->stubsPath('migrations/add_profile_image_to_users_table.php.stub');
            $target = $migrationsPath . "/{$timestamp}_add_profile_image_to_users_table.php";
            $this->publishStub($stubPath, $target, []);
            $this->line('  <fg=green>✓</> add_profile_image_to_users_table migration');
        } else {
            $this->line('  <fg=blue>→</> add_profile_image_to_users_table already exists');
        }

        // terms_accepted_at column
        if (!$this->migrationExists('add_terms_accepted_at_to_users_table')) {
            $timestamp = date('Y_m_d_His', time() + 2);
            $stubPath = $this->stubsPath('migrations/add_terms_accepted_at_to_users_table.php.stub');
            $target = $migrationsPath . "/{$timestamp}_add_terms_accepted_at_to_users_table.php";
            $this->publishStub($stubPath, $target, []);
            $this->line('  <fg=green>✓</> add_terms_accepted_at_to_users_table migration');
        } else {
            $this->line('  <fg=blue>→</> add_terms_accepted_at_to_users_table already exists');
        }

        // personal_access_tokens (Sanctum)
        if (!$this->migrationExists('create_personal_access_tokens_table')) {
            $this->callSilently('vendor:publish', [
                '--tag' => 'sanctum-migrations',
            ]);
            $this->line('  <fg=green>✓</> Sanctum migrations published');
        } else {
            $this->line('  <fg=blue>→</> Sanctum migrations already exist');
        }

        // Spatie permission tables
        if (!$this->migrationExists('create_permission_tables')) {
            $this->callSilently('vendor:publish', [
                '--provider' => 'Spatie\\Permission\\PermissionServiceProvider',
                '--tag' => 'permission-migrations',
            ]);
            $this->line('  <fg=green>✓</> Spatie Permission migrations published');
        } else {
            $this->line('  <fg=blue>→</> Spatie Permission migrations already exist');
        }
    }

    protected function migrationExists(string $migrationName): bool
    {
        $migrationsPath = \database_path('migrations');

        if (!$this->files->isDirectory($migrationsPath)) {
            return false;
        }

        $files = $this->files->glob($migrationsPath . "/*_{$migrationName}.php");

        return count($files) > 0;
    }

    // =========================================================================
    // Seeders
    // =========================================================================

    protected function installSeeders(): void
    {
        $seedersPath = \database_path('seeders');

        // RoleSeeder
        $roleSeederPath = $seedersPath . '/RoleSeeder.php';
        $stubPath = $this->stubsPath('seeders/RoleSeeder.php.stub');
        $this->publishStub($stubPath, $roleSeederPath, []);
        $this->line('  <fg=green>✓</> RoleSeeder');

        // UserSeeder
        $userSeederPath = $seedersPath . '/UserSeeder.php';
        $stubPath = $this->stubsPath('seeders/UserSeeder.php.stub');
        $this->publishStub($stubPath, $userSeederPath, [
            '{{ userModel }}' => $this->resolveUserModelImport(),
            '{{ userModelClass }}' => $this->resolveUserModelClass(),
        ]);
        $this->line('  <fg=green>✓</> UserSeeder');

        // Suggest adding to DatabaseSeeder
        $databaseSeederPath = $seedersPath . '/DatabaseSeeder.php';
        if ($this->files->exists($databaseSeederPath)) {
            $contents = $this->files->get($databaseSeederPath);
            if (!str_contains($contents, 'RoleSeeder') && !str_contains($contents, 'UserSeeder')) {
                $this->newLine();
                $this->warn('  Add to your DatabaseSeeder::run():');
                $this->line('    $this->call([');
                $this->line('        RoleSeeder::class,');
                $this->line('        UserSeeder::class,');
                $this->line('    ]);');
            }
        }
    }

    // =========================================================================
    // Users Config
    // =========================================================================

    protected function installUsersConfig(): void
    {
        $configPath = \config_path('users.php');
        $stubPath = $this->stubsPath('config/users.php.stub');
        $this->publishStub($stubPath, $configPath, []);
        $relativePath = str_replace(\base_path() . '/', '', $configPath);
        $this->line("  <fg=green>✓</> {$relativePath}");
    }

    // =========================================================================
    // API Auth Routes
    // =========================================================================

    protected function installApiAuthRoutes(): void
    {
        $targetPath = \base_path('routes/api-auth.php');
        $stubPath = $this->stubsPath('routes/api-auth.php.stub');

        $this->publishStub($stubPath, $targetPath, [
            '{{ authControllerClass }}' => 'App\\Http\\Controllers\\Api\\AuthController',
        ]);
        $this->line('  <fg=green>✓</> routes/api-auth.php');

        // Check if api.php already includes api-auth.php
        $apiRoutesPath = \base_path('routes/api.php');
        if ($this->files->exists($apiRoutesPath)) {
            $contents = $this->files->get($apiRoutesPath);
            $requireStatement = "require __DIR__.'/api-auth.php';";

            if (!str_contains($contents, 'api-auth.php')) {
                $this->files->append($apiRoutesPath, "\n\n// USIM Auth Routes\n{$requireStatement}\n");
                $this->line('  <fg=green>✓</> Added require to routes/api.php');
            } else {
                $this->line('  <fg=blue>→</> routes/api.php already includes api-auth.php');
            }
        }
    }

    // =========================================================================
    // Web Routes (catch-all)
    // =========================================================================

    protected function installWebRoutes(): void
    {
        $this->newLine();
        $this->info('Installing web routes...');

        $webRoutesPath = \base_path('routes/web.php');
        $contents = $this->files->exists($webRoutesPath) ? $this->files->get($webRoutesPath) : '';

        [$contents, $disabledDefaultWelcomeRoute] = $this->disableDefaultWelcomeRoute($contents);
        if ($disabledDefaultWelcomeRoute) {
            $this->files->put($webRoutesPath, $contents);
            $this->line('  <fg=green>✓</> Default welcome route disabled in routes/web.php');
        }

        if (str_contains($contents, 'ui.catchall')) {
            $this->line('  <fg=blue>→</> Catch-all route already exists in routes/web.php');
            return;
        }

        $stubContent = $this->files->get($this->stubsPath('routes/web.php.stub'));

        $this->files->append($webRoutesPath, "\n" . $stubContent);
        $this->line('  <fg=green>✓</> Catch-all route added to routes/web.php');
    }

    /**
     * Disable Laravel default welcome route to avoid conflicts with USIM catch-all route.
     *
     * @return array{0: string, 1: bool}
     */
    protected function disableDefaultWelcomeRoute(string $contents): array
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
                        static fn(string $line): string => '// ' . $line,
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

    // =========================================================================
    // .env Variables
    // =========================================================================

    protected function appendEnvVariables(): void
    {
        $this->newLine();
        $this->info('Configuring .env...');

        $envPath = \base_path('.env');

        if (!$this->files->exists($envPath)) {
            $envExamplePath = \base_path('.env.example');

            if ($this->files->exists($envExamplePath)) {
                $envPath = $envExamplePath;
                $this->line('  <fg=blue>→</> .env not found, using .env.example');
            } else {
                $this->line('  <fg=yellow>!</> .env and .env.example not found, skipping');
                return;
            }
        }

        $envContent = $this->files->get($envPath);
        $stubContent = $this->files->get($this->stubsPath('env.stub'));
        $variables = explode("\n", $stubContent);

        $appendContent = '';
        foreach ($variables as $line) {
            $line = trim($line);
            if (empty($line) || str_starts_with($line, '#')) {
                continue;
            }

            // Extract variable name
            $parts = explode('=', $line, 2);
            $key = trim($parts[0] ?? '');

            // Check if key exists (ensure it's the full key, not a suffix)
            if ($key && !preg_match("/(^|\n)\s*" . preg_quote($key, '/') . "\s*=/m", $envContent)) {
                $appendContent .= $line . "\n";
            }
        }

        if (!empty($appendContent)) {
            $this->info('  Appending missing environment variables...');
            $this->files->append($envPath, "\n# --- USIM Framework ---\n" . trim($appendContent) . "\n");
            $this->line('  <fg=green>✓</> .env updated');
        } else {
            $this->line('  <fg=blue>→</> USIM environment variables already present');
        }
    }

    // =========================================================================
    // Post-Install Instructions
    // =========================================================================

    protected function printPostInstallInstructions(): void
    {
        $this->components->info('Next steps:');

        $steps = [];

        $userModelPath = \app_path('Models/User.php');
        $databaseSeederPath = \database_path('seeders/DatabaseSeeder.php');

        $steps[] = "Ensure your <href=file://{$userModelPath}>User</> model implements:\n" .
            "     <fg=yellow>MustVerifyEmail</> and <fg=yellow>CanResetPassword</> interfaces, and uses the <fg=yellow>UsimUser</> trait\n";

        $steps[] = "Add <fg=yellow>RoleSeeder::class</> and <fg=yellow>UserSeeder::class</> to your " .
            "<href=file://{$databaseSeederPath}>DatabaseSeeder</>:\n" .
            "     <fg=gray>class DatabaseSeeder extends Seeder {\n" .
            "         use WithoutModelEvents;\n\n" .
            "         public function run(): void {\n" .
            "             \$this->call(RoleSeeder::class);\n" .
            "             \$this->call(UserSeeder::class);\n" .
            "         }\n" .
            "     }</fg=gray>\n";
        $steps[] = "Run <fg=yellow>php artisan usim:discover</> after creating new screens\n";
        $steps[] = "Run <fg=yellow>./start.sh [-r]</> to start the development server.\n" .
            "     <fg=gray>Note: -r option removes database and starts fresh)</fg=gray>";

        foreach ($steps as $i => $step) {
            $num = $i + 1;
            $this->line("  {$num}. {$step}");
        }

        $this->newLine();
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    protected function stubsPath(string $path = ''): string
    {
        return dirname(__DIR__, 3) . '/stubs/' . ltrim($path, '/');
    }

    protected function publishStub(string $stubPath, string $targetPath, array $replacements, ?callable $postInstallCallback = null): void
    {
        if ($this->files->exists($targetPath) && !$this->force) {
            if ($postInstallCallback) {
                $postInstallCallback($targetPath);
            }
            return;
        }

        $directory = dirname($targetPath);
        if (!$this->files->isDirectory($directory)) {
            $this->files->makeDirectory($directory, 0755, true);
        }

        $content = $this->files->get($stubPath);

        foreach ($replacements as $search => $replace) {
            $content = str_replace($search, $replace, $content);
        }

        $this->files->put($targetPath, $content);

        if ($postInstallCallback) {
            $postInstallCallback($targetPath);
        }
    }

    protected function resolveUserModelImport(): string
    {
        // Check if the app has a custom User model location
        $authConfig = \config('auth.providers.users.model', 'App\\Models\\User');
        return $authConfig;
    }

    protected function resolveUserModelClass(): string
    {
        $fullClass = $this->resolveUserModelImport();
        return \class_basename($fullClass);
    }
}
