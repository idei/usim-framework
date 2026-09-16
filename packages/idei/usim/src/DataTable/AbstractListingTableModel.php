<?php

namespace Idei\Usim\DataTable;

use Idei\Usim\Components\Table;
use Idei\Usim\Support\EloquentListingService;

/**
 * Base data table model backed by a listing service (such as EloquentListingService).
 *
 * Implements common pagination, sorting, search extraction, and data retrieval logic (DRY),
 * delegating column definitions and row formatting (Template Method Pattern) to child classes.
 *
 * @template TModel of object
 */
abstract class AbstractListingTableModel extends AbstractTableModel
{
    protected object $listingService;

    public function __construct(Table $tableBuilder)
    {
        parent::__construct($tableBuilder);
        $this->listingService = $this->resolveListingService();
    }

    /**
     * Resolve the underlying listing service instance.
     *
     * @return EloquentListingService<TModel>|object
     */
    abstract protected function resolveListingService(): object;

    /**
     * Set a custom listing service instance (supports Dependency Injection).
     *
     * @param object $listingService
     * @return $this
     */
    public function setListingService(object $listingService): self
    {
        $this->listingService = $listingService;
        return $this;
    }

    /**
     * Get the listing service instance.
     *
     * @return object
     */
    public function getListingService(): object
    {
        return $this->listingService;
    }

    /**
     * Format an individual model item into a table row.
     *
     * Expected format:
     * [
     *     '_model_id' => $item->id,
     *     'column_name' => $formattedValue,
     *     ...
     * ]
     *
     * @param TModel $item
     * @return array<string, mixed>
     */
    abstract protected function formatRow(object $item): array;

    /**
     * Additional filters to pass to the listing service query.
     *
     * @return array<string, mixed>
     */
    protected function getFilters(): array
    {
        return [];
    }

    /**
     * Count total items matching current search term and filters.
     */
    protected function countTotal(): int
    {
        $service = $this->getListingService();
        $searchTerm = $this->tableBuilder->getSearchTerm();

        if ($service instanceof EloquentListingService) {
            return $service->countMatching($searchTerm ?: null, $this->getFilters());
        }

        if (method_exists($service, 'countMatching')) {
            /** @var mixed $count */
            $count = $service->countMatching($searchTerm ?: null, $this->getFilters());
            return is_int($count) ? $count : 0;
        }

        return 0;
    }

    /**
     * Fetch paginated and sorted items using the listing service.
     *
     * @param int|null $page
     * @param int|null $perPage
     * @return list<TModel>
     */
    protected function fetchPaginatedItems(?int $page = null, ?int $perPage = null): array
    {
        $pagination = $this->tableBuilder->getPaginationData();
        $sortBy = $this->tableBuilder->getSortColumn();
        $sortDir = $this->tableBuilder->getSortDirection();
        $searchTerm = $this->tableBuilder->getSearchTerm();

        $effectivePage = $page ?? $pagination['current_page'];
        $effectivePerPage = $perPage ?? $pagination['per_page'];

        if ($effectivePerPage <= 0) {
            $effectivePerPage = max(1, $this->countTotal());
        }

        $service = $this->getListingService();

        if ($service instanceof EloquentListingService) {
            $result = $service->paginate(
                page: $effectivePage,
                perPage: $effectivePerPage,
                search: $searchTerm ?: null,
                filters: $this->getFilters(),
                sortField: $sortBy ?: null,
                sortDirection: (string) ($sortDir ?: 'asc'),
            );

            /** @var list<TModel> $items */
            $items = array_values($result['items']);
            return $items;
        }

        if (method_exists($service, 'paginate')) {
            /** @var mixed $result */
            $result = $service->paginate(
                $effectivePage,
                $effectivePerPage,
                $searchTerm ?: null,
                $this->getFilters(),
                $sortBy ?: null,
                (string) ($sortDir ?: 'asc'),
            );

            if (is_array($result) && isset($result['items']) && is_array($result['items'])) {
                /** @var list<TModel> $items */
                $items = array_values($result['items']);
                return $items;
            }
        }

        return [];
    }

    /**
     * Raw data for the current page (satisfies AbstractTableModel contract).
     *
     * @return list<array<string, mixed>>
     */
    public function getPageData(): array
    {
        $items = $this->fetchPaginatedItems();

        return array_map(function (object $item): array {
            if (method_exists($item, 'toArray')) {
                /** @var array<string, mixed> $array */
                $array = $item->toArray();
                return $array;
            }

            return (array) $item;
        }, $items);
    }

    /**
     * Formatted data for the current page consumed by the Table component.
     *
     * @param int $currentPage
     * @param int $perPage
     * @return list<array<string, mixed>>
     */
    public function getFormattedPageData(int $currentPage, int $perPage): array
    {
        $items = $this->fetchPaginatedItems($currentPage, $perPage);
        $formatted = [];

        foreach ($items as $item) {
            $formatted[] = $this->formatRow($item);
        }

        return $formatted;
    }
}
