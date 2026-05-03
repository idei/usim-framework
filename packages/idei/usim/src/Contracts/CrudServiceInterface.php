<?php

namespace Idei\Usim\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Contract for services that implement CRUD operations over an Eloquent model.
 *
 * @template TModel of Model
 */
interface CrudServiceInterface
{
    /**
     * Create a new model record.
     *
     * @param array<string, mixed> $attributes
     * @return TModel
     */
    public function create(array $attributes): Model;

    /**
     * Find a model by its primary key.
     *
     * @param int|string $id
     * @return TModel|null
     */
    public function find(int|string $id): ?Model;

    /**
     * Update an existing model.
     *
     * @param TModel $model
     * @param array<string, mixed> $attributes
     * @return TModel
     */
    public function update(Model $model, array $attributes): Model;

    /**
     * Delete an existing model.
     *
     * @param TModel $model
     */
    public function delete(Model $model): bool;

    /**
     * Return all model records.
     *
     * @return Collection<int, TModel>
     */
    public function all(): Collection;

    /**
     * Return records that match the provided filters.
     *
     * @param array<string, mixed> $filters
     * @return Collection<int, TModel>
     */
    public function filter(array $filters): Collection;

    /**
     * Search records using a free-text term and optional filters.
     *
     * @param string $term
     * @param array<string, mixed> $filters
     * @return Collection<int, TModel>
     */
    public function search(string $term, array $filters = []): Collection;
}
