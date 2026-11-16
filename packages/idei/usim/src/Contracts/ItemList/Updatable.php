<?php

namespace Idei\Usim\Contracts\ItemList;

use Idei\Usim\DTOs\ListItemDTO;

/**
 * Contract for services that support updating existing items from an ItemList component.
 */
interface Updatable
{
    /**
     * Update an existing item by ID and return the updated item DTO (or null if not found).
     *
     * @param  array<string, mixed>  $payload
     */
    public function update(int|string $id, string $label, array $payload = []): ?ListItemDTO;
}
