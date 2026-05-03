<?php

namespace Idei\Usim\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * @template TModel of Model
 */
interface ModelReadService
{
    /**
     * @param int|string $id
     * @return TModel|null
     */
    public function findById(int|string $id): ?Model;

    /**
     * @return array<int, TModel>
     */
    public function all(): array;
}
