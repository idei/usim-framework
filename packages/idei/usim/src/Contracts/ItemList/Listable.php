<?php

namespace Idei\Usim\Contracts\ItemList;

/**
 * Contract for services that can supply and optionally filter items for an ItemList component.
 */
interface Listable
{
    /**
     * Retrieve list items, optionally filtered by a search term and context parameters.
     *
     * @param  array<string, mixed>  $context
     * @return list<array<string, mixed>>
     */
    public function list(string $search = '', array $context = []): array;
}
