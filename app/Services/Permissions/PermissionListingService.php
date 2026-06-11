<?php

namespace App\Services\Permissions;

use Idei\Usim\Support\EloquentListingService;
use Spatie\Permission\Models\Permission;

/**
 * Read-only listing service for permissions.
 */
class PermissionListingService extends EloquentListingService
{
    protected string $modelClass = Permission::class;

    protected array $with = [];

    /**
     * @return array<string, string>
     */
    protected function searchableFields(): array
    {
        return [
            'name' => 'name',
        ];
    }

    /**
     * @return array<string, array{path: string, operator?: string, cast?: 'int'|'float'|'bool'|'string'}>
     */
    protected function filterableFields(): array
    {
        return [];
    }

    /**
     * @return array<string, string>
     */
    protected function sortableFields(): array
    {
        return [
            'name' => 'name',
        ];
    }

    /**
     * @return array{field: string, direction: 'asc'|'desc'}
     */
    protected function defaultSort(): array
    {
        return [
            'field' => 'name',
            'direction' => 'asc',
        ];
    }
}
