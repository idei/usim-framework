<?php

namespace Idei\Usim\Sync\Handlers;

use Idei\Usim\Contracts\DeviceSyncResult;
use Idei\Usim\Contracts\SyncEntityHandlerInterface;
use Idei\Usim\Contracts\SyncResultInterface;
use Idei\Usim\Support\DeviceSyncService;
use Idei\Usim\Sync\Concerns\WritesSyncOutput;
use Illuminate\Console\Command;
use Illuminate\Console\OutputStyle;

class DeviceSyncHandler implements SyncEntityHandlerInterface
{
    use WritesSyncOutput;

    public function __construct(
        protected DeviceSyncService $syncService
    ) {}

    public function getIdentifier(): string
    {
        return 'devices';
    }

    public function getDescription(): string
    {
        return 'hardware devices';
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
        return 40;
    }

    public function sync(Command|OutputStyle|null $output = null): SyncResultInterface
    {
        /** @var mixed $rawDevicesConfig */
        $rawDevicesConfig = config('usim.devices', []);

        /** @var array<string, array<string, mixed>> $devicesConfig */
        $devicesConfig = is_array($rawDevicesConfig) ? $rawDevicesConfig : [];

        if (empty($devicesConfig)) {
            $this->writeLine($output, 'No devices configured in usim.php to sync. Skipping.');

            return DeviceSyncResult::skipped('No devices configured in usim.php to sync.');
        }

        $this->writeInfo($output, 'Synchronizing hardware devices...');

        $result = $this->syncService->sync($devicesConfig);

        foreach ($result->synced as $syncedName) {
            $this->writeLine($output, "<fg=green>✓</> Dispositivo sincronizado: {$syncedName}");
        }

        foreach ($result->errors as $error) {
            $this->writeWarn($output, "⚠ {$error}");
        }

        $this->writeInfo($output, 'Synchronization of hardware devices completed.');

        return $result;
    }
}
