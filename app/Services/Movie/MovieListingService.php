<?php

namespace App\Services\Movie;

use App\Models\Movie;
use Idei\Usim\Contracts\ModelQueryableService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Read-only service for movies demo. No write operations are supported.
 *
 * @implements ModelQueryableService<Movie>
 */
class MovieListingService implements ModelQueryableService
{
    /** Fields allowed as sort columns. */
    private const SORTABLE_FIELDS = [
        'title',
        'release_year',
        'genre_name',
        'created_at',
    ];

    /** Filters accepted by filter() and search(). */
    private const FILTERABLE_FIELDS = [
        'genre_id',
        'genre_name',
        'release_year',
        'title',
    ];

    // -------------------------------------------------------------------------
    // ModelReadService
    // -------------------------------------------------------------------------

    public function findById(int|string $id): ?Model
    {
        return Movie::with('genre')->find($id);
    }

    public function all(): array
    {
        return Movie::with('genre')->get()->all();
    }

    // -------------------------------------------------------------------------
    // ModelFilterService
    // -------------------------------------------------------------------------

    /**
     * Filter movies by one or more fields.
     *
     * Accepted keys: genre_id, genre_name, release_year, title.
     *
     * @param array<string, mixed> $filters
     * @return array<int, Movie>
     */
    public function filter(array $filters): array
    {
        return $this->buildQuery(filters: $filters)->get()->all();
    }

    // -------------------------------------------------------------------------
    // ModelSortService
    // -------------------------------------------------------------------------

    /**
     * @param 'asc'|'desc' $direction
     * @return array<int, Movie>
     */
    public function sortBy(string $field, string $direction = 'asc'): array
    {
        return $this->buildQuery(sortField: $field, sortDirection: $direction)->get()->all();
    }

    // -------------------------------------------------------------------------
    // ModelSearchService
    // -------------------------------------------------------------------------

    /**
     * Full-text search across title, synopsis, cast_members and genre name.
     * Optional filters (same keys as filter()) can be combined.
     *
     * @param array<string, mixed> $filters
     * @return array<int, Movie>
     */
    public function search(string $term, array $filters = []): array
    {
        return $this->buildQuery(search: $term, filters: $filters)->get()->all();
    }

    // -------------------------------------------------------------------------
    // ModelStructureService
    // -------------------------------------------------------------------------

    public function getModelStructure(): array
    {
        return [
            ['name' => 'id',           'type' => 'int',    'nullable' => false, 'calculated' => false, 'searchable' => false, 'filterable' => false, 'sortable' => false],
            ['name' => 'title',        'type' => 'string', 'nullable' => false, 'calculated' => false, 'searchable' => true,  'filterable' => true,  'sortable' => true],
            ['name' => 'release_year', 'type' => 'int',    'nullable' => false, 'calculated' => false, 'searchable' => false, 'filterable' => true,  'sortable' => true],
            ['name' => 'cast_members', 'type' => 'string', 'nullable' => false, 'calculated' => false, 'searchable' => true,  'filterable' => false, 'sortable' => false],
            ['name' => 'synopsis',     'type' => 'string', 'nullable' => false, 'calculated' => false, 'searchable' => true,  'filterable' => false, 'sortable' => false],
            ['name' => 'image_url',    'type' => 'string', 'nullable' => false, 'calculated' => false, 'searchable' => false, 'filterable' => false, 'sortable' => false],
            ['name' => 'genre_id',     'type' => 'int',    'nullable' => false, 'calculated' => false, 'searchable' => false, 'filterable' => true,  'sortable' => false],
            ['name' => 'genre_name',   'type' => 'string', 'nullable' => false, 'calculated' => true,  'searchable' => true,  'filterable' => true,  'sortable' => true],
        ];
    }

    // -------------------------------------------------------------------------
    // Pagination-aware helper for DataTable integration
    // -------------------------------------------------------------------------

    /**
     * Returns a paginated slice of movies, applying optional search, filters and sort.
     *
     * @param int $page          1-based page number
     * @param int $perPage       Rows per page
     * @param string|null $search Free-text term applied to searchable fields
     * @param array<string, mixed> $filters Filterable key/value pairs
     * @param string|null $sortField    Column to sort by (see SORTABLE_FIELDS)
     * @param string $sortDirection     'asc' or 'desc'
     * @return array{ total: int, items: array<int, Movie> }
     */
    public function listMovies(
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

    /**
     * Count total matching movies (useful for DataTable without fetching rows).
     */
    public function countMovies(?string $search = null, array $filters = []): int
    {
        return $this->buildQuery(search: $search, filters: $filters)->count();
    }

    // -------------------------------------------------------------------------
    // Internal query builder
    // -------------------------------------------------------------------------

    /**
     * @return Builder<Movie>
     */
    private function buildQuery(
        ?string $search = null,
        array $filters = [],
        ?string $sortField = null,
        string $sortDirection = 'asc',
    ): Builder {
        $query = Movie::query()->with('genre')->leftJoin('genres', 'genres.id', '=', 'movies.genre_id')
            ->select('movies.*', 'genres.name as genre_name');

        // --- Filters ---
        foreach ($filters as $field => $value) {
            if (!in_array($field, self::FILTERABLE_FIELDS, strict: true) || $value === null || $value === '') {
                continue;
            }

            match ($field) {
                'genre_id'     => $query->where('movies.genre_id', $value),
                'genre_name'   => $query->where('genres.name', 'like', '%' . $value . '%'),
                'release_year' => $query->where('movies.release_year', (int) $value),
                'title'        => $query->where('movies.title', 'like', '%' . $value . '%'),
            };
        }

        // --- Full-text search ---
        if ($search !== null && $search !== '') {
            $query->where(function (Builder $q) use ($search): void {
                $q->where('movies.title', 'like', '%' . $search . '%')
                    ->orWhere('movies.synopsis', 'like', '%' . $search . '%')
                    ->orWhere('movies.cast_members', 'like', '%' . $search . '%')
                    ->orWhere('genres.name', 'like', '%' . $search . '%');
            });
        }

        // --- Sort ---
        $sortDirection = strtolower($sortDirection) === 'desc' ? 'desc' : 'asc';

        if ($sortField !== null && in_array($sortField, self::SORTABLE_FIELDS, strict: true)) {
            $column = $sortField === 'genre_name' ? 'genres.name' : "movies.{$sortField}";
            $query->orderBy($column, $sortDirection);
        } else {
            $query->orderBy('movies.title', 'asc');
        }

        /** @var Builder<Movie> $query */
        return $query;
    }
}
