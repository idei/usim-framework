<?php

namespace Idei\Usim\Contracts\ItemList;

/**
 * Contract for services that support updating existing items from an ItemList component.
 */
interface Updatable
{
    /**
     * Update an existing item by ID and return the updated item payload (or null if not found).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    public function update(int|string $id, string $label, array $payload = []): ?array;
}
