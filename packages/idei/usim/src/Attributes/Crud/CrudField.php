<?php

declare(strict_types=1);

namespace Idei\Usim\Attributes\Crud;

use Attribute;

/**
 * Optional UI & validation metadata for a DTO property inspected by `usim:crud`.
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final readonly class CrudField
{
    /**
     * @param  'text'|'email'|'number'|'password'|'textarea'|'checkbox'|'hidden'|null  $type  Explicit field widget override
     * @param  string|null  $rules  Explicit Laravel validation rule string override
     * @param  int|null  $span  Grid column span override in the detail form (1 or 2)
     * @param  bool  $readonly  Whether the generated component is read-only
     * @param  bool  $ignore  Exclude this property from scaffolded form inputs
     */
    public function __construct(
        public ?string $type = null,
        public ?string $rules = null,
        public ?int $span = null,
        public bool $readonly = false,
        public bool $ignore = false,
    ) {}
}
