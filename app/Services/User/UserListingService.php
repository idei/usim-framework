<?php

// @usim: feature="admin", type="service"

namespace App\Services\User;

use App\Models\User;
use Idei\Usim\Support\EloquentListingService;

/**
 * Read-only listing service for users.
 *
 * @extends EloquentListingService<User>
 */
class UserListingService extends EloquentListingService
{
    protected string $modelClass = User::class;

    protected array $with = ['roles'];

    /**
     * @return array<string, string>
     */
    protected function searchableFields(): array
    {
        return [
            'name' => 'name',
            'email' => 'email',
            'role_name' => 'roles.name',
        ];
    }

    /**
     * @return array<string, array{path: string, operator?: string, cast?: 'int'|'float'|'bool'|'string'}>
     */
    protected function filterableFields(): array
    {
        return [
            'role_name' => [
                'path' => 'roles.name',
                'operator' => 'like',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function sortableFields(): array
    {
        return [
            'name' => 'name',
            'email' => 'email',
            'email_verified_at' => 'email_verified_at',
            'updated_at' => 'updated_at',
            'created_at' => 'created_at',
        ];
    }

    /**
     * @return array{field: string, direction: 'asc'|'desc'}
     */
    protected function defaultSort(): array
    {
        return [
            'field' => 'updated_at',
            'direction' => 'desc',
        ];
    }
}
