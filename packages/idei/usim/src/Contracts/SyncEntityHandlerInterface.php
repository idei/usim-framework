<?php

namespace Idei\Usim\Contracts;

/**
 * Contract for extensible synchronization handlers (Open/Closed Principle).
 */
interface SyncEntityHandlerInterface
{
    /**
     * Unique identifier for the sync target (e.g. 'users', 'roles', 'devices', 'units', 'lang').
     */
    public function getIdentifier(): string;

    /**
     * Human-readable description of the entity being synchronized.
     */
    public function getDescription(): string;

    /**
     * Secondary targets or aliases that map to this handler (e.g. ['permissions'] for roles).
     *
     * @return list<string>
     */
    public function getAliases(): array;

    /**
     * Execution order when synchronizing all targets. Lower values execute first.
     */
    public function getOrder(): int;

    /**
     * Execute the synchronization.
     */
    public function sync(\Illuminate\Console\Command|\Illuminate\Console\OutputStyle|null $output = null): SyncResultInterface;
}
