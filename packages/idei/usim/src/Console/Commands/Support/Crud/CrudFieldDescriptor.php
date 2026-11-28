<?php

declare(strict_types=1);

namespace Idei\Usim\Console\Commands\Support\Crud;

use Idei\Usim\Components\UIElement;

/**
 * Normalized metadata for a single DTO property in a scaffolded Master-Detail CRUD screen.
 */
final readonly class CrudFieldDescriptor
{
    /**
     * @param  'string'|'int'|'float'|'bool'|'array'  $phpType
     * @param  'text'|'email'|'number'|'password'|'textarea'|'checkbox'|'belongs_to'|'belongs_to_many'  $widget
     * @param  class-string  $componentClass
     */
    public function __construct(
        public string $property,
        public string $snakeName,
        public string $phpType,
        public bool $nullable,
        public bool $hasDefault,
        public mixed $defaultValue,
        public string $widget,
        public string $componentName,
        public string $componentClass,
        public string $validationRules,
        public int $span = 2,
        public bool $readonly = false,
        public ?string $relatedServiceFqcn = null,
        public bool $searchable = true,
        public int $maxHeight = 240,
    ) {}

    public function humanLabel(): string
    {
        $clean = (string) preg_replace('/_(id|ids)$/', '', $this->snakeName);

        return ucwords(str_replace('_', ' ', $clean));
    }

    public function isRelation(): bool
    {
        return $this->widget === 'belongs_to' || $this->widget === 'belongs_to_many';
    }
}
