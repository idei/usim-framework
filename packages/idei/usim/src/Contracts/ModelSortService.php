<?php

namespace Idei\Usim\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * @template TModel of Model
 */
interface ModelSortService
{
    /**
     * @param string $field
     * @param 'asc'|'desc' $direction
     * @return array<int, TModel>
     */
    public function sortBy(string $field, string $direction = 'asc'): array;
}
