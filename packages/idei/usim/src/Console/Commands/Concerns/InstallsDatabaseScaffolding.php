<?php

namespace Idei\Usim\Console\Commands\Concerns;

trait InstallsDatabaseScaffolding
{
    protected function installMigrations(): void
    {
        $migrationStubs = [
            'create_temporary_uploads_table',
            'add_profile_image_to_users_table',
            'add_terms_accepted_at_to_users_table',
            'create_usim_languages_table',
            'create_usim_text_keys_table',
            'create_usim_text_values_table',
        ];

        foreach ($migrationStubs as $index => $migrationName) {
            $this->installStubMigration($migrationName, $index);
        }

        if (!$this->migrationExists('create_personal_access_tokens_table')) {
            $this->callSilently('vendor:publish', [
                '--tag' => 'sanctum-migrations',
            ]);
            $this->line('  <fg=green>✓</> Sanctum migrations published');
        } else {
            $this->line('  <fg=blue>→</> Sanctum migrations already exist');
        }

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

    protected function installSeeders(): void
    {
        $seedersPath = \database_path('seeders');

        $seederStubs = [
            'UsimRoleSeeder.php.stub' => [
                'target' => 'UsimRoleSeeder.php',
                'replacements' => [],
            ],
            'UsimUserSeeder.php.stub' => [
                'target' => 'UsimUserSeeder.php',
                'replacements' => [
                    '{{ userModel }}' => $this->resolveUserModelImport(),
                    '{{ userModelClass }}' => $this->resolveUserModelClass(),
                ],
            ],
            'UsimLanguageSeeder.php.stub' => [
                'target' => 'UsimLanguageSeeder.php',
                'replacements' => [],
            ],
            'UsimTranslationSeeder.php.stub' => [
                'target' => 'UsimTranslationSeeder.php',
                'replacements' => [],
            ],
            'UsimSeeder.php.stub' => [
                'target' => 'UsimSeeder.php',
                'replacements' => [],
            ],
        ];

        foreach ($seederStubs as $stub => $definition) {
            $targetPath = $seedersPath . '/' . $definition['target'];
            $stubPath = $this->stubsPath('seeders/' . $stub);
            $this->publishStub($stubPath, $targetPath, $definition['replacements']);
            $this->line('  <fg=green>✓</> ' . pathinfo($definition['target'], PATHINFO_FILENAME));
        }

        $this->info('USIM uses its own seeder (UsimSeeder) to preserve the integrity of existing project seeders.');
        $this->line('Run: php artisan db:seed --class=UsimSeeder');
    }

    protected function installStubMigration(string $migrationName, int $offsetSeconds): void
    {
        if ($this->migrationExists($migrationName)) {
            $this->line("  <fg=blue>→</> {$migrationName} already exists");
            return;
        }

        $migrationsPath = \database_path('migrations');
        $timestamp = date('Y_m_d_His', time() + $offsetSeconds);
        $stubPath = $this->stubsPath("migrations/{$migrationName}.php.stub");
        $target = $migrationsPath . "/{$timestamp}_{$migrationName}.php";

        $this->publishStub($stubPath, $target, []);
        $this->line("  <fg=green>✓</> {$migrationName} migration");
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
}
