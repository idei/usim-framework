<?php

namespace Idei\Usim\Support;

use Idei\Usim\Models\UsimRole;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class RoleAndPermissionSyncService
{
    /**
     * Sincroniza roles y permisos desde config('usim') hacia la base de datos.
     *
     * @param string $defaultGuard El guard a usar si la configuración omite uno.
     * @return array{permissions_created: int, roles_created: int, roles_updated: int}
     */
    public function sync(string $defaultGuard = 'web'): array
    {
        $stats = ['permissions_created' => 0, 'roles_created' => 0, 'roles_updated' => 0];

        /** @var array<string, mixed> $usimConfig */
        $usimConfig = config('usim', []);

        /** @var array<string, mixed> $permissionsConfig */
        $permissionsConfig = is_array($usimConfig['permissions'] ?? null) ? $usimConfig['permissions'] : [];

        /** @var list<string> $definedPermissions */
        $definedPermissions = [];
        foreach (array_keys($permissionsConfig) as $permissionName) {
            $definedPermissions[] = $permissionName;
        }

        /** @var array<string, mixed> $rolesConfig */
        $rolesConfig = is_array($usimConfig['roles'] ?? null) ? $usimConfig['roles'] : [];

        DB::beginTransaction();
        try {
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            foreach ($rolesConfig as $roleName => $roleMeta) {
                if (trim($roleName) === '') {
                    continue;
                }

                if (!is_array($roleMeta)) {
                    continue;
                }

                $guardName = $this->normalizeStringValue($roleMeta['guard_name'] ?? null, $defaultGuard);
                $homeScreen = $this->normalizeStringValue($roleMeta['home_screen'] ?? 'welcome', 'welcome');

                $priorityValue = $roleMeta['priority'] ?? 100;
                $priority = is_int($priorityValue) ? $priorityValue : (is_numeric($priorityValue) ? (int) $priorityValue : 100);

                // 1. Resolver permisos requeridos por este rol
                /** @var list<string> $rolePermissions */
                $rolePermissions = $roleName === 'root'
                    ? $definedPermissions // Root hereda todos los permisos declarados
                    : [];

                if (\is_array($roleMeta['permissions'] ?? null)) {
                    foreach ($roleMeta['permissions'] as $permName) {
                        if (\is_string($permName)) {
                            $rolePermissions[] = trim($permName);
                        }
                    }
                }

                /** @var list<string> $validPermissions */
                $validPermissions = [];

                // 2. Crear permisos inexistentes para el Guard actual
                foreach ($rolePermissions as $permName) {
                    $permName = trim($permName);
                    if ($permName === '' || \in_array($permName, $validPermissions, true)) {
                        continue;
                    }

                    $validPermissions[] = $permName;

                    $perm = Permission::firstOrCreate([
                        'name' => $permName,
                        'guard_name' => $guardName,
                    ]);

                    if ($perm->wasRecentlyCreated) {
                        $stats['permissions_created']++;
                    }
                }

                // 3. Crear o Actualizar el Rol (incluyendo configuración extendida)
                /** @var UsimRole|null $role */
                $role = UsimRole::query()
                    ->where('name', $roleName)
                    ->where('guard_name', $guardName)
                    ->first();

                if (!$role) {
                    $role = UsimRole::createWithHome($roleName, $homeScreen, $priority, $guardName);
                    $stats['roles_created']++;
                } else {
                    // Actualizamos la configuración a través de la relación directamente,
                    // evitando ensuciar el modelo base y el $role->save()
                    $role->usimSetting()->updateOrCreate(
                        [],
                        [
                            'home_screen' => $homeScreen,
                            'priority' => $priority,
                        ]
                    );
                    $stats['roles_updated']++;
                }

                // 4. Sincronizar (atar) los permisos al rol
                if (!empty($validPermissions)) {
                    $role->syncPermissions($validPermissions);
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $stats;
    }

    private function normalizeStringValue(mixed $value, string $fallback): string
    {
        $normalized = is_scalar($value) || $value instanceof \Stringable ? (string) $value : $fallback;
        $trimmed = trim($normalized);

        return $trimmed !== '' ? $trimmed : $fallback;
    }
}
