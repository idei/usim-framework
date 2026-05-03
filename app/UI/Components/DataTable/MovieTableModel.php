<?php

namespace App\UI\Components\DataTable;

use App\Services\Movie\MovieListingService;
use Idei\Usim\Components\Table;
use Idei\Usim\DataTable\AbstractDataTableModel;
use Idei\Usim\Support\UIStateManager;

class MovieTableModel extends AbstractDataTableModel
{
    private const SEARCH_KEY = 'movie_table_search';
    private const GENRE_FILTER_KEY = 'movie_table_genre_filter';
    private const MAX_CAST_LENGTH = 52;
    private const MAX_SYNOPSIS_LENGTH = 84;

    private ?MovieListingService $movieListingService = null;

    public function __construct(Table $tableBuilder)
    {
        parent::__construct($tableBuilder);
    }

    public function getColumns(): array
    {
        return [
            'title' => ['label' => 'Title', 'width' => [220, 220], 'sort_by' => 'title'],
            'genre' => ['label' => 'Genre', 'width' => [140, 140], 'sort_by' => 'genre_name'],
            'release_year' => ['label' => 'Year', 'width' => [90, 90], 'sort_by' => 'release_year'],
            'cast_members' => ['label' => 'Cast', 'width' => [260, 260]],
            'synopsis' => ['label' => 'Synopsis', 'width' => [360, 360]],
        ];
    }

    protected function getAllData(): array
    {
        return $this->movieListingService()->all();
    }

    protected function countTotal(): int
    {
        return $this->movieListingService()->countMovies(
            $this->getSearchTerm(),
            $this->buildFilters()
        );
    }

    public function setSearchTerm(?string $searchTerm): void
    {
        UIStateManager::storeKeyValue(self::SEARCH_KEY, $searchTerm);
    }

    public function getSearchTerm(): ?string
    {
        return UIStateManager::getKeyValue(self::SEARCH_KEY);
    }

    public function clearSearch(): void
    {
        UIStateManager::clearKeyValue(self::SEARCH_KEY);
    }

    public function setGenreFilter(?string $genre): void
    {
        UIStateManager::storeKeyValue(self::GENRE_FILTER_KEY, $this->normalizeFilterValue($genre));
    }

    public function getGenreFilter(): ?string
    {
        $value = UIStateManager::getKeyValue(self::GENRE_FILTER_KEY);

        return $this->normalizeFilterValue($value);
    }

    public function clearGenreFilter(): void
    {
        UIStateManager::clearKeyValue(self::GENRE_FILTER_KEY);
    }

    public function getPageData(): array
    {
        $pagination = $this->tableBuilder->getPaginationData();

        $result = $this->movieListingService()->listMovies(
            page: (int) $pagination['current_page'],
            perPage: (int) $pagination['per_page'],
            search: $this->getSearchTerm(),
            filters: $this->buildFilters(),
            sortField: $this->tableBuilder->getSortColumn(),
            sortDirection: (string) ($this->tableBuilder->getSortDirection() ?: 'asc'),
        );

        return $result['items'];
    }

    public function getFormattedPageData(int $currentPage, int $perPage): array
    {
        $movies = $this->getPageData();
        $formatted = [];

        foreach ($movies as $movie) {
            $formatted[] = [
                'title' => (string) $movie->title,
                'genre' => (string) ($movie->genre?->name ?? $movie->genre_name ?? 'Unknown'),
                'release_year' => (string) $movie->release_year,
                'cast_members' => $this->truncateText((string) $movie->cast_members, self::MAX_CAST_LENGTH),
                'synopsis' => $this->truncateText((string) $movie->synopsis, self::MAX_SYNOPSIS_LENGTH),
            ];
        }

        return $formatted;
    }

    private function movieListingService(): MovieListingService
    {
        if ($this->movieListingService === null) {
            $this->movieListingService = app(MovieListingService::class);
        }

        return $this->movieListingService;
    }

    /**
     * @return array<string, string>
     */
    private function buildFilters(): array
    {
        $filters = [];
        $genre = $this->getGenreFilter();

        if ($genre !== null) {
            $filters['genre_name'] = $genre;
        }

        return $filters;
    }

    private function normalizeFilterValue(?string $value): ?string
    {
        $normalized = trim((string) $value);

        if ($normalized === '' || $normalized === 'all') {
            return null;
        }

        return $normalized;
    }

    private function truncateText(string $value, int $maxLength): string
    {
        return mb_strlen($value) > $maxLength
            ? mb_strimwidth($value, 0, $maxLength, '...')
            : $value;
    }
}
