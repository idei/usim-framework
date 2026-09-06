<?php

namespace Database\Seeders;

use App\Models\User;
use Idei\Usim\Models\UsimUnit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class UserSeeder extends Seeder
{
    protected const USERS_COUNT = 50;
    protected const REGISTERED_PERCENTAGE = 25;
    protected const MIN_ROLES_BY_USER = 1;
    protected const MAX_ROLES_BY_USER = 2;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        /** @var array<string, array<string, mixed>> $rolesData */
        $rolesData = config('usim.roles', []);

        // We sort roles by priority
        uasort($rolesData, static function (array $a, array $b): int {
            $aPriority = $a['priority'] ?? 0;
            $bPriority = $b['priority'] ?? 0;
            $aPriority = \is_int($aPriority) ? $aPriority : 0;
            $bPriority = \is_int($bPriority) ? $bPriority : 0;

            return $aPriority <=> $bPriority;
        });

        /** @var list<string> $roles */
        $roles = array_keys($rolesData);

        $defaultRegisteringRole = config('usim.default_registering_role', 'registered');
        $registeringRole = is_string($defaultRegisteringRole) ? $defaultRegisteringRole : 'registered';

        // Roles permitidos para usuarios operativos (no registrados):
        // Excluimos 'root', 'guest' y el rol de registro ('registered').
        $systemRoles = ['root', 'guest', $registeringRole];
        $operationalRoles = array_values(array_filter(
            $roles,
            static fn (string $role): bool => !in_array($role, $systemRoles, true)
        ));

        if (empty($operationalRoles)) {
            $operationalRoles = array_values(array_filter(
                $roles,
                static fn (string $role): bool => $role !== $registeringRole && $role !== 'registered'
            ));
        }

        if (empty($operationalRoles)) {
            $operationalRoles = ['member'];
        }

        // Aseguramos que los roles existan en Spatie
        $defaultGuard = config('auth.defaults.guard', 'web');
        $guardName = is_string($defaultGuard) ? $defaultGuard : 'web';
        $allRolesToEnsure = array_unique(array_merge([$registeringRole], $operationalRoles));
        foreach ($allRolesToEnsure as $roleName) {
            Role::findOrCreate($roleName, $guardName);
        }

        $teamsEnabled = (bool) config('permission.teams', false);
        $lobbyUnit = null;
        /** @var Collection<int, UsimUnit> $operationalUnits */
        $operationalUnits = collect();

        if ($teamsEnabled) {
            $units = UsimUnit::all();
            if ($units->isEmpty()) {
                $structure = config('usim.units.structure', []);
                if (is_array($structure) && !empty($structure)) {
                    foreach ($structure as $slug => $data) {
                        UsimUnit::firstOrCreate(
                            ['slug' => $slug],
                            ['type' => is_array($data) ? ($data['type'] ?? null) : null]
                        );
                    }
                    $units = UsimUnit::all();
                }
            }

            $lobbyUnit = $units->firstWhere('slug', 'lobby')
                ?? UsimUnit::firstOrCreate(['slug' => 'lobby'], ['type' => 'system']);

            $operationalUnits = $units->filter(
                static fn (UsimUnit $unit): bool => $unit->type !== 'system' && !in_array($unit->slug, ['lobby', 'main'], true)
            );

            if ($operationalUnits->isEmpty()) {
                $fallbackUnit = UsimUnit::firstOrCreate(
                    ['slug' => 'operations'],
                    ['type' => 'department']
                );
                $operationalUnits = collect([$fallbackUnit]);
            }
        }

        User::factory()
            ->count(self::USERS_COUNT)
            ->create()
            ->each(function (User $user, int $index) use (
                $teamsEnabled,
                $lobbyUnit,
                $operationalUnits,
                $registeringRole,
                $operationalRoles
            ): void {
                // 1. Decidir si el usuario está registrado:
                // Se garantiza que existan tanto usuarios registrados como no registrados
                $isRegistered = match ($index) {
                    0 => true,
                    1 => false,
                    default => fake()->boolean(self::REGISTERED_PERCENTAGE),
                };

                if ($isRegistered) {
                    // 1.a. Si está registrado: sólo puede estar en la unidad "lobby" y con role "registered"
                    // $lobbyUnit siempre es un UsimUnit cuando $teamsEnabled es true (ver bloque de inicialización)
                    if ($teamsEnabled) {
                        $user->usimUnits()->sync([$lobbyUnit->id]);
                        setPermissionsTeamId($lobbyUnit->id);
                    }

                    $user->syncRoles([$registeringRole]);

                    if ($teamsEnabled) {
                        setPermissionsTeamId(null);
                    }
                } else {
                    // 1.b. No está registrado: sólo en unidades que no son de type "system" y roles != "registered"
                    if ($teamsEnabled && $operationalUnits->isNotEmpty()) {
                        $unitsCount = (fake()->boolean(20) && $operationalUnits->count() > 1) ? 2 : 1;
                        // random() con $number no nulo siempre devuelve una Collection, nunca un único modelo
                        $assignedUnits = $operationalUnits->random($unitsCount);

                        $user->usimUnits()->sync($assignedUnits->pluck('id')->all());

                        foreach ($assignedUnits as $assignedUnit) {
                            setPermissionsTeamId($assignedUnit->id);

                            $rolesCount = rand(self::MIN_ROLES_BY_USER, min(self::MAX_ROLES_BY_USER, count($operationalRoles)));
                            $randRoles = Arr::random($operationalRoles, $rolesCount);
                            $rolesForSync = is_array($randRoles) ? array_values($randRoles) : [$randRoles];

                            $user->syncRoles($rolesForSync);
                            $user->unsetRelation('roles');
                        }

                        setPermissionsTeamId(null);
                    } else {
                        $rolesCount = rand(self::MIN_ROLES_BY_USER, min(self::MAX_ROLES_BY_USER, count($operationalRoles)));
                        $randRoles = Arr::random($operationalRoles, $rolesCount);
                        $rolesForSync = is_array($randRoles) ? array_values($randRoles) : [$randRoles];

                        $user->syncRoles($rolesForSync);
                    }
                }
            });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        if ($teamsEnabled) {
            setPermissionsTeamId(null);
        }
    }
}
