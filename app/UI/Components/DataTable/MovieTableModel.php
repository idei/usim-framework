<?php

namespace App\UI\Components\DataTable;

use App\Services\Movie\MovieListingService;
use Idei\Usim\Components\Table;
use Idei\Usim\DataTable\AbstractDataTableModel;

class MovieTableModel extends AbstractDataTableModel
{
    private const MAX_CAST_LENGTH = 52;

    private MovieListingService $listingService;

    public function __construct(Table $tableBuilder)
    {
        parent::__construct($tableBuilder);
        $this->listingService = app(MovieListingService::class);
    }

    public function getColumns(): array
    {
        $prefix = 'screen.demo.table_demo';
        return [
            'title' => ['label' => t("$prefix.title_column"), 'width' => 350, 'sort_by' => 'title'],
            'genre' => ['label' => t("$prefix.genre_column"), 'width' => 120, 'sort_by' => 'genre_name'],
            'release_year' => ['label' => t("$prefix.release_year_column"), 'width' => 90, 'sort_by' => 'release_year'],
            'cast_members' => ['label' => t("$prefix.cast_column"), 'width' => 400],
        ];
    }

    protected function getAllData(): array
    {
        return [];
    }

    protected function countTotal(): int
    {
        $searchTerm = $this->tableBuilder->getSearchTerm();
        return $this->listingService->countMatching($searchTerm);
    }

    public function getPageData(): array
    {
        $pagination = $this->tableBuilder->getPaginationData();

        $result = $this->listingService->paginate(
            page: (int) $pagination['current_page'],
            perPage: (int) $pagination['per_page'],
            search: $this->tableBuilder->getSearchTerm(),
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
            $formatted[] = $this->formatRow($movie);
        }

        return $formatted;
    }

    public function formatRow(object $row): array
    {
        return [
            'title' => (string) $row->title,
            'genre' => (string) ($row->genre?->name ?? $row->genre_name ?? 'Unknown'),
            'release_year' => (string) $row->release_year,
            'cast_members' => $this->truncateText((string) $row->cast_members, self::MAX_CAST_LENGTH),
        ];
    }

    private function truncateText(string $value, int $maxLength): string
    {
        return mb_strlen($value) > $maxLength
            ? mb_strimwidth($value, 0, $maxLength, '...')
            : $value;
    }
}
