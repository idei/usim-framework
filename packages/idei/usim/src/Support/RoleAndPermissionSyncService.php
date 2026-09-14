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

        $usimConfig = config('usim');
        $permissionsConfig = $usimConfig['permissions'] ?? [];
        $definedPermissions = array_keys($permissionsConfig);
        $rolesConfig = $usimConfig['roles'] ?? [];

        DB::beginTransaction();
        try {
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            foreach ($rolesConfig as $roleName => $roleMeta) {
                if (!is_string($roleName) || trim($roleName) === '') {
                    continue;
                }

                $guardName = $roleMeta['guard_name'] ?? $defaultGuard;
                $homeScreen = $roleMeta['home_screen'] ?? 'welcome';
                $priority = (int) ($roleMeta['priority'] ?? 100);

                // 1. Resolver permisos requeridos por este rol
                $rolePermissions = $roleName === 'root'
                    ? $definedPermissions // Root hereda todos los permisos declarados
                    : ($roleMeta['permissions'] ?? []);

                $validPermissions = [];

                // 2. Crear permisos inexistentes para el Guard actual
                foreach ($rolePermissions as $permName) {
                    if (!is_string($permName) || trim($permName) === '') {
                        continue;
                    }

                    $permName = trim($permName);
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
}
