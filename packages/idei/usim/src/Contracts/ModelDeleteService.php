<?php

namespace Idei\Usim\Contracts;

interface ModelDeleteService
{
    public function deleteById(int|string $id): bool;
}
