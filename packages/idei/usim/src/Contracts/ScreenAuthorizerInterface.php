<?php

namespace Idei\Usim\Contracts;

/**
 * Contract for authorizing screen actions, roles, and permissions within USIM.
 */
interface ScreenAuthorizerInterface
{
    /**
     * Check if the given actor/user has the specified permission.
     */
    public function can(?object $actor, string $permission, mixed $unit = null): bool;

    /**
     * Check if the given actor/user has any of the specified roles.
     *
     * @param  string|array<int, string>  $roles
     */
    public function hasRole(?object $actor, string|array $roles, mixed $unit = null): bool;
}
