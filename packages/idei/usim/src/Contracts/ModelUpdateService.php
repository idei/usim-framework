<?php

namespace Idei\Usim\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * @template TModel of Model
 */
interface ModelUpdateService
{
    /**
     * @param int|string $id
     * @param array<string, mixed> $attributes
     * @return TModel|null
     */
    public function updateById(int|string $id, array $attributes): ?Model;
}
