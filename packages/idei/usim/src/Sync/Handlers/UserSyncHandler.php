<?php

namespace Idei\Usim\Sync\Handlers;

use Idei\Usim\Contracts\SyncEntityHandlerInterface;
use Idei\Usim\Contracts\SyncResultInterface;
use Idei\Usim\Support\UsersSyncService;
use Idei\Usim\Sync\Concerns\WritesSyncOutput;
use Illuminate\Console\Command;
use Illuminate\Console\OutputStyle;

class UserSyncHandler implements SyncEntityHandlerInterface
{
    use WritesSyncOutput;

    public function __construct(
        protected UsersSyncService $syncService
    ) {}

    public function getIdentifier(): string
    {
        return 'users';
    }

    public function getDescription(): string
    {
        return 'users';
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
        return 30;
    }

    public function sync(Command|OutputStyle|null $output = null): SyncResultInterface
    {
        $this->writeInfo($output, 'Synchronizing users...');

        $result = $this->syncService->sync();

        $this->writeLine($output, "<fg=green>✓</> Users created: {$result->usersCreated}");
        $this->writeLine($output, "<fg=green>✓</> Users updated: {$result->usersUpdated}");
        $this->writeLine($output, "<fg=green>✓</> Users deleted: {$result->usersDeleted}");
        $this->writeInfo($output, 'Synchronization of users completed.');
        $this->writeNewLine($output);

        return $result;
    }
}
