<?php

namespace Idei\Usim\Contracts\ItemList;

/**
 * Contract for services that support creating new items from an ItemList component.
 */
interface Creatable
{
    /**
     * Create a new item from the given label and optional payload, returning the normalized item.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function create(string $label, array $payload = []): array;
}
