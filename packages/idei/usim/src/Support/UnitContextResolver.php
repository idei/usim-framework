<?php

namespace Idei\Usim\Support;

use Idei\Usim\Models\UsimUnit;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Resolves and applies the active organizational unit (permissions team) for a request.
 *
 * The requested slug is only honored when it actually belongs to the user. If it is
 * missing, invalid, or not one of the user's units, the user's first assigned unit
 * (deterministically ordered) is used as a fallback.
 */
class UnitContextResolver
{
    /**
     * Resolve the unit context for the given user and apply it as the current
     * permissions team (via setPermissionsTeamId), returning the resolved unit.
     */
    public static function resolveAndApply(?Authenticatable $user, ?string $slug): ?UsimUnit
    {
        $unit = self::resolve($user, $slug);

        if ($unit) {
            setPermissionsTeamId($unit->id);
        }

        return $unit;
    }

    /**
     * Resolve the unit the given user should operate in, without applying it.
     *
     * Expects the user model to expose a `usimUnits()` BelongsToMany relation.
     */
    public static function resolve(?Authenticatable $user, ?string $slug): ?UsimUnit
    {
        if (!$user) {
            return null;
        }

        $isRoot = method_exists($user, 'isRoot') && $user->isRoot();

        // Scoped to the user's own units (unless root) so an
        // untrusted client-supplied slug can't grant access to another unit.
        if (\is_string($slug) && $slug !== '') {
            if ($isRoot) {
                $unit = UsimUnit::where('slug', $slug)->first();
                if ($unit) {
                    return $unit;
                }
            } elseif (method_exists($user, 'usimUnits')) {
                $unit = $user->usimUnits()->where('slug', $slug)->first();
                if ($unit) {
                    return $unit;
                }
            }
        }

        // Requested slug is missing or doesn't belong to the user: fall back to the
        // first unit the user is registered in, deterministically ordered by id.
        if (method_exists($user, 'usimUnits')) {
            $firstUnit = $user->usimUnits()->orderBy('usim_units.id')->first();
            if ($firstUnit) {
                return $firstUnit;
            }
        }

        if ($isRoot) {
            return UsimUnit::where('slug', 'main')->first();
        }

        return null;
    }
}
