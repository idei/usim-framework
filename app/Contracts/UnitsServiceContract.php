<?php
// @usim: feature="admin", type="contract"
namespace App\Contracts;

use App\Services\Units\UnitSyncResult;

interface UnitsServiceContract
{
    /**
     * Determine whether organizational units (teams) are enabled in the application.
     */
    public function isTeamsEnabled(): bool;

    /**
     * Synchronize organizational units based on the given structure or config.
     *
     * @param array<string, array{
     *     type?: string|null,
     *     parent?: string|null,
     *     default_translations?: array<string, mixed>
     * }>|null $structure
     * @param (callable(int $current, int $total, string $slug): void)|null $onProgress
     * @param string|null $baseLangPath Optional base directory for translation files
     */
    public function sync(?array $structure = null, ?callable $onProgress = null, ?string $baseLangPath = null): UnitSyncResult;

    /**
     * Remove units from the database that are no longer present in the configuration.
     *
     * @param array<int, string> $configuredSlugs
     */
    public function deleteObsoleteUnits(array $configuredSlugs): int;

    /**
     * Create or update units from the given structure.
     *
     * @param array<string, array{
     *     type?: string|null,
     *     parent?: string|null,
     *     default_translations?: array<string, mixed>
     * }> $structure
     * @param (callable(int $current, int $total, string $slug): void)|null $onProgress
     */
    public function upsertUnits(array $structure, ?callable $onProgress = null): int;

    /**
     * Update parent-child relationships according to the structure.
     *
     * @param array<string, array{
     *     type?: string|null,
     *     parent?: string|null,
     *     default_translations?: array<string, mixed>
     * }> $structure
     */
    public function updateHierarchy(array $structure): int;

    /**
     * Generate unit translation files for all configured locales.
     *
     * @param array<string, array{
     *     type?: string|null,
     *     parent?: string|null,
     *     default_translations?: array<string, mixed>
     * }> $structure
     * @param string $filePrefix
     * @param string|null $baseLangPath Optional base directory for translation files
     * @return array<int, string> List of generated file paths
     */
    public function syncTranslations(array $structure, string $filePrefix = 'unit', ?string $baseLangPath = null): array;
}

