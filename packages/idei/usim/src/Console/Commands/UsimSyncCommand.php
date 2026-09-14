<?php

namespace Idei\Usim\Console\Commands;

use Idei\Usim\Support\DeviceSyncService;
use Idei\Usim\Support\RoleAndPermissionSyncService;
use Illuminate\Console\Command;

class UsimSyncCommand extends Command
{
    protected $signature = 'usim:sync {target? : Element to sync (units, roles, permissions, all)}';

    protected $description = 'Syncs the system configuration with the database. This includes units, roles, permissions, and other related entities.';

    public function handle(): int
    {
        // App\Contracts\UnitsServiceContract only exists after `usim:install` has
        // published its stub, so it must be resolved lazily (not via constructor
        // injection) to avoid breaking `composer install`/`package:discover` on a
        // fresh app that hasn't installed USIM yet.
        if (!interface_exists(\App\Contracts\UnitsServiceContract::class)) {
            $this->warn('App\\Contracts\\UnitsServiceContract not found. Run "php artisan usim:install" first.');
            return self::SUCCESS;
        }

        $target = $this->argument('target') ?? 'all';

        if (\in_array($target, ['roles', 'permissions', 'all'], true)) {
            $this->syncRolesAndPermissions();
        }

        if (\in_array($target, ['units', 'all'], true)) {
            $this->syncUnits();
        }

        if (\in_array($target, ['devices', 'all'], true)) {
            $this->syncDevices();
        }

        return self::SUCCESS;
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
        $unitsService = $this->laravel->make(\App\Contracts\UnitsServiceContract::class);

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
}
