<?php

namespace Idei\Usim\Services;

use App\Models\User;
use Idei\Usim\Models\UsimUnit;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use Spatie\Permission\PermissionRegistrar;

class UsimUserService
{
    /**
     * Crea o actualiza un usuario basándose en la key de config/usim.php
     * y le asigna las membresías y roles correspondientes (unit_roles).
     */
    public function provisionFromConfig(string $configKey): User
    {
        $userData = config("usim.users.{$configKey}");

        if (!$userData) {
            throw new InvalidArgumentException("La key '{$configKey}' no existe en la configuración usim.users.");
        }

        $firstName = $userData['first_name'] ?? '';
        $lastName = $userData['last_name'] ?? '';
        $name = trim("{$firstName} {$lastName}");

        $user = User::updateOrCreate(
            ['email' => $userData['email']],
            [
                'name' => $name,
                'password' => Hash::make($userData['password']),
                'email_verified_at' => now(), // Útil para que los tests no reboten en middleware de verificación
            ]
        );

        $unitIdsForMembership = [];

        if (isset($userData['unit_roles'])) {
            foreach ($userData['unit_roles'] as $unitSlug => $roles) {
                // En un entorno de test, si la unidad no existe, la creamos al vuelo.
                $unit = UsimUnit::firstOrCreate(['slug' => $unitSlug]);
                $unitIdsForMembership[] = $unit->id;

                app(PermissionRegistrar::class)->setPermissionsTeamId($unit->id);

                // Aseguramos que los roles existan antes de asignarlos
                foreach ($roles as $roleName) {
                    \Spatie\Permission\Models\Role::findOrCreate($roleName);
                }

                $user->syncRoles($roles);
            }

            // Sincronizar membresía física
            $user->usimUnits()->sync($unitIdsForMembership);
        }

        // Limpiar caché de Spatie tras mutar permisos (esencial para los tests)
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }
}
