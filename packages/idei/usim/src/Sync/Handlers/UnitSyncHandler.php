<?php

namespace Idei\Usim\Sync\Handlers;

use Idei\Usim\Contracts\SyncEntityHandlerInterface;
use Idei\Usim\Contracts\SyncResultInterface;
use Idei\Usim\Contracts\UnitsServiceInterface;
use Idei\Usim\Contracts\UnitSyncResult;
use Idei\Usim\Sync\Concerns\WritesSyncOutput;
use Illuminate\Console\Command;
use Illuminate\Console\OutputStyle;

class UnitSyncHandler implements SyncEntityHandlerInterface
{
    use WritesSyncOutput;

    public function getIdentifier(): string
    {
        return 'units';
    }

    public function getDescription(): string
    {
        return 'organizational units';
    }

    /**
     * @return list<string>
     */
    public function getAliases(): array
    {
        return [];
    }

    public function getOrder(): int
    {
        return 20;
    }

    public function sync(Command|OutputStyle|null $output = null): SyncResultInterface
    {
        /** @var UnitsServiceInterface|null $unitsService */
        $unitsService = app()->bound(UnitsServiceInterface::class)
            ? app(UnitsServiceInterface::class)
            : (class_exists('App\\Services\\Units\\UnitsService') ? app('App\\Services\\Units\\UnitsService') : null);

        if (! $unitsService) {
            $this->writeWarn($output, 'UnitsService is not available. Skipping unit synchronization.');

            return UnitSyncResult::skipped('UnitsService is not available.');
        }

        if (! $unitsService->isTeamsEnabled()) {
            $this->writeWarn($output, 'Units are disabled in the Spatie (permission.php) configuration. Skipping unit synchronization.');

            return UnitSyncResult::skipped('Units are disabled in the Spatie configuration.');
        }

        $this->writeInfo($output, 'Synchronizing organizational units...');

        $progressBar = null;

        $result = $unitsService->sync(
            onProgress: function (int $current, int $total, string $slug) use ($output, &$progressBar): void {
                if ($output === null) {
                    return;
                }
                if ($progressBar === null) {
                    $progressBar = $output instanceof Command
                        ? $output->getOutput()->createProgressBar($total)
                        : $output->createProgressBar($total);
                    $progressBar->start();
                }
                $progressBar->advance();
            }
        );

        if ($progressBar !== null) {
            $progressBar->finish();
            $this->writeNewLine($output, 2);
        }

        if ($result->deletedCount > 0) {
            $this->writeWarn($output, "Removed {$result->deletedCount} obsolete units.");
        }

        if (! empty($result->generatedTranslationFiles)) {
            $this->writeLine($output, '<comment>Language files generated:</comment> lang/{locale}/unit.php');
        }

        $this->writeInfo($output, 'Synchronization of organizational units completed successfully.');

        return $result;
    }
}
