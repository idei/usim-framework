<?php

namespace Idei\Usim\Navigation\Contracts;

use Idei\Usim\Navigation\MenuBuilder;

interface MenuProviderInterface
{
    /**
     * Build the menu structure using the provided builder.
     */
    public function build(MenuBuilder $menu): void;
}
