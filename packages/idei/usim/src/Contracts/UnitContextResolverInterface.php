<?php

namespace Idei\Usim\Contracts;

use Idei\Usim\Models\UsimUnit;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Resolves and applies active organizational units / teams for requests.
 */
interface UnitContextResolverInterface
{
    /**
     * Resolve the unit the given user should operate in, without applying it.
     */
    public function resolveUserUnit(?Authenticatable $user, ?string $slug): ?UsimUnit;

    /**
     * Resolve the unit context for the given user and apply it as the current permissions team.
     */
    public function applyUserUnit(?Authenticatable $user, ?string $slug): ?UsimUnit;
}
