<?php

namespace Idei\Usim\Sync\Handlers;

use Idei\Usim\Contracts\SyncEntityHandlerInterface;
use Idei\Usim\Contracts\SyncResultInterface;
use Idei\Usim\Support\RoleAndPermissionSyncService;
use Idei\Usim\Sync\Concerns\WritesSyncOutput;
use Illuminate\Console\Command;
use Illuminate\Console\OutputStyle;

class RoleSyncHandler implements SyncEntityHandlerInterface
{
    use WritesSyncOutput;

    public function __construct(
        protected RoleAndPermissionSyncService $syncService
    ) {}

    public function getIdentifier(): string
    {
        return 'roles';
    }

    public function getDescription(): string
    {
        return 'roles and permissions';
    }

    /**
     * @return list<string>
     */
    public function getAliases(): array
    {
        return ['permissions'];
    }

    public function getOrder(): int
    {
        return 10;
    }

    public function sync(Command|OutputStyle|null $output = null): SyncResultInterface
    {
        $this->writeInfo($output, 'Synchronizing roles and permissions...');

        $result = $this->syncService->sync();

        $this->writeLine($output, "<fg=green>✓</> Permissions created: {$result->permissionsCreated}");
        $this->writeLine($output, "<fg=green>✓</> Roles created: {$result->rolesCreated}");
        $this->writeLine($output, "<fg=green>✓</> Roles updated: {$result->rolesUpdated}");
        $this->writeInfo($output, 'Synchronization of roles and permissions completed.');
        $this->writeNewLine($output);

        return $result;
    }
}
