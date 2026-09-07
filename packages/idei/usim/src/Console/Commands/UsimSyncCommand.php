<?php

namespace Idei\Usim\Console\Commands;

use App\Contracts\UnitsServiceContract;
use Illuminate\Console\Command;

class UsimSyncCommand extends Command
{
    protected $signature = 'usim:sync {target? : Element to sync (units, roles, permissions, all)}';

    protected $description = 'Syncs the system configuration with the database. This includes units, roles, permissions, and other related entities.';

    public function __construct(
        protected UnitsServiceContract $unitsService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $target = $this->argument('target') ?? 'all';

        if (\in_array($target, ['units', 'all'], true)) {
            $this->syncUnits();
        }

        // Here you can add $this->syncRoles(), $this->syncPermissions(), etc.
        return self::SUCCESS;
    }

    protected function syncUnits(): void
    {
        if (!$this->unitsService->isTeamsEnabled()) {
            $this->warn('Units are disabled in the Spatie (permission.php) configuration. Skipping unit synchronization.');
            return;
        }

        $this->info('Synchronizing organizational units...');

        $progressBar = null;

        $result = $this->unitsService->sync(
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
}
