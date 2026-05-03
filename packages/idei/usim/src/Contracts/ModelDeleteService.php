<?php

namespace Idei\Usim\Contracts;

interface ModelDeleteService
{
    /**
     * @param int|string $id
     */
    public function deleteById(int|string $id): bool;
}
