<?php

declare(strict_types=1);

namespace Idei\Usim\Console\Commands\Support\Crud;

/**
 * Inspected capabilities and DTO field descriptors for a Master-Detail Service.
 */
final readonly class CrudServiceMetadata
{
    /**
     * @param  class-string  $serviceFqcn
     * @param  class-string  $dtoFqcn
     * @param  list<CrudFieldDescriptor>  $fields
     */
    public function __construct(
        public string $serviceFqcn,
        public string $dtoFqcn,
        public bool $isListable,
        public bool $isDetailable,
        public bool $isCreatable,
        public bool $isUpdatable,
        public bool $isDeletable,
        public array $fields,
    ) {}

    public function isMutable(): bool
    {
        return $this->isCreatable || $this->isUpdatable;
    }

    public function isReadOnly(): bool
    {
        return ! $this->isMutable();
    }

    /**
     * @return list<CrudFieldDescriptor>
     */
    public function belongsToFields(): array
    {
        return array_values(array_filter(
            $this->fields,
            static fn (CrudFieldDescriptor $field): bool => $field->widget === 'belongs_to' && $field->relatedServiceFqcn !== null
        ));
    }

    /**
     * @return list<CrudFieldDescriptor>
     */
    public function belongsToManyFields(): array
    {
        return array_values(array_filter(
            $this->fields,
            static fn (CrudFieldDescriptor $field): bool => $field->widget === 'belongs_to_many' && $field->relatedServiceFqcn !== null
        ));
    }
}
