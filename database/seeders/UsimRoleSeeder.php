<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class UsimRoleSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $rolesConfig = config('usim.users.roles', config('users.roles', []));
        $manifest = $this->loadScreensManifest();

        $screenPermissions = collect($manifest)
            ->pluck('permission')
            ->filter(fn ($permission) => \is_string($permission) && $permission !== '')
            ->values()
            ->all();

        $permissions = collect($screenPermissions)
            ->unique()
            ->values()
            ->all();

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        foreach ($rolesConfig as $roleName => $roleMeta) {
            if (!\is_string($roleName) || $roleName === '') {
                continue;
            }

            $role = Role::firstOrCreate(['name' => $roleName]);

            // Keep admin as super-role; other roles start without screen permissions.
            if ($roleName === 'admin') {
                $role->syncPermissions(Permission::all());
                continue;
            }

            // TODO: this is for "default screen access"
            $defaultScreenPermission = $this->permissionFromScreenClass(
                manifest: $manifest,
                screenClass: \is_array($roleMeta) ? ($roleMeta['default_screen'] ?? null) : null
            );

            if ($defaultScreenPermission !== null) {
                $role->syncPermissions([$defaultScreenPermission]);
                continue;
            }

            $role->syncPermissions([]);
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function loadScreensManifest(): array
    {
        $manifestPath = app()->bootstrapPath('cache/usim_screens.php');

        if (!file_exists($manifestPath)) {
            return [];
        }

        $manifest = require $manifestPath;

        if (!\is_array($manifest)) {
            return [];
        }

        return $manifest;
    }

    /**
     * @param array<string, array<string, mixed>> $manifest
     */
    private function permissionFromScreenClass(array $manifest, mixed $screenClass): ?string
    {
        if (!\is_string($screenClass) || $screenClass === '') {
            return null;
        }

        $screenMeta = $manifest[$screenClass] ?? null;
        if (!\is_array($screenMeta)) {
            return null;
        }

        $permission = $screenMeta['permission'] ?? null;
        if (!\is_string($permission) || $permission === '') {
            return null;
        }

        return $permission;
    }
}
