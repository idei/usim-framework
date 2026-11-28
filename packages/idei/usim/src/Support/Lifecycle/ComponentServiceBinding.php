<?php

declare(strict_types=1);

namespace Idei\Usim\Support\Lifecycle;

use Idei\Usim\Components\CardGrid;
use Idei\Usim\Components\ItemList;

/**
 * Immutable value object representing a resolved component service binding.
 */
final readonly class ComponentServiceBinding
{
    public function __construct(
        public ItemList|CardGrid $component,
        public string $operation,
    ) {}
}
