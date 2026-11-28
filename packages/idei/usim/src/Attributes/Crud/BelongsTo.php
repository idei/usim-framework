<?php

declare(strict_types=1);

namespace Idei\Usim\Attributes\Crud;

use Attribute;
use Idei\Usim\Contracts\ItemList\Listable;

/**
 * Marks a DTO property as a 1:N (belongs-to) reference resolved via a Listable service.
 *
 * Used by `usim:crud` to scaffold a `Select` component hydrated from the target service.
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final readonly class BelongsTo
{
    /**
     * @param  class-string<Listable>  $service  Listable service providing selectable options
     * @param  bool  $searchable  Whether the generated Select should enable client filtering
     */
    public function __construct(
        public string $service,
        public bool $searchable = true,
    ) {}
}
