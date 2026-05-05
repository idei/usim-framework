<?php

namespace App\Services\Support;

use Idei\Usim\Contracts\ModelQueryableService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;
use Throwable;

/**
 * Generic listing/query service for Eloquent models.
 *
 * Child classes only need to define `$modelClass` and allowed fields.
 * Field definitions support base columns and relation paths (`relation.column`).
 *
 * @template TModel of Model
 * @implements ModelQueryableService<TModel>
 */
abstract class EloquentListingService implements ModelQueryableService
{
    /** @var class-string<TModel> */
    protected string $modelClass;

    /**
     * Additional relations to eager-load.
     *
     * @var array<int, string>
     */
    protected array $with = [];

    /**
     * @var array<string, array{
     *   type: class-string<Relation>,
     *   related_table: string,
     *   relation: Relation
     * }>
     */
    private array $relationCache = [];

    /**
     * @var array<string, string>
     */
    private array $joinedRelationAliases = [];

    public function findById(int|string $id): ?Model
    {
        return $this->newBaseQuery()->find($id);
    }

    public function all(): array
    {
        return $this->newBaseQuery()->get()->all();
    }

    public function filter(array $filters): array
    {
        return $this->buildQuery(filters: $filters)->get()->all();
    }

    public function sortBy(string $field, string $direction = 'asc'): array
    {
        return $this->buildQuery(sortField: $field, sortDirection: $direction)->get()->all();
    }

    public function search(string $term, array $filters = []): array
    {
        return $this->buildQuery(search: $term, filters: $filters)->get()->all();
    }

    public function getModelStructure(): array
    {
        $searchable = array_keys($this->normalizeFieldMap($this->searchableFields(), defaultOperator: 'like'));
        $filterable = array_keys($this->normalizeFieldMap($this->filterableFields(), defaultOperator: 'eq'));
        $sortable = array_keys($this->normalizeFieldMap($this->sortableFields(), defaultOperator: 'eq'));

        $allFields = array_values(array_unique(array_merge($searchable, $filterable, $sortable)));
        $result = [];

        foreach ($allFields as $field) {
            $definition = $this->resolveDefinition(
                $field,
                array_merge(
                    $this->normalizeFieldMap($this->searchableFields(), defaultOperator: 'like'),
                    $this->normalizeFieldMap($this->filterableFields(), defaultOperator: 'eq'),
                    $this->normalizeFieldMap($this->sortableFields(), defaultOperator: 'eq'),
                )
            );

            if ($definition === null) {
                continue;
            }

            $result[] = [
                'name' => $field,
                'type' => 'string',
                'nullable' => true,
                'calculated' => $definition['relation'] !== null,
                'searchable' => in_array($field, $searchable, true),
                'filterable' => in_array($field, $filterable, true),
                'sortable' => in_array($field, $sortable, true),
            ];
        }

        return $result;
    }

    /**
     * Generic pagination helper reusable by child services.
     *
     * @return array{total: int, items: array<int, TModel>}
     */
    public function paginate(
        int $page = 1,
        int $perPage = 10,
        ?string $search = null,
        array $filters = [],
        ?string $sortField = null,
        string $sortDirection = 'asc',
    ): array {
        $query = $this->buildQuery(
            search: $search,
            filters: $filters,
            sortField: $sortField,
            sortDirection: $sortDirection,
        );

        $total = (clone $query)->count();

        $items = $query
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get()
            ->all();

        return [
            'total' => $total,
            'items' => $items,
        ];
    }

    public function countMatching(?string $search = null, array $filters = []): int
    {
        return $this->buildQuery(search: $search, filters: $filters)->count();
    }

    /**
     * @return array<int, string>|array<string, string|array{path?: string, operator?: string, cast?: 'int'|'float'|'bool'|'string'}>
     */
    abstract protected function searchableFields(): array;

    /**
     * @return array<int, string>|array<string, string|array{path?: string, operator?: string, cast?: 'int'|'float'|'bool'|'string'}>
     */
    abstract protected function filterableFields(): array;

    /**
     * @return array<int, string>|array<string, string|array{path?: string, operator?: string, cast?: 'int'|'float'|'bool'|'string'}>
     */
    abstract protected function sortableFields(): array;

    /**
     * @return array{field: string, direction: 'asc'|'desc'}
     */
    protected function defaultSort(): array
    {
        return [
            'field' => $this->newModel()->getKeyName(),
            'direction' => 'asc',
        ];
    }

    protected function newModel(): Model
    {
        $class = $this->modelClass;

        /** @var Model $model */
        $model = new $class();

        return $model;
    }

    /**
     * @return Builder<TModel>
     */
    protected function newBaseQuery(): Builder
    {
        $model = $this->newModel();

        /** @var Builder<TModel> $query */
        $query = $model->newQuery();

        $query->with($this->resolveWithRelations());

        return $query;
    }

