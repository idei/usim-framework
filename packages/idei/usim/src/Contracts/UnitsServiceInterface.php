<?php

namespace Idei\Usim\Contracts;

/**
 * Service interface for synchronizing and checking status of organizational units.
 */
interface UnitsServiceInterface
{
    /**
     * Determine whether organizational units (teams) are enabled.
     */
    public function isTeamsEnabled(): bool;

    /**
     * Synchronize organizational units based on the given structure or configuration.
     *
     * @param array<string, array{
     *     type?: string|null,
     *     parent?: string|null,
     *     default_translations?: array<string, mixed>
     * }>|null $structure
     * @param  (callable(int $current, int $total, string $slug): void)|null  $onProgress
     */
    public function sync(?array $structure = null, ?callable $onProgress = null, ?string $baseLangPath = null): UnitSyncResult;
}
