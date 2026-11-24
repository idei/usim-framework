<?php

namespace Idei\Usim\DTOs;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;
use JsonSerializable;

/**
 * Paginated response DTO returned by CardListable services.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class CardPageResult implements Arrayable, JsonSerializable
{
    /**
     * @param  list<CardItemDTO>  $items
     */
    public function __construct(
        public array $items,
        public ?int $total = null,
        public int $page = 1,
        public int $perPage = 12,
        public bool $hasMore = false,
    ) {}

    /**
     * Build a CardPageResult from a Collection or array of CardItemDTO / ListItemDTO / arrays.
     *
     * @param  Collection<covariant int|string, covariant mixed>|array<int|string, mixed>  $items
     */
    public static function make(
        Collection|array $items,
        ?int $total = null,
        int $page = 1,
        int $perPage = 12,
        ?bool $hasMore = null,
    ): self {
        $normalizedPage = max(1, $page);
        $normalizedPerPage = max(1, $perPage);
        $normalizedItems = self::normalizeItems($items);

        $resolvedHasMore = $hasMore;
        if ($resolvedHasMore === null) {
            if ($total !== null) {
                $resolvedHasMore = ($normalizedPage * $normalizedPerPage) < $total;
            } else {
                $resolvedHasMore = count($normalizedItems) >= $normalizedPerPage;
            }
        }

        return new self(
            items: $normalizedItems,
            total: $total !== null ? max(0, $total) : null,
            page: $normalizedPage,
            perPage: $normalizedPerPage,
            hasMore: $resolvedHasMore,
        );
    }

    /**
     * Slice a full in-memory Collection/array according to the given CardQuery.
     *
     * @param  Collection<covariant int|string, covariant mixed>|array<int|string, mixed>  $allItems
     */
    public static function fromCollection(Collection|array $allItems, CardQuery $query): self
    {
        $normalized = self::normalizeItems($allItems);
        $total = count($normalized);
        $totalPages = $total > 0 ? (int) ceil($total / $query->perPage) : 1;
        $safePage = min($query->page, $totalPages);
        $offset = ($safePage - 1) * $query->perPage;
        $slice = array_slice($normalized, $offset, $query->perPage);

        return new self(
            items: $slice,
            total: $total,
            page: $safePage,
            perPage: $query->perPage,
            hasMore: ($safePage * $query->perPage) < $total,
        );
    }

    /**
     * Total number of pages when total count is known.
     */
    public function totalPages(): int
    {
        if ($this->total === null) {
            return $this->hasMore ? $this->page + 1 : $this->page;
        }

        if ($this->total <= 0) {
            return 0;
        }

        return (int) ceil($this->total / max(1, $this->perPage));
    }

    /**
     * @return array{
     *   items: list<array<string, mixed>>,
     *   pagination: array{
     *     page: int,
     *     per_page: int,
     *     total: ?int,
     *     total_pages: int,
     *     has_more: bool,
     *     can_prev: bool,
     *     can_next: bool
     *   }
     * }
     */
    public function toArray(): array
    {
        return [
            'items' => array_map(
                static fn (CardItemDTO $item): array => $item->toArray(),
                $this->items
            ),
            'pagination' => [
                'page' => $this->page,
                'per_page' => $this->perPage,
                'total' => $this->total,
                'total_pages' => $this->totalPages(),
                'has_more' => $this->hasMore,
                'can_prev' => $this->page > 1,
                'can_next' => $this->hasMore,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @param  Collection<covariant int|string, covariant mixed>|array<int|string, mixed>  $items
     * @return list<CardItemDTO>
     */
    private static function normalizeItems(Collection|array $items): array
    {
        $raw = $items instanceof Collection ? $items->all() : $items;
        $normalized = [];
        $index = 0;

        foreach ($raw as $key => $item) {
            $fallbackId = is_int($key) ? $index : $key;

            if ($item instanceof CardItemDTO) {
                $normalized[] = $item;
            } elseif ($item instanceof ListItemDTO) {
                $normalized[] = CardItemDTO::fromListItemDTO($item);
            } elseif ($item instanceof Arrayable) {
                /** @var array<string, mixed> $arr */
                $arr = $item->toArray();
                $normalized[] = CardItemDTO::fromArray($arr, $fallbackId);
            } elseif (is_array($item)) {
                /** @var array<string, mixed> $item */
                $normalized[] = CardItemDTO::fromArray($item, $fallbackId);
            } elseif (is_scalar($item)) {
                $normalized[] = CardItemDTO::make(id: $fallbackId, title: (string) $item);
            }

            $index++;
        }

        return $normalized;
    }
}
