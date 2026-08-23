<?php

// @usim: feature="admin", type="service"

namespace App\Services\Role;

use Idei\Usim\Models\UsimRole;

class RoleService
{
    /**
     * Return all permission IDs associated with a role.
     *
     * @param UsimRole|int|string $role
     * @return list<int|string>
     */
    public function getPermissionIds(UsimRole|int|string $role): array
    {
        $roleModel = $role instanceof UsimRole
            ? $role
            : (UsimRole::find($role) ?? UsimRole::query()->where('name', (string) $role)->first());

        if (!$roleModel instanceof UsimRole) {
            return [];
        }

        if ($roleModel->relationLoaded('permissions')) {
            /** @var list<int|string> $ids */
            $ids = $roleModel->permissions
                ->pluck('id')
                ->map(static fn(mixed $id): int|string => is_numeric($id) ? (int) $id : (string) $id)
                ->values()
                ->toArray();

            return $ids;
        }

        /** @var list<int|string> $ids */
        $ids = $roleModel->permissions()
            ->pluck('permissions.id')
            ->map(static fn(mixed $id): int|string => is_numeric($id) ? (int) $id : (string) $id)
            ->values()
            ->toArray();

        return $ids;
    }
}