<?php

namespace Idei\Usim\Contracts;

interface ModelStructureService
{
    /**
     * Return field metadata for the model, including calculated fields.
     *
     * @return array<int, array{
     *   name: string,
     *   type: string,
     *   nullable: bool,
     *   calculated: bool,
     *   searchable: bool,
     *   filterable: bool,
     *   sortable: bool
     * }>
     */
    public function getModelStructure(): array;
}
