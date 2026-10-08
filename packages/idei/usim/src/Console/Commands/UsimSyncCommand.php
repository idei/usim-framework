<?php

namespace Idei\Usim\Console\Commands;

use Idei\Usim\Contracts\SyncEntityHandlerInterface;
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
            if (! app()->environment('production')) {
                $this->call('usim:discover');
            } else {
                $this->line('<comment>Skipping screen discovery in production environment.</comment>');
            }
        }

        if ($target === 'screens') {
            return self::SUCCESS;
        }

        /** @var iterable<int|string, SyncEntityHandlerInterface> $handlers */
        $handlers = $this->laravel->tagged('usim.sync_handlers');

        /** @var list<SyncEntityHandlerInterface> $sortedHandlers */
        $sortedHandlers = collect($handlers)
            ->sortBy(fn (SyncEntityHandlerInterface $handler): int => $handler->getOrder())
            ->values()
            ->all();

        foreach ($sortedHandlers as $handler) {
            $matchesTarget = $target === 'all'
                || $handler->getIdentifier() === $target
                || \in_array($target, $handler->getAliases(), true);

            if ($matchesTarget) {
                $handler->sync($this);
            }
        }

        return self::SUCCESS;
    }
}
