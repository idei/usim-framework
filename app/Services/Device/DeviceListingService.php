<?php

// @usim: feature="admin", type="service"

namespace App\Services\Device;

use App\Models\Device;
use Idei\Usim\Support\EloquentListingService;

/**
 * Listing service for devices.
 *
 * @extends EloquentListingService<Device>
 */
class DeviceListingService extends EloquentListingService
{
    protected string $modelClass = Device::class;

    protected array $with = ['tokens', 'roles', 'usimUnits'];

    /**
     * @return array<string, string>
     */
    protected function searchableFields(): array
    {
        return [
            'name' => 'name',
            'pairing_pin' => 'pairing_pin',
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
            'created_at' => 'created_at',
            'updated_at' => 'updated_at',
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

