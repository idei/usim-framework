<?php

namespace Idei\Usim\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class UsimUnitsService
{
    /**
     * Given a user, returns the units they have access to, and for each unit, the roles that the user has.
     * As a result:
     * [
     *   'unit_slug' => ['role1', 'role2'],
     *   'unit_slug2' => ['role3'],
     * ]
     *
     * @param User $user
     * @return array<string, list<string>>
     */
    public function getUserUnitsWithRoles(User $user): array
    {
        $unitsWithRoles = [];

        $units = method_exists($user, 'usimUnits') ? $user->usimUnits : collect();
        foreach ($units as $unit) {
            $unitsWithRoles[$unit->slug] = [];
        }

        if (!config('permission.teams', false)) {
            $roles = method_exists($user, 'getRoleNames') ? $user->getRoleNames()->values()->toArray() : [];
            foreach (array_keys($unitsWithRoles) as $slug) {
                $unitsWithRoles[$slug] = $roles;
            }

            return $unitsWithRoles;
        }

        $teamForeignKey = config('permission.column_names.team_foreign_key') ?? 'usim_unit_id';
        $modelHasRolesTable = config('permission.table_names.model_has_roles') ?? 'model_has_roles';
        $rolesTable = config('permission.table_names.roles') ?? 'roles';
        $modelMorphKey = config('permission.column_names.model_morph_key') ?? 'model_id';

        $assignedRoles = DB::table($modelHasRolesTable)
            ->join($rolesTable, "{$rolesTable}.id", '=', "{$modelHasRolesTable}.role_id")
            ->join('usim_units', 'usim_units.id', '=', "{$modelHasRolesTable}.{$teamForeignKey}")
            ->where("{$modelHasRolesTable}.model_type", $user->getMorphClass())
            ->where("{$modelHasRolesTable}.{$modelMorphKey}", $user->getKey())
            ->select("usim_units.slug", "{$rolesTable}.name as role_name")
            ->get();

        foreach ($assignedRoles as $row) {
            if (!isset($unitsWithRoles[$row->slug])) {
                $unitsWithRoles[$row->slug] = [];
            }

            if (!in_array($row->role_name, $unitsWithRoles[$row->slug], true)) {
                $unitsWithRoles[$row->slug][] = $row->role_name;
            }
        }

        return $unitsWithRoles;
    }
}
