<?php

namespace Idei\Usim\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * @template TModel of Model
 */
interface ModelFilterService
{
    /**
     * @param array<string, mixed> $filters
     * @return array<int, TModel>
     */
    public function filter(array $filters): array;
}
