<?php

namespace Idei\Usim\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class InstallCommand extends Command
{
    protected $signature = 'usim:install
                            {--preset=full : Installation preset (minimal or full)}
                            {--force : Overwrite existing files}';

    protected $description = 'Install the USIM framework scaffolding';

    protected Filesystem $files;
    protected string $preset;
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

        // --- Choose Preset ---
        $this->preset = $this->option('preset');
        if (!in_array($this->preset, ['minimal', 'full'])) {
            $this->preset = $this->choice(
                'Which preset would you like to install?',
                ['full', 'minimal'],
                0
            );
        }

        $this->info("Installing USIM [{$this->preset}] preset...");
        $this->newLine();

        // --- Resolve namespaces ---
        $this->screensNamespace = config('ui-services.screens_namespace', 'App\\UI\\Screens');
        $this->screensPath = config('ui-services.screens_path', app_path('UI/Screens'));
        $this->componentsNamespace = Str::beforeLast($this->screensNamespace, '\\Screens') . '\\Components';
        $this->componentsPath = Str::beforeLast($this->screensPath, '/Screens') . '/Components';

        // === STEP 1: Publish USIM config and assets ===
        $this->publishConfig();
        $this->publishAssets();

        // === STEP 2: Install screens (both presets) ===
        $this->installScreen('Home.php.stub', 'Home.php');

        if ($this->preset === 'minimal') {
            $this->installScreen('MenuMinimal.php.stub', 'Menu.php');
        } else {
            $this->installScreen('Menu.php.stub', 'Menu.php');
        }

        // === STEP 3: Full preset — Auth screens, modals, controller, model, etc. ===
        if ($this->preset === 'full') {
            $this->installFullPreset();
        }

        // === STEP 4: Install web routes (catch-all) ===
        $this->installWebRoutes();

        // === STEP 5: Append .env variables ===
        if ($this->preset === 'full') {
            $this->appendEnvVariables();
        }

        // === STEP 6: Run usim:discover ===
        $this->call('usim:discover');

        // === STEP 7: Summary ===
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

