<?php

declare(strict_types=1);

namespace Idei\Usim\Contracts\MasterDetail;

/**
 * Contract for services that update an existing entity from a detail DTO in a Master-Detail screen.
 *
 * @template TDetail of object
 */
interface UpdatableDetail
{
    /**
     * Update an existing entity by ID and return the updated DTO (or null if not found).
     *
     * @param  TDetail  $dto
     * @return TDetail|null
     */
    public function updateDetail(int|string $id, object $dto): ?object;
}