    /**
     * @return Builder<TModel>
     */
    protected function buildQuery(
        ?string $search = null,
        array $filters = [],
        ?string $sortField = null,
        string $sortDirection = 'asc',
    ): Builder {
        $query = $this->newBaseQuery();
        $this->joinedRelationAliases = [];

        $this->applyFilters($query, $filters);
        $this->applySearch($query, $search);
        $this->applySort($query, $sortField, $sortDirection);

        /** @var Builder<TModel> $query */
        return $query;
    }

    /**
     * @param Builder<TModel> $query
     * @param array<string, mixed> $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        $allowed = $this->normalizeFieldMap($this->filterableFields(), defaultOperator: 'eq');

        foreach ($filters as $field => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $definition = $this->resolveDefinition((string) $field, $allowed);
            if ($definition === null) {
                continue;
            }

            $operator = $definition['operator'];
            $filterValue = $this->castValue($value, $definition['cast']);

            if ($definition['relation'] === null) {
                $query->where(
                    $this->qualifyBaseColumn($definition['column']),
                    $operator,
                    $this->normalizeOperatorValue($operator, $filterValue)
                );

                continue;
            }

            $query->whereHas($definition['relation'], function (Builder $relationQuery) use ($definition, $operator, $filterValue): void {
                $relationQuery->where(
                    $definition['column'],
                    $operator,
                    $this->normalizeOperatorValue($operator, $filterValue)
                );
            });
        }
    }

    /**
     * @param Builder<TModel> $query
     */
    private function applySearch(Builder $query, ?string $search): void
    {
        $term = trim((string) $search);
        if ($term === '') {
            return;
        }

        $definitions = array_values($this->normalizeFieldMap($this->searchableFields(), defaultOperator: 'like'));
        if ($definitions === []) {
            return;
        }

        $query->where(function (Builder $where) use ($definitions, $term): void {
            foreach ($definitions as $index => $definition) {
                $method = $index === 0 ? 'where' : 'orWhere';

                if ($definition['relation'] === null) {
                    $where->{$method}(
                        $this->qualifyBaseColumn($definition['column']),
                        $definition['operator'],
                        $this->normalizeOperatorValue($definition['operator'], $term)
                    );

                    continue;
                }

                $where->orWhereHas($definition['relation'], function (Builder $relationQuery) use ($definition, $term): void {
                    $relationQuery->where(
                        $definition['column'],
                        $definition['operator'],
                        $this->normalizeOperatorValue($definition['operator'], $term)
                    );
                });
            }
        });
    }

    /**
     * @param Builder<TModel> $query
     */
    private function applySort(Builder $query, ?string $sortField, string $sortDirection): void
    {
        $allowed = $this->normalizeFieldMap($this->sortableFields(), defaultOperator: 'eq');
        $direction = strtolower($sortDirection) === 'desc' ? 'desc' : 'asc';

        $targetField = $sortField;
        if ($targetField === null || !isset($allowed[$targetField])) {
            $default = $this->defaultSort();
            $targetField = $default['field'];
            $direction = strtolower($default['direction']) === 'desc' ? 'desc' : 'asc';
        }

        $definition = $this->resolveDefinition((string) $targetField, $allowed);
        if ($definition === null) {
            $query->orderBy($this->qualifyBaseColumn($this->newModel()->getKeyName()), 'asc');

            return;
        }

        if ($definition['relation'] === null) {
            $query->orderBy($this->qualifyBaseColumn($definition['column']), $direction);

            return;
        }

        $alias = $this->joinSortableRelation($query, $definition['relation']);
        if ($alias === null) {
            // Fallback for non-joinable relations.
            $query->orderBy($this->qualifyBaseColumn($this->newModel()->getKeyName()), 'asc');

            return;
        }

        $query->orderBy($alias.'.'.$definition['column'], $direction);
    }

    /**
     * @param Builder<TModel> $query
     */
    private function joinSortableRelation(Builder $query, string $relation): ?string
    {
        if (isset($this->joinedRelationAliases[$relation])) {
            return $this->joinedRelationAliases[$relation];
        }

        $info = $this->discoverRelations()[$relation] ?? null;
        if ($info === null) {
            return null;
        }

        $relationObject = $info['relation'];
        $alias = 'sort_rel_'.Str::snake($relation);
        $table = $info['related_table'];

        if ($relationObject instanceof BelongsTo) {
            $query->leftJoin($table.' as '.$alias, $relationObject->getQualifiedForeignKeyName(), '=', $alias.'.'.$relationObject->getOwnerKeyName());
            $this->joinedRelationAliases[$relation] = $alias;

            return $alias;
        }

        if ($relationObject instanceof HasOne || $relationObject instanceof MorphOne) {
            $query->leftJoin($table.' as '.$alias, $alias.'.'.$relationObject->getForeignKeyName(), '=', $relationObject->getQualifiedParentKeyName());
            $this->joinedRelationAliases[$relation] = $alias;

            return $alias;
        }

        return null;
    }

