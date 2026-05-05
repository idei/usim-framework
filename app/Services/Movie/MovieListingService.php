<?php

namespace App\Services\Movie;

use App\Models\Movie;
use App\Services\Support\EloquentListingService;

/**
 * Read-only service for movies demo. No write operations are supported.
 */
class MovieListingService extends EloquentListingService
{
    protected string $modelClass = Movie::class;

    protected array $with = ['genre'];

    public function getModelStructure(): array
    {
        return [
            ['name' => 'id', 'type' => 'int', 'nullable' => false, 'calculated' => false, 'searchable' => false, 'filterable' => false, 'sortable' => false],
            ['name' => 'title', 'type' => 'string', 'nullable' => false, 'calculated' => false, 'searchable' => true, 'filterable' => true, 'sortable' => true],
            ['name' => 'release_year', 'type' => 'int', 'nullable' => false, 'calculated' => false, 'searchable' => false, 'filterable' => true, 'sortable' => true],
            ['name' => 'cast_members', 'type' => 'string', 'nullable' => false, 'calculated' => false, 'searchable' => true, 'filterable' => false, 'sortable' => false],
            ['name' => 'synopsis', 'type' => 'string', 'nullable' => false, 'calculated' => false, 'searchable' => true, 'filterable' => false, 'sortable' => false],
            ['name' => 'image_url', 'type' => 'string', 'nullable' => false, 'calculated' => false, 'searchable' => false, 'filterable' => false, 'sortable' => false],
            ['name' => 'genre_id', 'type' => 'int', 'nullable' => false, 'calculated' => false, 'searchable' => false, 'filterable' => true, 'sortable' => false],
            ['name' => 'genre_name', 'type' => 'string', 'nullable' => false, 'calculated' => true, 'searchable' => true, 'filterable' => true, 'sortable' => true],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function searchableFields(): array
    {
        return [
            'title' => 'title',
            'synopsis' => 'synopsis',
            'cast_members' => 'cast_members',
            'genre_name' => 'genre.name',
        ];
    }

    /**
     * @return array<string, array{path: string, operator?: string, cast?: 'int'|'float'|'bool'|'string'}>
     */
    protected function filterableFields(): array
    {
        return [
            'genre_name' => ['path' => 'genre.name', 'operator' => 'like'],
            'release_year' => ['path' => 'release_year', 'operator' => '=', 'cast' => 'int'],
            'title' => ['path' => 'title', 'operator' => 'like'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function sortableFields(): array
    {
        return [
            'title' => 'title',
            'release_year' => 'release_year',
            'genre_name' => 'genre.name',
            'created_at' => 'created_at',
        ];
    }

    /**
     * @return array{field: string, direction: 'asc'|'desc'}
     */
    protected function defaultSort(): array
    {
        return [
            'field' => 'title',
            'direction' => 'asc',
        ];
    }
}
