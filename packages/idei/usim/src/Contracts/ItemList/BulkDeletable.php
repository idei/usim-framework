<?php

namespace Idei\Usim\Contracts\ItemList;

/**
 * Optional contract for services that can delete multiple ItemList items in a single operation.
 */
interface BulkDeletable
{
    /**
     * Delete multiple items by ID and return the number of deleted records.
     *
     * @param  list<int|string>  $ids
     */
    public function deleteMany(array $ids): int;
}
