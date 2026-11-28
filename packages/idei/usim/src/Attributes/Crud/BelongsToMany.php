<?php

declare(strict_types=1);

namespace Idei\Usim\Attributes\Crud;

use Attribute;
use Idei\Usim\Contracts\ItemList\Listable;

/**
 * Marks an array DTO property as an M:N (many-to-many) collection of IDs resolved via a Listable service.
 *
 * Used by `usim:crud` to scaffold a multi-select `ItemList` component wired to the target service.
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final readonly class BelongsToMany
{
    /**
     * @param  class-string<Listable>  $service  Listable service providing selectable items
     * @param  bool  $searchable  Whether the generated ItemList enables search filtering
     * @param  int  $maxHeight  Max height in pixels for the multi-select list box
     */
    public function __construct(
        public string $service,
        public bool $searchable = true,
        public int $maxHeight = 240,
    ) {}
}
