<?php

namespace Idei\Usim\DTOs;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * Immutable query parameters sent to a CardListable service.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class CardQuery implements Arrayable, JsonSerializable
{
    /**
     * @param  'asc'|'desc'  $sortDirection
     * @param  array<string, mixed>  $filters
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public string $search = '',
        public int $page = 1,
        public int $perPage = 12,
        public ?string $sortBy = null,
        public string $sortDirection = 'asc',
        public array $filters = [],
        public array $context = [],
    ) {}

    /**
     * Hydrate a CardQuery from loose request/component parameters.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $context
     */
    public static function fromArray(
        array $data,
        int $defaultPerPage = 12,
        ?string $defaultSortBy = null,
        string $defaultSortDirection = 'asc',
        array $context = [],
    ): self {
        $rawSearch = $data['search'] ?? '';
        $search = is_scalar($rawSearch) ? trim((string) $rawSearch) : '';

        $rawPage = $data['page'] ?? 1;
        $page = is_numeric($rawPage) ? max(1, (int) $rawPage) : 1;

        $rawPerPage = $data['per_page'] ?? $data['perPage'] ?? $defaultPerPage;
        $perPage = is_numeric($rawPerPage) ? max(1, min(200, (int) $rawPerPage)) : max(1, $defaultPerPage);

        $rawSortBy = $data['sort_by'] ?? $data['sortBy'] ?? $defaultSortBy;
        $sortBy = is_string($rawSortBy) && trim($rawSortBy) !== '' ? trim($rawSortBy) : null;

        $rawSortDir = $data['sort_direction'] ?? $data['sortDirection'] ?? $defaultSortDirection;
        $sortDirection = is_string($rawSortDir) && strtolower(trim($rawSortDir)) === 'desc' ? 'desc' : 'asc';

        $rawFilters = $data['filters'] ?? [];
        if (is_string($rawFilters) && trim($rawFilters) !== '') {
            $decoded = json_decode($rawFilters, true);
            $rawFilters = is_array($decoded) ? $decoded : [];
        }

        /** @var array<string, mixed> $filters */
        $filters = [];
        if (is_array($rawFilters)) {
            foreach ($rawFilters as $key => $value) {
                if (! is_string($key) || trim($key) === '') {
                    continue;
                }
                if ($value === null || $value === '' || $value === []) {
                    continue;
                }
                $filters[$key] = $value;
            }
        }

        $reserved = [
            'search',
            'page',
            'per_page',
            'perPage',
            'sort_by',
            'sortBy',
            'sort_direction',
            'sortDirection',
            'filters',
        ];
        $extraContext = array_diff_key($data, array_flip($reserved));
        /** @var array<string, mixed> $mergedContext */
        $mergedContext = array_merge($context, $extraContext);

        return new self(
            search: $search,
            page: $page,
            perPage: $perPage,
            sortBy: $sortBy,
            sortDirection: $sortDirection,
            filters: $filters,
            context: $mergedContext,
        );
    }

    /**
     * Retrieve a specific filter value or a default if not set.
     */
    public function filter(string $key, mixed $default = null): mixed
    {
        return array_key_exists($key, $this->filters) ? $this->filters[$key] : $default;
    }

    /**
     * Check whether a filter key has an active value.
     */
    public function hasFilter(string $key): bool
    {
        return array_key_exists($key, $this->filters);
    }

    /**
     * Zero-based offset for the current page.
     */
    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'search' => $this->search,
            'page' => $this->page,
            'per_page' => $this->perPage,
            'sort_by' => $this->sortBy,
            'sort_direction' => $this->sortDirection,
            'filters' => $this->filters,
            'context' => $this->context,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