    /**
     * @return array<string, array{type: class-string<Relation>, related_table: string, relation: Relation}>
     */
    private function discoverRelations(): array
    {
        if ($this->relationCache !== []) {
            return $this->relationCache;
        }

        $model = $this->newModel();
        $reflection = new ReflectionClass($model);

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if (
                $method->class !== $reflection->getName()
                || $method->isStatic()
                || $method->getNumberOfParameters() > 0
            ) {
                continue;
            }

            try {
                $relation = $method->invoke($model);
            } catch (Throwable) {
                continue;
            }

            if (!$relation instanceof Relation) {
                continue;
            }

            $this->relationCache[$method->getName()] = [
                'type' => $relation::class,
                'related_table' => $relation->getRelated()->getTable(),
                'relation' => $relation,
            ];
        }

        return $this->relationCache;
    }

    /**
     * @return array<int, string>
     */
    private function resolveWithRelations(): array
    {
        $relationFields = [];

        foreach ([$this->searchableFields(), $this->filterableFields(), $this->sortableFields()] as $fieldMap) {
            foreach ($this->normalizeFieldMap($fieldMap, defaultOperator: 'eq') as $definition) {
                if ($definition['relation'] !== null) {
                    $relationFields[] = $definition['relation'];
                }
            }
        }

        return array_values(array_unique(array_merge($this->with, $relationFields)));
    }

    /**
     * @param array<int, string>|array<string, string|array{path?: string, operator?: string, cast?: 'int'|'float'|'bool'|'string'}> $definitions
     * @return array<string, array{field: string, path: string, relation: string|null, column: string, operator: string, cast: 'int'|'float'|'bool'|'string'|null}>
     */
    private function normalizeFieldMap(array $definitions, string $defaultOperator): array
    {
        $result = [];

        foreach ($definitions as $key => $value) {
            $definition = $this->normalizeFieldDefinition($key, $value, $defaultOperator);
            $result[$definition['field']] = $definition;
        }

        return $result;
    }

    /**
     * @param int|string $key
     * @param string|array{path?: string, operator?: string, cast?: 'int'|'float'|'bool'|'string'} $value
     * @return array{field: string, path: string, relation: string|null, column: string, operator: string, cast: 'int'|'float'|'bool'|'string'|null}
     */
    private function normalizeFieldDefinition(int|string $key, string|array $value, string $defaultOperator): array
    {
        $field = is_int($key)
            ? (string) $value
            : (string) $key;

        if (is_string($value)) {
            $path = is_int($key) ? $value : $value;

            return $this->buildNormalizedDefinition($field, $path, $defaultOperator, null);
        }

        $path = (string) ($value['path'] ?? $field);
        $operator = (string) ($value['operator'] ?? $defaultOperator);
        $cast = $value['cast'] ?? null;

        return $this->buildNormalizedDefinition($field, $path, $operator, $cast);
    }

    /**
     * @return array{field: string, path: string, relation: string|null, column: string, operator: string, cast: 'int'|'float'|'bool'|'string'|null}
     */
    private function buildNormalizedDefinition(string $field, string $path, string $operator, ?string $cast): array
    {
        $relation = null;
        $column = $path;

        if (str_contains($path, '.')) {
            [$relation, $column] = explode('.', $path, 2);
        }

        return [
            'field' => $field,
            'path' => $path,
            'relation' => $relation,
            'column' => $column,
            'operator' => $this->normalizeOperator($operator),
            'cast' => $cast,
        ];
    }

    /**
     * @param array<string, array{field: string, path: string, relation: string|null, column: string, operator: string, cast: 'int'|'float'|'bool'|'string'|null}> $definitions
     * @return array{field: string, path: string, relation: string|null, column: string, operator: string, cast: 'int'|'float'|'bool'|'string'|null}|null
     */
    private function resolveDefinition(string $field, array $definitions): ?array
    {
        return $definitions[$field] ?? null;
    }

    private function qualifyBaseColumn(string $column): string
    {
        return $this->newModel()->qualifyColumn($column);
    }

    private function normalizeOperator(string $operator): string
    {
        $normalized = strtolower(trim($operator));

        return match ($normalized) {
            'eq', '=' => '=',
            'ne', '!=' => '!=',
            'gt', '>' => '>',
            'gte', '>=' => '>=',
            'lt', '<' => '<',
            'lte', '<=' => '<=',
            'like' => 'like',
            default => '=',
        };
    }

    private function normalizeOperatorValue(string $operator, mixed $value): mixed
    {
        if ($operator === 'like') {
            return '%'.$value.'%';
        }

        return $value;
    }

    private function castValue(mixed $value, ?string $cast): mixed
    {
        return match ($cast) {
            'int' => (int) $value,
            'float' => (float) $value,
            'bool' => filter_var($value, FILTER_VALIDATE_BOOL),
            'string' => (string) $value,
            default => $value,
        };
    }
}
