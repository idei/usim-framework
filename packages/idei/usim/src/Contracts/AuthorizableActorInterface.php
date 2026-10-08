<?php

namespace Idei\Usim\Contracts;

/**
 * Interface for actors that support authorization, roles, and permissions.
 */
interface AuthorizableActorInterface
{
    public function isRoot(): bool;

    public function hasPermissionTo(string $permission): bool;

    /**
     * @param  string|array<int, string>  $roles
     */
    public function hasRole(string|array $roles): bool;

    /**
     * @param  string|array<int, string>  $roles
     */
    public function hasAnyRole(string|array $roles): bool;
}
