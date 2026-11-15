<?php

namespace Idei\Usim\Contracts\ItemList;

/**
 * Contract for services that support deleting single items from an ItemList component.
 */
interface Deletable
{
    /**
     * Delete a single item by its ID.
     */
    public function delete(int|string $id): bool;
}