        $relativePath = str_replace(base_path() . '/', '', $targetFile);
        $this->line("  <fg=green>✓</> {$relativePath}");
    }

    // =========================================================================
    // Full Preset
    // =========================================================================

    protected function installFullPreset(): void
    {
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
        $this->installComponent('Modals/LoginDialogService.php.stub', 'LoginDialogService.php', 'Modals');
        $this->installComponent('Modals/RegisterDialogService.php.stub', 'RegisterDialogService.php', 'Modals');

        // AuthController
        $this->newLine();
        $this->info('Installing AuthController...');
        $this->installAuthController();

        // User model
        $this->newLine();
        $this->info('Configuring User model...');
        $this->configureUserModel();

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

        $relativePath = str_replace(base_path() . '/', '', $targetFile);
        $this->line("  <fg=green>✓</> {$relativePath}");
    }

    // =========================================================================
    // AuthController
    // =========================================================================

    protected function installAuthController(): void
    {
        $controllerPath = app_path('Http/Controllers/Api/AuthController.php');
        $stubPath = $this->stubsPath('controllers/AuthController.php.stub');

        $this->publishStub($stubPath, $controllerPath, [
            '{{ namespace }}' => 'App\\Http\\Controllers\\Api',
            '{{ userModel }}' => $this->resolveUserModelImport(),
            '{{ userModelClass }}' => $this->resolveUserModelClass(),
        ]);

        $relativePath = str_replace(base_path() . '/', '', $controllerPath);
        $this->line("  <fg=green>✓</> {$relativePath}");
    }

    // =========================================================================
    // User Model Configuration
    // =========================================================================

    protected function configureUserModel(): void
    {
        $userModelPath = app_path('Models/User.php');

        if (!$this->files->exists($userModelPath)) {
            // No User model — publish ours
            $stubPath = $this->stubsPath('models/User.php.stub');
            $this->publishStub($stubPath, $userModelPath, [
                '{{ namespace }}' => 'App\\Models',
            ]);
            $this->line('  <fg=green>✓</> User model created with USIM traits');
            return;
        }

        // User model exists — check if it already has our traits
        $contents = $this->files->get($userModelPath);

        $modified = false;

        // Check for UsimUser trait
        if (!str_contains($contents, 'UsimUser')) {
            // Add import
            if (!str_contains($contents, 'Idei\\Usim\\Traits\\UsimUser')) {
                $contents = preg_replace(
                    '/(use Illuminate\\\\Foundation\\\\Auth\\\\User as Authenticatable;)/',
                    "$1\nuse Idei\\Usim\\Traits\\UsimUser;",
                    $contents
                );
            }

            // Add trait usage
            $contents = preg_replace(
                '/(use\s+HasFactory\s*,\s*Notifiable)/',
                '$1, UsimUser',
                $contents
            );

            // Fallback: if the pattern above didn't match, try simpler
            if (!str_contains($contents, 'UsimUser')) {
                $contents = preg_replace(
                    '/(use\s+HasFactory)/',
                    '$1, UsimUser',
                    $contents
                );
            }

            $modified = true;
        }

        // Check for HasApiTokens
        if (!str_contains($contents, 'HasApiTokens')) {
            if (!str_contains($contents, 'Laravel\\Sanctum\\HasApiTokens')) {
                $contents = preg_replace(
                    '/(use Illuminate\\\\Foundation\\\\Auth\\\\User as Authenticatable;)/',
                    "use Laravel\\Sanctum\\HasApiTokens;\n$1",
                    $contents
                );
            }

            $contents = preg_replace(
                '/(use\s+HasFactory)/',
                '$1, HasApiTokens',
                $contents
            );

            $modified = true;
        }

        // Check for HasRoles
        if (!str_contains($contents, 'HasRoles')) {
            if (!str_contains($contents, 'Spatie\\Permission\\Traits\\HasRoles')) {
                $contents = preg_replace(
                    '/(use Illuminate\\\\Foundation\\\\Auth\\\\User as Authenticatable;)/',
                    "use Spatie\\Permission\\Traits\\HasRoles;\n$1",
                    $contents
                );
            }

            $contents = preg_replace(
                '/(use\s+HasFactory)/',
                '$1, HasRoles',
                $contents
            );

            $modified = true;
        }

        // Check for MustVerifyEmail interface
        if (!str_contains($contents, 'MustVerifyEmail')) {
            if (!str_contains($contents, 'Illuminate\\Contracts\\Auth\\MustVerifyEmail')) {
                $contents = preg_replace(
                    '/(use Illuminate\\\\Foundation\\\\Auth\\\\User as Authenticatable;)/',
                    "use Illuminate\\Contracts\\Auth\\MustVerifyEmail;\n$1",
                    $contents
                );
            }

            $contents = preg_replace(
                '/(extends\s+Authenticatable)(?!\s+implements)/',
                '$1 implements MustVerifyEmail',
                $contents
            );

            $modified = true;
        }

        if ($modified) {
            $this->files->put($userModelPath, $contents);
            $this->line('  <fg=green>✓</> User model updated with USIM traits');
        } else {
            $this->line('  <fg=blue>→</> User model already configured');
        }
    }

    // =========================================================================
    // Migrations
    // =========================================================================

    protected function installMigrations(): void
    {
        $migrationsPath = database_path('migrations');

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
        $migrationsPath = database_path('migrations');

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
        $seedersPath = database_path('seeders');

        // RoleSeeder
        $roleSeederPath = $seedersPath . '/RoleSeeder.php';
        $stubPath = $this->stubsPath('seeders/RoleSeeder.php.stub');
        $this->publishStub($stubPath, $roleSeederPath, []);
        $this->line('  <fg=green>✓</> RoleSeeder');

        // UsimUserSeeder
        $userSeederPath = $seedersPath . '/UsimUserSeeder.php';
        $stubPath = $this->stubsPath('seeders/UsimUserSeeder.php.stub');
        $this->publishStub($stubPath, $userSeederPath, [
            '{{ userModel }}' => $this->resolveUserModelImport(),
            '{{ userModelClass }}' => $this->resolveUserModelClass(),
        ]);
        $this->line('  <fg=green>✓</> UsimUserSeeder');

        // Suggest adding to DatabaseSeeder
        $databaseSeederPath = $seedersPath . '/DatabaseSeeder.php';
        if ($this->files->exists($databaseSeederPath)) {
            $contents = $this->files->get($databaseSeederPath);
            if (!str_contains($contents, 'RoleSeeder') && !str_contains($contents, 'UsimUserSeeder')) {
                $this->newLine();
                $this->warn('  Add to your DatabaseSeeder::run():');
                $this->line('    $this->call([');
                $this->line('        RoleSeeder::class,');
                $this->line('        UsimUserSeeder::class,');
                $this->line('    ]);');
            }
        }
    }

    // =========================================================================
    // Users Config
    // =========================================================================

    protected function installUsersConfig(): void
    {
        $configPath = config_path('users.php');
        $stubPath = $this->stubsPath('config/users.php.stub');
        $this->publishStub($stubPath, $configPath, []);
        $relativePath = str_replace(base_path() . '/', '', $configPath);
        $this->line("  <fg=green>✓</> {$relativePath}");
    }

    // =========================================================================
    // API Auth Routes
    // =========================================================================

    protected function installApiAuthRoutes(): void
    {
        $targetPath = base_path('routes/api-auth.php');
        $stubPath = $this->stubsPath('routes/api-auth.php.stub');

        $this->publishStub($stubPath, $targetPath, [
            '{{ authControllerClass }}' => 'App\\Http\\Controllers\\Api\\AuthController',
        ]);
        $this->line('  <fg=green>✓</> routes/api-auth.php');

        // Check if api.php already includes api-auth.php
        $apiRoutesPath = base_path('routes/api.php');
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

        $webRoutesPath = base_path('routes/web.php');
        $contents = $this->files->exists($webRoutesPath) ? $this->files->get($webRoutesPath) : '';

        if (str_contains($contents, 'ui.catchall')) {
            $this->line('  <fg=blue>→</> Catch-all route already exists in routes/web.php');
            return;
        }

        $stubContent = $this->files->get($this->stubsPath('routes/web.php.stub'));

        $this->files->append($webRoutesPath, "\n" . $stubContent);
        $this->line('  <fg=green>✓</> Catch-all route added to routes/web.php');
    }

    // =========================================================================
    // .env Variables
    // =========================================================================

    protected function appendEnvVariables(): void
    {
        $this->newLine();
        $this->info('Configuring .env...');

        $envPath = base_path('.env');

        if (!$this->files->exists($envPath)) {
            $this->line('  <fg=yellow>!</> .env file not found, skipping');
            return;
        }

        $envContent = $this->files->get($envPath);

        // Check if USIM variables already exist
        if (str_contains($envContent, 'USIM Framework')) {
            $this->line('  <fg=blue>→</> USIM .env variables already present');
            return;
        }

        $stubContent = $this->files->get($this->stubsPath('env.stub'));
        $this->files->append($envPath, "\n" . trim($stubContent) . "\n");
        $this->line('  <fg=green>✓</> USIM variables appended to .env');
    }

    // =========================================================================
    // Post-Install Instructions
    // =========================================================================

    protected function printPostInstallInstructions(): void
    {
        $this->components->info('Next steps:');
        $this->newLine();

        $steps = [];

        if ($this->preset === 'full') {
            $steps[] = 'Run <fg=yellow>php artisan migrate</> to create database tables';
            $steps[] = 'Run <fg=yellow>php artisan db:seed</> to create default users (configure .env first)';
            $steps[] = 'Add <fg=yellow>RoleSeeder::class</> and <fg=yellow>UsimUserSeeder::class</> to your DatabaseSeeder';
        }

        $steps[] = 'Run <fg=yellow>php artisan usim:discover</> after creating new screens';
        $steps[] = 'Run <fg=yellow>php artisan serve</> and visit <fg=yellow>http://localhost:8000</>';

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

    protected function publishStub(string $stubPath, string $targetPath, array $replacements): void
    {
        if ($this->files->exists($targetPath) && !$this->force) {
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
    }

    protected function resolveUserModelImport(): string
    {
        // Check if the app has a custom User model location
        $authConfig = config('auth.providers.users.model', 'App\\Models\\User');
        return $authConfig;
    }

    protected function resolveUserModelClass(): string
    {
        $fullClass = $this->resolveUserModelImport();
        return class_basename($fullClass);
    }
}
