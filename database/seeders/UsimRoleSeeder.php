<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Idei\Usim\Screen;
use Idei\Usim\Support\ScreenDiscoveryService;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class UsimRoleSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $rolesConfig = config('users.roles', []);
        $screenPermissions = collect(app(ScreenDiscoveryService::class)->discover())
            ->keys()
            ->filter(fn ($screenClass) => is_string($screenClass) && class_exists($screenClass))
            ->filter(fn ($screenClass) => is_subclass_of($screenClass, Screen::class))
            ->map(function (string $screenClass): ?string {
                /** @var class-string<Screen> $screenClass */
                return $this->permissionFromRoutePath($screenClass::getRoutePath());
            })
            ->filter(fn ($permission) => is_string($permission) && $permission !== '')
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
            if (!is_string($roleName) || $roleName === '') {
                continue;
            }

            $role = Role::firstOrCreate(['name' => $roleName]);

            // Keep admin as super-role; other roles start without screen permissions.
            if ($roleName === 'admin') {
                $role->syncPermissions(Permission::all());
                continue;
            }

            $defaultScreenPermission = $this->permissionFromScreenClass(
                is_array($roleMeta) ? ($roleMeta['default_screen'] ?? null) : null
            );

            if ($defaultScreenPermission !== null) {
                $role->syncPermissions([$defaultScreenPermission]);
                continue;
            }

            $role->syncPermissions([]);
        }
    }

    private function permissionFromScreenClass(mixed $screenClass): ?string
    {
        if (!is_string($screenClass) || !class_exists($screenClass)) {
            return null;
        }

        if (!is_subclass_of($screenClass, Screen::class)) {
            return null;
        }

        /** @var class-string<Screen> $screenClass */
        return $this->permissionFromRoutePath($screenClass::getRoutePath());
    }

    private function permissionFromRoutePath(string $routePath): string
    {
        $normalized = trim($routePath, '/');

        if ($normalized === '') {
            return 'screen.access.root';
        }

        return 'screen.access.' . str_replace('/', '.', $normalized);
    }
}
