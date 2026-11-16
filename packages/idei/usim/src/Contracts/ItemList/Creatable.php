<?php

namespace Idei\Usim\Contracts\ItemList;

use Idei\Usim\DTOs\ListItemDTO;

/**
 * Contract for services that support creating new items from an ItemList component.
 */
interface Creatable
{
    /**
     * Create a new item from the given label and optional payload, returning the item DTO.
     *
     * @param  array<string, mixed>  $payload
     */
    public function create(string $label, array $payload = []): ListItemDTO;
}
