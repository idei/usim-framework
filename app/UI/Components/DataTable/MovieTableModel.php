<?php

namespace App\UI\Components\DataTable;

use App\Services\Movie\MovieListingService;
use Idei\Usim\Components\Table;
use Idei\Usim\DataTable\AbstractDataTableModel;
use Idei\Usim\Support\UIStateManager;

class MovieTableModel extends AbstractDataTableModel
{
    private const GENRE_FILTER_KEY = 'movie_table_genre_filter';
    private const MAX_CAST_LENGTH = 52;

    private MovieListingService $listingService;

    public function __construct(Table $tableBuilder)
    {
        parent::__construct($tableBuilder);
        $this->listingService = app(MovieListingService::class);
    }

    public function getColumns(): array
    {
        return [
            'title' => ['label' => t('screen.demo.table_demo.title_column'), 'width' => [220, 220], 'sort_by' => 'title'],
            'genre' => ['label' => t('screen.demo.table_demo.genre_column'), 'width' => [140, 140], 'sort_by' => 'genre_name'],
            'release_year' => ['label' => t('screen.demo.table_demo.release_year_column'), 'width' => [90, 90], 'sort_by' => 'release_year'],
            'cast_members' => ['label' => t('screen.demo.table_demo.cast_column'), 'width' => [260, 260]],
        ];
    }

    protected function getAllData(): array
    {
        return $this->listingService->all();
    }

    protected function countTotal(): int
    {
        return $this->listingService->countMatching(
            $this->tableBuilder->getSearchTerm(),
            $this->buildFilters()
        );
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

        $result = $this->listingService->paginate(
            page: (int) $pagination['current_page'],
            perPage: (int) $pagination['per_page'],
            search: $this->tableBuilder->getSearchTerm(),
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
            ];
        }

        return $formatted;
    }

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
