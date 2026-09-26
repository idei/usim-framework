<?php

namespace Idei\Usim\Contracts;

use Idei\Usim\Screen;

interface ModalInterface
{
    public static function open(Screen $caller, mixed ...$params): void;
}
