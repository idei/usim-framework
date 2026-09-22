<?php

namespace Idei\Usim\Console\Commands;

use App\Services\Units\UnitsService;
use Idei\Usim\Support\DeviceSyncService;
use Idei\Usim\Support\RoleAndPermissionSyncService;
use Idei\Usim\Support\UsersSyncService;
use Idei\Usim\Support\LangSyncService;
use Illuminate\Console\Command;

class UsimSyncCommand extends Command
{
    protected $signature = 'usim:sync {target? : Element to sync (units, roles, permissions, screens, all)} {--discover : Force running screen discovery before sync}';

    protected $description = 'Syncs the system configuration with the database. This includes units, roles, permissions, screens, and other related entities.';

    public function handle(): int
    {
        $target = $this->argument('target') ?? 'all';
        $shouldDiscover = (bool) $this->option('discover') || \in_array($target, ['screens', 'all'], true);

        if ($shouldDiscover) {
            if (!app()->environment('production')) {
                $this->call('usim:discover');
            } else {
                $this->line('<comment>Skipping screen discovery in production environment.</comment>');
            }
        }

        if (\in_array($target, ['roles', 'permissions', 'all'], true)) {
            $this->syncRolesAndPermissions();
        }

        if (\in_array($target, ['units', 'all'], true)) {
            $this->syncUnits();
        }

        if (\in_array($target, ['users', 'all'], true)) {
            $this->syncUsers();
        }

        if (\in_array($target, ['devices', 'all'], true)) {
            $this->syncDevices();
        }

        if (\in_array($target, ['lang', 'all'], true)) {
            $this->syncLang();
        }

        return self::SUCCESS;
    }

    protected function syncUsers(): void
    {
        $this->info('Synchronizing users...');

        $syncService = $this->laravel->make(UsersSyncService::class);
        $stats = $syncService->sync();

        $this->line("<fg=green>✓</> Users created: {$stats['users_created']}");
        $this->line("<fg=green>✓</> Users updated: {$stats['users_updated']}");
        $this->line("<fg=green>✓</> Users deleted: {$stats['users_deleted']}");

        $this->info('Synchronization of users completed.');
        $this->newLine();
    }

    protected function syncRolesAndPermissions(): void
    {
        $this->info('Synchronizing roles and permissions...');

        $syncService = $this->laravel->make(RoleAndPermissionSyncService::class);
        $stats = $syncService->sync();

        $this->line("<fg=green>✓</> Permissions created: {$stats['permissions_created']}");
        $this->line("<fg=green>✓</> Roles created: {$stats['roles_created']}");
        $this->line("<fg=green>✓</> Roles updated: {$stats['roles_updated']}");

        $this->info('Synchronization of roles and permissions completed.');
        $this->newLine();
    }

    protected function syncUnits(): void
    {
        $unitsService = app(UnitsService::class);

        if (!$unitsService->isTeamsEnabled()) {
            $this->warn('Units are disabled in the Spatie (permission.php) configuration. Skipping unit synchronization.');
            return;
        }

        $this->info('Synchronizing organizational units...');

        $progressBar = null;

        $result = $unitsService->sync(
            onProgress: function (int $current, int $total, string $slug) use (&$progressBar): void {
                if ($progressBar === null) {
                    $progressBar = $this->output->createProgressBar($total);
                    $progressBar->start();
                }
                $progressBar->advance();
            }
        );

        if ($progressBar !== null) {
            $progressBar->finish();
            $this->newLine(2);
        }

        if ($result->deletedCount > 0) {
            $this->warn("Removed {$result->deletedCount} obsolete units.");
        }

        if (!empty($result->generatedTranslationFiles)) {
            $this->line('<comment>Language files generated:</comment> lang/{locale}/unit.php');
        }

        $this->info('Synchronization of organizational units completed successfully.');
    }

    protected function syncDevices(): void
    {
        // El servicio DeviceSyncService es interno del paquete, así que podemos resolverlo directamente
        $syncService = $this->laravel->make(DeviceSyncService::class);

        /** @var mixed $rawDevicesConfig */
        $rawDevicesConfig = config('usim.devices', []);

        /** @var array<string, array<string, mixed>> $devicesConfig */
        $devicesConfig = is_array($rawDevicesConfig) ? $rawDevicesConfig : [];

        if (empty($devicesConfig)) {
            $this->line('No devices configured in usim.php to sync. Skipping.');
            return;
        }

        $this->info('Synchronizing hardware devices...');

        $results = $syncService->sync($devicesConfig);

        // Feedback visual de éxitos
        foreach ($results['synced'] as $syncedName) {
            $this->line("<fg=green>✓</> Dispositivo sincronizado: {$syncedName}");
        }

        // Feedback visual de errores (Ej: Modelo no publicado, o Unidad faltante)
        foreach ($results['errors'] as $error) {
            $this->warn("⚠ {$error}");
        }

        $this->info('Synchronization of hardware devices completed.');
    }

    protected function syncLang(): void
    {
        $this->info('Synchronizing language files...');
        // El servicio LangSyncService es interno del paquete, así que podemos resolverlo directamente
        $syncService = $this->laravel->make(LangSyncService::class);
        $stats = $syncService->sync();

        $this->line("<fg=green>✓</> Languages created: {$stats['languages_created']}");
        $this->line("<fg=green>✓</> Languages updated: {$stats['languages_updated']}");

        $this->info('Synchronization of languages completed.');
        $this->newLine();
    }
}
