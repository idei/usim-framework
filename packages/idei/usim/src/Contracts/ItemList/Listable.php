<?php

namespace Idei\Usim\Contracts\ItemList;

use Idei\Usim\DTOs\ListItemDTO;
use Illuminate\Support\Collection;

/**
 * Contract for services that can supply and optionally filter items for an ItemList component.
 */
interface Listable
{
    /**
     * Retrieve list items, optionally filtered by a search term and context parameters.
     *
     * @param  array<string, mixed>  $context
     * @return Collection<int, ListItemDTO>
     */
    public function list(string $search = '', array $context = []): Collection;
}
