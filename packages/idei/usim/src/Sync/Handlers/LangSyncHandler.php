<?php

namespace Idei\Usim\Sync\Handlers;

use Idei\Usim\Contracts\SyncEntityHandlerInterface;
use Idei\Usim\Contracts\SyncResultInterface;
use Idei\Usim\Support\LangSyncService;
use Idei\Usim\Sync\Concerns\WritesSyncOutput;
use Illuminate\Console\Command;
use Illuminate\Console\OutputStyle;

class LangSyncHandler implements SyncEntityHandlerInterface
{
    use WritesSyncOutput;

    public function __construct(
        protected LangSyncService $syncService
    ) {}

    public function getIdentifier(): string
    {
        return 'lang';
    }

    public function getDescription(): string
    {
        return 'language files';
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
        return 50;
    }

    public function sync(Command|OutputStyle|null $output = null): SyncResultInterface
    {
        $this->writeInfo($output, 'Synchronizing language files...');

        $result = $this->syncService->sync();

        $this->writeLine($output, "<fg=green>✓</> Languages created: {$result->languagesCreated}");
        $this->writeLine($output, "<fg=green>✓</> Languages updated: {$result->languagesUpdated}");
        $this->writeInfo($output, 'Synchronization of languages completed.');
        $this->writeNewLine($output);

        return $result;
    }
}
