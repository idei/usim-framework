<?php

namespace Idei\Usim\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * @template TModel of Model
 */
interface ModelSearchService
{
    /**
     * @param string $term
     * @param array<string, mixed> $filters
     * @return array<int, TModel>
     */
    public function search(string $term, array $filters = []): array;
}
