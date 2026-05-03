<?php

namespace Idei\Usim\Contracts;

/**
 * Contract for services that implement CRUD operations.
 *
 * @template TRecord
 */
interface CrudService
{
    /**
     * Create a new model record.
     *
     * @param array<string, mixed> $attributes
    * @return TRecord
     */
    public function create(array $attributes): mixed;

    /**
     * Find a model by its primary key.
     *
     * @param int|string $id
    * @return TRecord|null
     */
    public function find(int|string $id): mixed;

    /**
     * Update an existing model.
     *
    * @param int|string $id
     * @param array<string, mixed> $attributes
    * @return TRecord|null
     */
    public function update(int|string $id, array $attributes): mixed;

    /**
     * Delete an existing model.
     *
    * @param int|string $id
     */
    public function delete(int|string $id): bool;

    /**
     * Return all model records.
     *
    * @return array<int, TRecord>
     */
    public function all(): array;

    /**
     * Return records that match the provided filters.
     *
     * @param array<string, mixed> $filters
    * @return array<int, TRecord>
     */
    public function filter(array $filters): array;

    /**
     * Search records using a free-text term and optional filters.
     *
     * @param string $term
     * @param array<string, mixed> $filters
     * @return array<int, TRecord>
     */
    public function search(string $term, array $filters = []): array;
}
