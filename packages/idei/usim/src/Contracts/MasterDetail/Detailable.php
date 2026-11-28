<?php

declare(strict_types=1);

namespace Idei\Usim\Contracts\MasterDetail;

/**
 * Contract for services that resolve a full detail DTO by ID for a Master-Detail screen.
 *
 * @template TDetail of object
 */
interface Detailable
{
    /**
     * Find a single item's detail DTO by its identifier, or null when not found.
     *
     * @return TDetail|null
     */
    public function findDetail(int|string $id): ?object;
}
