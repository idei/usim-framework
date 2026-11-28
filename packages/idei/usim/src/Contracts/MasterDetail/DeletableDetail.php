<?php

declare(strict_types=1);

namespace Idei\Usim\Contracts\MasterDetail;

/**
 * Contract for services that delete an entity by ID from a Master-Detail screen.
 */
interface DeletableDetail
{
    /**
     * Delete an entity by its identifier.
     */
    public function deleteDetail(int|string $id): bool;
}
