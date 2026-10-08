<?php

namespace Idei\Usim\Support;

use Idei\Usim\Contracts\AuthorizableActorInterface;
use Idei\Usim\Contracts\ScreenAuthorizerInterface;
use Idei\Usim\Models\UsimUnit;

class SpatieScreenAuthorizer implements ScreenAuthorizerInterface
{
    public function can(?object $actor, string $permission, mixed $unit = null): bool
    {
        if ($actor === null) {
            return false;
        }

        if ($this->isActorRoot($actor)) {
            return true;
        }

        if ($unit === null) {
            $unit = UIStateManager::getActiveUnit();
        }

        $targetUnitId = $this->resolveUnitId($unit);

        if ($targetUnitId !== null && function_exists('getPermissionsTeamId') && function_exists('setPermissionsTeamId')) {
            $previousTeamId = getPermissionsTeamId();
            try {
                setPermissionsTeamId($targetUnitId);

                return $this->checkActorPermission($actor, $permission);
            } finally {
                setPermissionsTeamId($previousTeamId);
            }
        }

        return $this->checkActorPermission($actor, $permission);
    }

    public function hasRole(?object $actor, string|array $roles, mixed $unit = null): bool
    {
        if ($actor === null) {
            return false;
        }

        if ($this->isActorRoot($actor)) {
            return true;
        }

        if ($unit === null) {
            $unit = UIStateManager::getActiveUnit();
        }

        $targetUnitId = $this->resolveUnitId($unit);

        if ($targetUnitId !== null && function_exists('getPermissionsTeamId') && function_exists('setPermissionsTeamId')) {
            $previousTeamId = getPermissionsTeamId();
            try {
                setPermissionsTeamId($targetUnitId);

                return $this->checkActorRole($actor, $roles);
            } finally {
                setPermissionsTeamId($previousTeamId);
            }
        }

        return $this->checkActorRole($actor, $roles);
    }

    protected function isActorRoot(object $actor): bool
    {
        if ($actor instanceof AuthorizableActorInterface) {
            return $actor->isRoot();
        }

        if (method_exists($actor, 'isRoot')) {
            /** @var callable $callable */
            $callable = [$actor, 'isRoot'];

            return (bool) $callable();
        }

        return false;
    }

    protected function checkActorPermission(object $actor, string $permission): bool
    {
        if ($actor instanceof AuthorizableActorInterface) {
            return $actor->hasPermissionTo($permission);
        }

        if (method_exists($actor, 'hasPermissionTo')) {
            /** @var callable $callable */
            $callable = [$actor, 'hasPermissionTo'];

            return (bool) $callable($permission);
        }

        return false;
    }

    /**
     * @param  string|array<int, string>  $roles
     */
    protected function checkActorRole(object $actor, string|array $roles): bool
    {
        if ($actor instanceof AuthorizableActorInterface) {
            return $actor->hasAnyRole($roles);
        }

        if (method_exists($actor, 'hasAnyRole')) {
            /** @var callable $callable */
            $callable = [$actor, 'hasAnyRole'];

            return (bool) $callable($roles);
        }

        if (method_exists($actor, 'hasRole')) {
            /** @var callable $callable */
            $callable = [$actor, 'hasRole'];

            return (bool) $callable($roles);
        }

        return false;
    }

    protected function resolveUnitId(mixed $unit): ?int
    {
        if ($unit instanceof UsimUnit) {
            return $unit->id;
        }

        if (is_int($unit)) {
            return $unit;
        }

        if (is_string($unit) && $unit !== '') {
            $found = UsimUnit::where('slug', $unit)->first();

            return $found?->id;
        }

        return null;
    }
}
