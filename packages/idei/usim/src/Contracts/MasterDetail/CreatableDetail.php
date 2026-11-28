<?php

declare(strict_types=1);

namespace Idei\Usim\Contracts\MasterDetail;

/**
 * Contract for services that persist a newly created detail DTO in a Master-Detail screen.
 *
 * @template TDetail of object
 */
interface CreatableDetail
{
    /**
     * Persist a new entity from the given DTO and return the stored DTO (with assigned ID).
     *
     * @param  TDetail  $dto
     * @return TDetail
     */
    public function createDetail(object $dto): object;
}
