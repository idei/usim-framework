<?php

namespace Idei\Usim\Components;

use Idei\Usim\Contracts\CardGrid\CardListable;
use Idei\Usim\Contracts\ItemList\Listable;
use Idei\Usim\Contracts\UIElement;
use Idei\Usim\DTOs\CardActionDTO;
use Idei\Usim\DTOs\CardItemDTO;
use Idei\Usim\DTOs\CardPageResult;
use Idei\Usim\DTOs\CardQuery;
use Idei\Usim\DTOs\ListItemDTO;
use Idei\Usim\Enums\AspectRatio;
use Idei\Usim\Enums\Style;
use Idei\Usim\Screen;
use Idei\Usim\Support\ImageSourceResolver;
use Idei\Usim\ValueObjects\Size;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;

/**
 * Builder for CardGrid UI components (`type: 'card_grid'`).
 *
 * Pure server-driven USIM responsive 2D card grid managing first-class child
 * `ItemCard` components with native `ComponentDiffer` incremental diffs:
 * - Service binding via `CardListable` (and automatic fallback for `Listable`),
 *   plus server-side static collection support via `items()`
 * - Integrated toolbar (debounced search, sorting, declarative filters: select,
 *   multi_select, toggle, chips) and pagination (`pages` & `load_more`) dispatched
 *   via `POST /api/ui-event` so the server always owns the authoritative card tree
 * - Deterministic per-item child `ItemCard` IDs (`<grid>__card_<itemId>`), so:
 *   - Mutating one card (`$grid->card($id)->badge(...)` or `$grid->patchCard(...)`)
 *     emits ONLY that single `ItemCard`'s changed properties
 *   - Removing one card (`$grid->removeCard($id)`) emits `{ "<id>": { "parent": null } }`
 *   - `load_more` keeps already-mounted cards untouched (zero diff) and emits ONLY new cards
 */
class CardGrid extends UIComponent
{
    public const SERVICE_ACTION_PREFIX = '__usim_card_grid:';

    protected ?object $serviceInstance = null;

    protected bool $serviceDataStale = false;

    protected bool $itemsManuallySet = false;

    /**
     * Active child ItemCard components keyed by stringified item ID.
     *
     * @var array<string, ItemCard>
     */
    protected array $cards = [];

    public function __construct(?string $name = null)
    {
        parent::__construct($name);
        $this->type = 'card_grid';
        $this->config['type'] = 'card_grid';
        $this->config['query_action'] = $this->serviceActionFor('query');
    }

    /**
     * @return array<string, mixed>
     */
    protected function getDefaultConfig(): array
    {
        return [
            // Header
            'title' => null,
            'title_style' => Style::DEFAULT->value,
            'subtitle' => null,
            'show_count' => true,
            'header_action' => null,
            'header_actions' => [],

            // Pagination & Active Count
            'visible_count' => 0,
            'pagination' => [
                'mode' => 'pages', // 'pages' | 'load_more'
                'page' => 1,
                'per_page' => 12,
                'per_page_options' => [12, 24, 36, 48],
                'show_per_page' => true,
                'total' => 0,
                'total_pages' => 0,
                'has_more' => false,
                'can_prev' => false,
                'can_next' => false,
            ],

            // Search
            'searchable' => true,
            'search_placeholder' => 'Buscar...',
            'search_term' => null,
            'min_search_chars' => 0,
            'debounce' => 300,

            // Sorting
            'sort_options' => [],
            'sort_by' => null,
            'sort_direction' => 'asc',

            // Integrated Filters
            'filters' => [],
            'active_filters' => [],

            // Service Binding & Event Query Action (POST /api/ui-event)
            'query_action' => null,
            'parameters' => [],
            'loading' => false,
            'service' => null,
            'static_items' => null,

            // Grid & Child ItemCard Presentation
            'min_card_width' => '280px',
            'max_columns' => null,
            'gap' => '16px',
            'card_layout' => 'vertical', // 'vertical' | 'horizontal'
            'variant' => 'elevated', // 'elevated' | 'outlined' | 'flat'
            'density' => 'comfortable', // 'compact' | 'comfortable' | 'spacious'
            'image_aspect_ratio' => '16/9',
            'max_visible_actions' => 2,
            'max_visible_tags' => 3,
            'skeleton_count' => 6,
            'show_layout_toggle' => false,

            // Default click action propagated to child ItemCards lacking their own clickAction/url
            'on_card_click' => null,

            // States & Copy
            'disabled' => false,
            'empty_message' => 'No hay elementos para mostrar',
            'no_results_message' => 'No se encontraron resultados con los criterios actuales',
            'min_chars_message' => 'Escribe al menos :count caracteres para buscar...',
            'load_more_label' => 'Cargar más',
            'clear_filters_label' => 'Limpiar filtros',
            'sort_label' => 'Ordenar',
            'filters_label' => 'Filtros',
        ];
    }

    // ─── Tree Connection & Serialization ──────────────────────────────────────

    public function connectChild(UIElement $element): void
    {
        if ($element instanceof ItemCard) {
            $element->setParent($this->id);
            $this->cards[$element->getItemKey()] = $element;
        }
    }

    public function postConnect(): void
    {
        if ($this->cards === []) {
            return;
        }

        uasort($this->cards, static fn (ItemCard $a, ItemCard $b): int => $a->getCardOrder() <=> $b->getCardOrder());
    }

    /**
     * {@inheritDoc}
     */
    public function toJson(?int $order = null): array
    {
        $this->hydrateServicePageIfStale();

        $result = parent::toJson($order);

        $orderedCards = $this->getCards();
        foreach ($orderedCards as $index => $card) {
            $card->setParent($this->id);
            $result += $card->toJson($index + 1);
        }

        return $result;
    }

    // ─── Header ───────────────────────────────────────────────────────────────

    public function title(?string $title): static
    {
        return $this->setConfig('title', $title);
    }

    public function titleStyle(Style|string $style): static
    {
        return $this->setConfig('title_style', self::resolveStyle($style));
    }

    public function subtitle(?string $subtitle): static
    {
        return $this->setConfig('subtitle', $subtitle);
    }

    public function showCount(bool $show = true): static
    {
        return $this->setConfig('show_count', $show);
    }

    /**
     * Configure an optional single global CTA button in the grid header (e.g. "+ Nuevo").
     * Preserved as backward-compatible syntactic sugar over `headerActions()`.
     *
     * @param  array<string, mixed>  $parameters
     */
    public function headerAction(
        ?string $label,
        ?string $action = null,
        array $parameters = [],
        ?string $icon = null,
        Style|string $style = Style::PRIMARY,
        string $variant = 'solid',
        ?string $url = null,
        string $target = '_self',
        bool $disabled = false,
        ?string $tooltip = null
    ): static {
        if ($label === null || trim($label) === '') {
            $this->setConfig('header_action', null);
            $this->setConfig('header_actions', []);

            return $this;
        }

        $resolvedStyle = self::resolveStyle($style, Style::PRIMARY);
        $resolvedVariant = match (strtolower(trim($variant))) {
            'outline' => 'outline',
            'ghost' => 'ghost',
            default => 'solid',
        };

        $entry = [
            'label' => $label,
            'action' => $action,
            'parameters' => $parameters,
            'icon' => $icon,
            'style' => $resolvedStyle,
            'variant' => $resolvedVariant,
            'disabled' => $disabled,
        ];

        if ($url !== null && trim($url) !== '') {
            $entry['url'] = trim($url);
            $entry['target'] = trim($target) !== '' ? trim($target) : '_self';
        }

        if ($tooltip !== null && trim($tooltip) !== '') {
            $entry['tooltip'] = trim($tooltip);
        }

        $this->setConfig('header_action', $entry);
        $this->setConfig('header_actions', [$entry]);

        return $this;
    }

    /**
     * Configure multiple contextual action buttons in the grid header.
     *
     * @param  array<int, CardActionDTO|array<string, mixed>>  $actions
     */
    public function headerActions(array $actions): static
    {
        $normalized = [];

        foreach ($actions as $entry) {
            if ($entry instanceof CardActionDTO) {
                if ($entry->label !== '' || $entry->icon !== null) {
                    $normalized[] = $entry->toArray();
                }
            } elseif (is_array($entry)) {
                /** @var array<string, mixed> $entry */
                $dto = CardActionDTO::fromArray($entry);
                if ($dto->label !== '' || $dto->icon !== null) {
                    $normalized[] = $dto->toArray();
                }
            }
        }

        $this->setConfig('header_actions', $normalized);
        $this->setConfig('header_action', $normalized[0] ?? null);

        return $this;
    }

    /**
     * Append a single contextual action button to the grid header.
     *
     * @param  array<string, mixed>  $parameters
     */
    public function addHeaderAction(
        string $label,
        ?string $action = null,
        array $parameters = [],
        ?string $icon = null,
        Style|string $style = Style::PRIMARY,
        string $variant = 'solid',
        bool $disabled = false,
        ?string $url = null,
        string $target = '_self',
        ?string $tooltip = null
    ): static {
        $existing = is_array($this->config['header_actions'] ?? null) ? $this->config['header_actions'] : [];
        if ($existing === [] && is_array($this->config['header_action'] ?? null)) {
            $existing = [$this->config['header_action']];
        }

        $existing[] = CardActionDTO::make(
            label: $label,
            action: $action,
            parameters: $parameters,
            url: $url,
            target: $target,
            icon: $icon,
            style: $style,
            variant: $variant,
            disabled: $disabled,
            tooltip: $tooltip,
        )->toArray();

        $existing = array_values($existing);
        $this->setConfig('header_actions', $existing);
        $this->setConfig('header_action', $existing[0]);

        return $this;
    }

    // ─── Child ItemCard Management (Native USIM Tree) ─────────────────────────

    /**
     * Retrieve a currently mounted child ItemCard by its domain item ID.
     */
    public function card(int|string $itemId): ?ItemCard
    {
        $this->hydrateServicePageIfStale();

        return $this->cards[(string) $itemId] ?? null;
    }

    /**
     * Find an already-mounted child ItemCard by component name without triggering
     * premature lazy service hydration (used by Container::findByName()).
     */
    public function findMountedCardByName(string $name): ?ItemCard
    {
        foreach ($this->cards as $card) {
            if ($card->getName() === $name) {
                return $card;
            }
        }

        return null;
    }

    /**
     * Find an already-mounted child ItemCard by component ID without triggering
     * premature lazy service hydration (used by Container::findById()).
     */
    public function findMountedCardById(int $id): ?ItemCard
    {
        foreach ($this->cards as $card) {
            if ($card->getId() === $id) {
                return $card;
            }
        }

        return null;
    }

    /**
     * Retrieve all currently mounted child ItemCards ordered by position.
     *
     * @return list<ItemCard>
     */
    public function getCards(): array
    {
        $this->hydrateServicePageIfStale();

        $list = array_values($this->cards);
        usort($list, static fn (ItemCard $a, ItemCard $b): int => $a->getCardOrder() <=> $b->getCardOrder());

        return $list;
    }

    /**
     * Set static card items (no external service needed).
     * Search, sort, filters, and pagination are evaluated server-side via POST /api/ui-event.
     *
     * @param  Collection<covariant int|string, covariant mixed>|array<int|string, mixed>  $items
     */
    public function items(Collection|array $items): static
    {
        $this->itemsManuallySet = true;
        $this->serviceDataStale = false;

        $normalized = self::normalizeItems($items);
        $this->setConfig('static_items', $normalized);

        $result = $this->executeStaticQuery($this->buildQueryFromCurrentState());
        $this->syncCardsFromPageResult($result, append: false);

        return $this;
    }

    /**
     * Apply a CardPageResult directly, synchronizing child ItemCard components
     * and pagination metadata.
     */
    public function pageResult(CardPageResult $result, bool $append = false): static
    {
        $this->serviceDataStale = false;
        $this->syncCardsFromPageResult($result, append: $append);

        return $this;
    }

    /**
     * Mutate a single mounted child ItemCard cleanly via native ComponentDiffer.
     *
     * Accepts either a full CardItemDTO or a partial associative array of changed
     * fields. Emits ONLY the mutated properties of that single ItemCard.
     *
     * @param  CardItemDTO|array<string, mixed>  $changes
     */
    public function patchCard(int|string $id, CardItemDTO|array $changes): static
    {
        $card = $this->card($id);
        $this->syncStaticItemPatch($id, $changes);

        if ($card === null) {
            return $this;
        }

        if ($changes instanceof CardItemDTO) {
            [$defaultClickAction, $defaultClickParams] = $this->resolveDefaultClickConfig();
            $aspectRatio = is_string($this->config['image_aspect_ratio'] ?? null)
                ? $this->config['image_aspect_ratio']
                : '16/9';
            $this->applyInheritedImageConfigToCard($card);
            $card->applyFromDTO($changes, $defaultClickAction, $defaultClickParams, $aspectRatio);
            $this->applyGridPresentationToCard($card);

            return $this;
        }

        $card->patch($changes);

        return $this;
    }

    /**
     * Remove a single mounted child ItemCard from the grid tree.
     * ComponentDiffer automatically emits `{ "<card_id>": { "parent": null } }`.
     * When operating in static `items()` mode, also removes the entry from `static_items`.
     */
    public function removeCard(int|string $id): static
    {
        $this->hydrateServicePageIfStale();
        $key = (string) $id;

        $removedFromStatic = $this->syncStaticItemRemove($key);
        $wasMounted = isset($this->cards[$key]);

        if (! $wasMounted && ! $removedFromStatic) {
            return $this;
        }

        if ($wasMounted) {
            $this->cards[$key]->setParent(null);
            unset($this->cards[$key]);

            $this->reindexCardPositions();
            $this->setConfig('visible_count', count($this->cards));
        }

        $pagination = $this->getPagination();
        if ($pagination['total'] !== null) {
            $pagination['total'] = max(0, $pagination['total'] - 1);
            $pagination['total_pages'] = $pagination['total'] > 0
                ? (int) ceil($pagination['total'] / max(1, $pagination['per_page']))
                : 0;
            $pagination['has_more'] = ($pagination['page'] * $pagination['per_page']) < $pagination['total'];
            $pagination['can_next'] = $pagination['has_more'];
            $this->setConfig('pagination', $pagination);
        }

        return $this;
    }

    /**
     * Prepend a new ItemCard at position 0 of the grid tree.
     * ComponentDiffer emits ONLY the new ItemCard and position updates for shifted siblings.
     * When operating in static `items()` mode, also prepends the entry into `static_items`.
     *
     * @param  CardItemDTO|array<string, mixed>  $card
     */
    public function prependCard(CardItemDTO|array $card): static
    {
        $this->hydrateServicePageIfStale();
        $dto = $card instanceof CardItemDTO ? $card : CardItemDTO::fromArray($card);
        $key = (string) $dto->id;

        $existedInCards = isset($this->cards[$key]);
        $existedInStatic = $this->syncStaticItemPrepend($dto);

        $ordered = array_values(array_filter(
            $this->getCards(),
            static fn (ItemCard $existing): bool => $existing->getItemKey() !== $key
        ));

        $itemCard = $this->cards[$key] ?? $this->instantiateChildCard($key);
        $this->hydrateChildCard($itemCard, $dto, 0);

        $newMap = [$key => $itemCard];
        foreach ($ordered as $idx => $existing) {
            $existing->cardOrder($idx + 1);
            $newMap[$existing->getItemKey()] = $existing;
        }

        $this->cards = $newMap;
        $this->setConfig('visible_count', count($this->cards));

        if (! $existedInCards && ! $existedInStatic) {
            $pagination = $this->getPagination();
            if ($pagination['total'] !== null) {
                $pagination['total'] += 1;
                $pagination['total_pages'] = (int) ceil($pagination['total'] / max(1, $pagination['per_page']));
                $pagination['has_more'] = ($pagination['page'] * $pagination['per_page']) < $pagination['total'];
                $pagination['can_next'] = $pagination['has_more'];
                $this->setConfig('pagination', $pagination);
            }
        }

        return $this;
    }

    // ─── Pagination ───────────────────────────────────────────────────────────

    /**
     * @return array{
     *   mode: string,
     *   page: int,
     *   per_page: int,
     *   per_page_options: list<int>,
     *   show_per_page: bool,
     *   total: ?int,
     *   total_pages: int,
     *   has_more: bool,
     *   can_prev: bool,
     *   can_next: bool
     * }
     */
    public function getPagination(): array
    {
        $raw = $this->config['pagination'] ?? [];
        $data = is_array($raw) ? $raw : [];

        $mode = is_string($data['mode'] ?? null) && $data['mode'] === 'load_more'
            ? 'load_more'
            : 'pages';
        $page = is_numeric($data['page'] ?? null) ? max(1, (int) $data['page']) : 1;
        $perPage = is_numeric($data['per_page'] ?? null) ? max(1, (int) $data['per_page']) : 12;

        $rawOptions = $data['per_page_options'] ?? [12, 24, 36, 48];
        $perPageOptions = [];
        if (is_array($rawOptions)) {
            foreach ($rawOptions as $opt) {
                if (is_numeric($opt) && (int) $opt > 0) {
                    $perPageOptions[] = (int) $opt;
                }
            }
        }
        if ($perPageOptions === []) {
            $perPageOptions = [12, 24, 36, 48];
        }

        $total = array_key_exists('total', $data) && $data['total'] === null
            ? null
            : (is_numeric($data['total'] ?? null) ? max(0, (int) $data['total']) : 0);
        $totalPages = is_numeric($data['total_pages'] ?? null) ? max(0, (int) $data['total_pages']) : 0;

        return [
            'mode' => $mode,
            'page' => $page,
            'per_page' => $perPage,
            'per_page_options' => array_values(array_unique($perPageOptions)),
            'show_per_page' => (bool) ($data['show_per_page'] ?? true),
            'total' => $total,
            'total_pages' => $totalPages,
            'has_more' => (bool) ($data['has_more'] ?? false),
            'can_prev' => (bool) ($data['can_prev'] ?? ($page > 1)),
            'can_next' => (bool) ($data['can_next'] ?? false),
        ];
    }

    /**
     * Set pagination mode: 'pages' (classic numbered pagination) or 'load_more'.
     */
    public function paginationMode(string $mode): static
    {
        $normalized = strtolower(trim($mode)) === 'load_more' ? 'load_more' : 'pages';
        $pagination = $this->getPagination();
        $pagination['mode'] = $normalized;

        return $this->setConfig('pagination', $pagination);
    }

    public function perPage(int $perPage): static
    {
        $pagination = $this->getPagination();
        $pagination['per_page'] = max(1, min(200, $perPage));
        $this->markServiceDataStaleIfBound();

        return $this->setConfig('pagination', $pagination);
    }

    /**
     * @param  array<int, int>  $options
     */
    public function perPageOptions(array $options): static
    {
        $clean = [];
        foreach ($options as $option) {
            if ($option > 0) {
                $clean[] = $option;
            }
        }

        $pagination = $this->getPagination();
        $pagination['per_page_options'] = $clean !== [] ? array_values(array_unique($clean)) : [12, 24, 36, 48];

        return $this->setConfig('pagination', $pagination);
    }

    public function showPerPage(bool $show = true): static
    {
        $pagination = $this->getPagination();
        $pagination['show_per_page'] = $show;

        return $this->setConfig('pagination', $pagination);
    }

    public function page(int $page): static
    {
        $pagination = $this->getPagination();
        $pagination['page'] = max(1, $page);
        $this->markServiceDataStaleIfBound();

        return $this->setConfig('pagination', $pagination);
    }

    // ─── Search ───────────────────────────────────────────────────────────────

    public function searchable(bool $searchable = true, ?string $placeholder = null): static
    {
        $this->setConfig('searchable', $searchable);
        if ($placeholder !== null) {
            $this->setConfig('search_placeholder', $placeholder);
        }

        return $this;
    }

    public function searchPlaceholder(string $placeholder): static
    {
        return $this->setConfig('search_placeholder', $placeholder);
    }

    public function setSearchTerm(?string $search): static
    {
        $normalized = $search !== null ? trim($search) : null;
        $this->markServiceDataStaleIfBound();

        return $this->setConfig('search_term', $normalized === '' ? null : $normalized);
    }

    public function getSearchTerm(): ?string
    {
        $term = $this->config['search_term'] ?? null;

        return is_string($term) && $term !== '' ? $term : null;
    }

    public function minSearchChars(int $chars, ?string $message = null): static
    {
        $this->setConfig('min_search_chars', max(0, $chars));
        if ($message !== null) {
            $this->setConfig('min_chars_message', $message);
        }

        return $this;
    }

    public function debounce(int $ms): static
    {
        return $this->setConfig('debounce', max(0, $ms));
    }

    // ─── Sorting ──────────────────────────────────────────────────────────────

    /**
     * @param  array<int|string, mixed>  $options
     */
    public function sortOptions(array $options): static
    {
        $normalized = [];

        foreach ($options as $key => $entry) {
            if (is_array($entry)) {
                $rawField = $entry['field'] ?? $entry['value'] ?? (is_string($key) ? $key : '');
                $rawLabel = $entry['label'] ?? $rawField;
                $field = is_scalar($rawField) ? trim((string) $rawField) : '';
                $label = is_scalar($rawLabel) ? trim((string) $rawLabel) : '';
                if ($field !== '') {
                    $normalized[] = [
                        'field' => $field,
                        'label' => $label !== '' ? $label : $field,
                    ];
                }
            } elseif (is_string($key) && is_scalar($entry)) {
                $field = trim($key);
                $label = trim((string) $entry);
                if ($field !== '') {
                    $normalized[] = [
                        'field' => $field,
                        'label' => $label !== '' ? $label : $field,
                    ];
                }
            }
        }

        return $this->setConfig('sort_options', $normalized);
    }

    public function addSortOption(string $field, string $label): static
    {
        $current = is_array($this->config['sort_options'] ?? null) ? $this->config['sort_options'] : [];
        $current[] = [
            'field' => trim($field),
            'label' => trim($label),
        ];

        return $this->setConfig('sort_options', array_values($current));
    }

    public function defaultSort(?string $field, string $direction = 'asc'): static
    {
        $this->sortBy($field);
        $this->sortDirection($direction);

        return $this;
    }

    public function sortBy(?string $field): static
    {
        $this->markServiceDataStaleIfBound();

        return $this->setConfig('sort_by', $field !== null && trim($field) !== '' ? trim($field) : null);
    }

    public function sortDirection(string $direction): static
    {
        $normalized = strtolower(trim($direction)) === 'desc' ? 'desc' : 'asc';
        $this->markServiceDataStaleIfBound();

        return $this->setConfig('sort_direction', $normalized);
    }

    // ─── Integrated Toolbar Filters ───────────────────────────────────────────

    /**
     * @param  array<int, array<string, mixed>>  $filters
     */
    public function filters(array $filters): static
    {
        $normalized = [];
        $defaults = $this->getActiveFilters();

        foreach ($filters as $entry) {
            $filter = self::normalizeFilterDefinition($entry);
            if ($filter === null) {
                continue;
            }
            $normalized[] = $filter;

            if (
                array_key_exists('default', $filter)
                && $filter['default'] !== null
                && $filter['default'] !== ''
                && $filter['default'] !== []
                && ! array_key_exists($filter['name'], $defaults)
            ) {
                $defaults[$filter['name']] = $filter['default'];
            }
        }

        $this->setConfig('filters', $normalized);

        if ($defaults !== []) {
            $this->activeFilters($defaults);
        }

        return $this;
    }

    /**
     * @param  array<int|string, mixed>  $options
     */
    public function addSelectFilter(
        string $name,
        string $label,
        array $options,
        ?string $placeholder = null,
        ?string $default = null
    ): static {
        return $this->appendFilter([
            'name' => $name,
            'label' => $label,
            'type' => 'select',
            'options' => $options,
            'placeholder' => $placeholder,
            'default' => $default,
        ]);
    }

    /**
     * @param  array<int|string, mixed>  $options
     * @param  list<string>  $default
     */
    public function addMultiSelectFilter(
        string $name,
        string $label,
        array $options,
        ?string $placeholder = null,
        array $default = []
    ): static {
        return $this->appendFilter([
            'name' => $name,
            'label' => $label,
            'type' => 'multi_select',
            'options' => $options,
            'placeholder' => $placeholder,
            'default' => $default,
        ]);
    }

    public function addToggleFilter(
        string $name,
        string $label,
        bool $default = false
    ): static {
        return $this->appendFilter([
            'name' => $name,
            'label' => $label,
            'type' => 'toggle',
            'default' => $default ? true : null,
        ]);
    }

    /**
     * @param  array<int|string, mixed>  $options
     */
    public function addChipsFilter(
        string $name,
        string $label,
        array $options,
        ?string $default = null
    ): static {
        return $this->appendFilter([
            'name' => $name,
            'label' => $label,
            'type' => 'chips',
            'options' => $options,
            'default' => $default,
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function activeFilters(array $filters): static
    {
        $clean = [];
        foreach ($filters as $key => $value) {
            if ($value === null || $value === '' || $value === [] || $value === false) {
                continue;
            }
            $clean[$key] = $value;
        }

        $this->markServiceDataStaleIfBound();

        return $this->setConfig('active_filters', $clean);
    }

    public function setFilter(string $name, mixed $value): static
    {
        $active = $this->getActiveFilters();
        if ($value === null || $value === '' || $value === [] || $value === false) {
            unset($active[$name]);
        } else {
            $active[$name] = $value;
        }

        $this->markServiceDataStaleIfBound();

        return $this->setConfig('active_filters', $active);
    }

    /**
     * @return array<string, mixed>
     */
    public function getActiveFilters(): array
    {
        $raw = $this->config['active_filters'] ?? [];
        if (! is_array($raw)) {
            return [];
        }

        /** @var array<string, mixed> $result */
        $result = [];
        foreach ($raw as $key => $val) {
            if (is_string($key) && $key !== '') {
                $result[$key] = $val;
            }
        }

        return $result;
    }

    // ─── Service Binding & Event-Driven Querying (POST /api/ui-event) ─────────

    /**
     * Override the Screen action invoked for query events (search, sort, filter, paginate).
     *
     * @param  array<string, mixed>  $parameters
     */
    public function queryAction(?string $action, array $parameters = []): static
    {
        $this->setConfig('query_action', $action);
        if ($parameters !== []) {
            $this->setConfig('parameters', $parameters);
        }

        return $this;
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    public function parameters(array $parameters): static
    {
        return $this->setConfig('parameters', $parameters);
    }

    public function loading(bool $loading = true): static
    {
        return $this->setConfig('loading', $loading);
    }

    /**
     * Bind a CardListable (or Listable) service and auto-wire the server-side query event.
     *
     * @param  object|class-string  $service
     */
    public function service(object|string $service): static
    {
        $instance = $this->resolveServiceArgument($service);

        if (! self::implementsSupportedContract($instance)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'CardGrid service [%s] must implement %s or %s.',
                    $instance::class,
                    CardListable::class,
                    Listable::class
                )
            );
        }

        $this->serviceInstance = $instance;
        $this->setConfig('service', $instance::class);
        $this->setConfig('query_action', $this->serviceActionFor('query'));

        if (! $this->itemsManuallySet && $this->cards === []) {
            $this->serviceDataStale = true;
        }

        return $this;
    }

    public function getService(): ?object
    {
        if ($this->serviceInstance !== null) {
            return $this->serviceInstance;
        }

        $serviceClass = $this->config['service'] ?? null;
        if (! is_string($serviceClass) || trim($serviceClass) === '') {
            return null;
        }

        if (! class_exists($serviceClass) && ! app()->bound($serviceClass)) {
            return null;
        }

        $resolved = app($serviceClass);
        if (! is_object($resolved) || ! self::implementsSupportedContract($resolved)) {
            return null;
        }

        $this->serviceInstance = $resolved;

        return $this->serviceInstance;
    }

    /**
     * Re-query the bound service (or static items) with current search/sort/filter/page state
     * and synchronize child ItemCard components so ComponentDiffer emits minimal diffs.
     *
     * @param  array<string, mixed>  $extraContext
     */
    public function reload(array $extraContext = []): static
    {
        $query = $this->buildQueryFromCurrentState($extraContext);
        $service = $this->getService();

        if ($service !== null) {
            $result = $this->executeServiceQuery($service, $query);
            $this->syncCardsFromPageResult($result, append: false);
        } elseif (is_array($this->config['static_items'] ?? null)) {
            $result = $this->executeStaticQuery($query);
            $this->syncCardsFromPageResult($result, append: false);
        }

        return $this;
    }

    public function serviceActionFor(string $operation = 'query'): string
    {
        $target = $this->name !== null && trim($this->name) !== ''
            ? trim($this->name)
            : (string) $this->id;

        return self::SERVICE_ACTION_PREFIX.$target.':'.$operation;
    }

    /**
     * @return array{target: string, operation: string}|null
     */
    public static function parseServiceAction(string $action): ?array
    {
        if (! str_starts_with($action, self::SERVICE_ACTION_PREFIX)) {
            return null;
        }

        $rest = substr($action, strlen(self::SERVICE_ACTION_PREFIX));
        $lastColon = strrpos($rest, ':');
        if ($lastColon === false || $lastColon === 0) {
            return null;
        }

        $target = substr($rest, 0, $lastColon);
        $operation = substr($rest, $lastColon + 1);
        if ($target === '' || $operation === '') {
            return null;
        }

        return [
            'target' => $target,
            'operation' => $operation,
        ];
    }

    public function supportsServiceOperation(string $operation): bool
    {
        if ($operation !== 'query') {
            return false;
        }

        return $this->getService() !== null || is_array($this->config['static_items'] ?? null);
    }

    /**
     * Handle an internal CardGrid service event dispatched via POST /api/ui-event.
     * Updates query state on the component and synchronizes child ItemCard components
     * so ComponentDiffer returns exact ADD / UPDATE / DELETE diffs.
     *
     * @param  array<string, mixed>  $parameters
     */
    public function handleServiceAction(string $operation, array $parameters = [], ?Screen $screen = null): void
    {
        if ($operation !== 'query') {
            throw new \BadMethodCallException("Unsupported CardGrid service operation [{$operation}].");
        }

        $append = (bool) ($parameters['append'] ?? false);
        $pagination = $this->getPagination();

        if (array_key_exists('search', $parameters)) {
            $rawSearch = $parameters['search'];
            $searchVal = is_scalar($rawSearch) ? trim((string) $rawSearch) : '';
            $this->setConfig('search_term', $searchVal === '' ? null : $searchVal);
        }

        if (array_key_exists('sort_by', $parameters)) {
            $rawSort = $parameters['sort_by'];
            $this->setConfig('sort_by', is_string($rawSort) && trim($rawSort) !== '' ? trim($rawSort) : null);
        }

        if (array_key_exists('sort_direction', $parameters)) {
            $rawDir = $parameters['sort_direction'];
            $this->setConfig('sort_direction', is_string($rawDir) && strtolower(trim($rawDir)) === 'desc' ? 'desc' : 'asc');
        }

        if (array_key_exists('filters', $parameters)) {
            $rawFilters = $parameters['filters'];
            if (is_string($rawFilters) && trim($rawFilters) !== '') {
                $decoded = json_decode($rawFilters, true);
                $rawFilters = is_array($decoded) ? $decoded : [];
            }

            /** @var array<string, mixed> $cleanFilters */
            $cleanFilters = [];
            if (is_array($rawFilters)) {
                foreach ($rawFilters as $k => $v) {
                    if (is_string($k) && $k !== '' && $v !== null && $v !== '' && $v !== [] && $v !== false) {
                        $cleanFilters[$k] = $v;
                    }
                }
            }
            $this->setConfig('active_filters', $cleanFilters);
        }

        if (array_key_exists('per_page', $parameters) && is_numeric($parameters['per_page'])) {
            $pagination['per_page'] = max(1, min(200, (int) $parameters['per_page']));
        }

        if (array_key_exists('page', $parameters) && is_numeric($parameters['page'])) {
            $pagination['page'] = max(1, (int) $parameters['page']);
        }

        $this->setConfig('pagination', $pagination);

        $query = $this->buildQueryFromCurrentState();
        $service = $this->getService();

        if ($service !== null) {
            $result = $this->executeServiceQuery($service, $query);
        } else {
            $result = $this->executeStaticQuery($query);
        }

        $this->syncCardsFromPageResult($result, append: $append);
    }

    // ─── Layout & Child Card Presentation ─────────────────────────────────────

    public function minCardWidth(Size|int|string $width): static
    {
        $value = trim((string) Size::from($width));

        return $this->setConfig('min_card_width', $value !== '' ? $value : '280px');
    }

    public function maxColumns(?int $columns): static
    {
        return $this->setConfig('max_columns', $columns !== null ? max(1, $columns) : null);
    }

    public function gap(Size|int|string $gap): static
    {
        $value = trim((string) Size::from($gap));

        return $this->setConfig('gap', $value !== '' ? $value : '16px');
    }

    public function cardLayout(string $layout): static
    {
        $normalized = strtolower(trim($layout)) === 'horizontal' ? 'horizontal' : 'vertical';
        $this->setConfig('card_layout', $normalized);
        $this->propagatePresentationToCards();

        return $this;
    }

    public function variant(string $variant): static
    {
        $normalized = match (strtolower(trim($variant))) {
            'outlined' => 'outlined',
            'flat' => 'flat',
            default => 'elevated',
        };
        $this->setConfig('variant', $normalized);
        $this->propagatePresentationToCards();

        return $this;
    }

    public function density(string $density): static
    {
        $normalized = match (strtolower(trim($density))) {
            'compact' => 'compact',
            'spacious' => 'spacious',
            default => 'comfortable',
        };
        $this->setConfig('density', $normalized);
        $this->propagatePresentationToCards();

        return $this;
    }

    public function imageAspectRatio(string $ratio): static
    {
        $value = trim($ratio) !== '' ? trim($ratio) : '16/9';
        $this->setConfig('image_aspect_ratio', $value);

        return $this;
    }

    /**
     * Resolve relative card image filenames from the configured USIM upload directory
     * for all child ItemCards in this grid (parity with `UI::image()->storage(...)`).
     */
    public function imageStorage(?string $directory): static
    {
        $normalized = ImageSourceResolver::normalizeStorageDirectory($directory);
        if ($normalized === null) {
            unset($this->config['_storage_path']);
        } else {
            $this->config['_storage_path'] = $normalized;
        }

        $this->propagateImageConfigToCards();

        return $this;
    }

    /**
     * Enable or configure automatic placeholder generation for child ItemCards
     * lacking an `image` (parity with `UI::image()` placeholder pipeline).
     */
    public function imagePlaceholder(
        bool $enabled = true,
        AspectRatio|string|null $aspect = null,
        ?string $backgroundColor = null,
        ?string $textColor = null
    ): static {
        $placeholder = $this->getImagePlaceholderConfig();
        $placeholder['enabled'] = $enabled;

        if ($aspect !== null) {
            $placeholder['aspect'] = ImageSourceResolver::normalizeAspectRatio($aspect);
        }
        if ($backgroundColor !== null) {
            $placeholder['background_color'] = trim($backgroundColor) !== '' ? trim($backgroundColor) : null;
        }
        if ($textColor !== null) {
            $placeholder['text_color'] = trim($textColor) !== '' ? trim($textColor) : null;
        }

        $this->config['_image_placeholder'] = $placeholder;
        $this->propagateImageConfigToCards();

        return $this;
    }

    public function imagePlaceholderAspect(AspectRatio|string|null $aspectRatio): static
    {
        $placeholder = $this->getImagePlaceholderConfig();
        $placeholder['enabled'] = true;
        $placeholder['aspect'] = ImageSourceResolver::normalizeAspectRatio($aspectRatio);
        $this->config['_image_placeholder'] = $placeholder;
        $this->propagateImageConfigToCards();

        return $this;
    }

    public function imagePlaceholderColors(?string $background, ?string $text): static
    {
        $placeholder = $this->getImagePlaceholderConfig();
        $placeholder['enabled'] = true;
        $placeholder['background_color'] = $background !== null && trim($background) !== '' ? trim($background) : null;
        $placeholder['text_color'] = $text !== null && trim($text) !== '' ? trim($text) : null;
        $this->config['_image_placeholder'] = $placeholder;
        $this->propagateImageConfigToCards();

        return $this;
    }

    public function maxVisibleActions(int $max): static
    {
        $this->setConfig('max_visible_actions', max(0, min(5, $max)));
        $this->propagatePresentationToCards();

        return $this;
    }

    public function maxVisibleTags(int $max): static
    {
        $this->setConfig('max_visible_tags', max(0, min(10, $max)));
        $this->propagatePresentationToCards();

        return $this;
    }

    public function skeletonCount(int $count): static
    {
        return $this->setConfig('skeleton_count', max(1, min(24, $count)));
    }

    public function showLayoutToggle(bool $show = true): static
    {
        return $this->setConfig('show_layout_toggle', $show);
    }

    // ─── Events & Messages ────────────────────────────────────────────────────

    /**
     * @param  string|array<string, mixed>|null  $action
     * @param  array<string, mixed>  $parameters
     */
    public function onCardClick(string|array|null $action, array $parameters = []): static
    {
        if ($action === null || $action === '') {
            return $this->setConfig('on_card_click', null);
        }

        if (is_array($action)) {
            $actionName = is_string($action['action'] ?? null) ? $action['action'] : '';
            $actionParams = is_array($action['parameters'] ?? null) ? $action['parameters'] : $parameters;

            return $this->setConfig('on_card_click', [
                'action' => $actionName,
                'parameters' => $actionParams,
            ]);
        }

        return $this->setConfig('on_card_click', [
            'action' => $action,
            'parameters' => $parameters,
        ]);
    }

    public function disabled(bool $disabled = true): static
    {
        return $this->setConfig('disabled', $disabled);
    }

    public function emptyMessage(string $message): static
    {
        return $this->setConfig('empty_message', $message);
    }

    public function noResultsMessage(string $message): static
    {
        return $this->setConfig('no_results_message', $message);
    }

    public function minCharsMessage(string $message): static
    {
        return $this->setConfig('min_chars_message', $message);
    }

    public function loadMoreLabel(string $label): static
    {
        return $this->setConfig('load_more_label', $label);
    }

    public function clearFiltersLabel(string $label): static
    {
        return $this->setConfig('clear_filters_label', $label);
    }

    // ─── Normalization & Internal Sync ────────────────────────────────────────

    /**
     * @param  Collection<covariant int|string, covariant mixed>|array<int|string, mixed>  $items
     * @return list<array<string, mixed>>
     */
    public static function normalizeItems(Collection|array $items): array
    {
        $raw = $items instanceof Collection ? $items->all() : $items;
        $normalized = [];
        $index = 0;

        foreach ($raw as $key => $item) {
            $fallbackId = is_int($key) ? $index : $key;
            $normalized[] = self::normalizeSingleItem($item, $fallbackId);
            $index++;
        }

        return $normalized;
    }

    /**
     * @return array<string, mixed>
     */
    public static function normalizeSingleItem(mixed $item, int|string $fallbackId = 0): array
    {
        if ($item instanceof CardItemDTO) {
            return $item->toArray();
        }

        if ($item instanceof ListItemDTO) {
            return CardItemDTO::fromListItemDTO($item)->toArray();
        }

        if ($item instanceof Arrayable) {
            /** @var array<string, mixed> $arr */
            $arr = $item->toArray();

            return CardItemDTO::fromArray($arr, $fallbackId)->toArray();
        }

        if (is_array($item)) {
            /** @var array<string, mixed> $item */
            return CardItemDTO::fromArray($item, $fallbackId)->toArray();
        }

        $title = is_scalar($item) ? (string) $item : (string) $fallbackId;

        return CardItemDTO::make(id: $fallbackId, title: $title)->toArray();
    }

    /**
     * Synchronize child ItemCard components against a CardPageResult.
     *
     * - Existing ItemCards matching an item ID are reused (preserving deterministic IDs)
     * - Detached ItemCards are excluded from `$this->cards` so ComponentDiffer emits `parent: null`
     * - In `append: true` (`load_more`), existing cards stay untouched (zero diff) and only new cards are added
     */
    private function syncCardsFromPageResult(CardPageResult $result, bool $append = false): void
    {
        $this->serviceDataStale = false;

        if ($append) {
            $nextCards = $this->cards;
            $position = count($nextCards);

            foreach ($result->items as $dto) {
                $key = (string) $dto->id;
                if (isset($nextCards[$key])) {
                    continue;
                }

                $card = $this->instantiateChildCard($key);
                $this->hydrateChildCard($card, $dto, $position);
                $nextCards[$key] = $card;
                $position++;
            }

            $this->cards = $nextCards;
        } else {
            /** @var array<string, ItemCard> $nextCards */
            $nextCards = [];

            foreach ($result->items as $pos => $dto) {
                $key = (string) $dto->id;
                $card = $this->cards[$key] ?? $this->instantiateChildCard($key);
                $this->hydrateChildCard($card, $dto, $pos);
                $nextCards[$key] = $card;
            }

            // Detach cards that are no longer on the active page
            foreach ($this->cards as $oldKey => $oldCard) {
                if (! isset($nextCards[$oldKey])) {
                    $oldCard->setParent(null);
                }
            }

            $this->cards = $nextCards;
        }

        $this->setConfig('visible_count', count($this->cards));

        $payload = $result->toArray();
        $pagination = array_merge($this->getPagination(), $payload['pagination']);
        $this->setConfig('pagination', $pagination);
    }

    private function instantiateChildCard(string $itemKey): ItemCard
    {
        $gridTarget = $this->name !== null && trim($this->name) !== ''
            ? trim($this->name)
            : (string) $this->id;
        $safeKey = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $itemKey) ?: $itemKey;

        $card = new ItemCard("{$gridTarget}__card_{$safeKey}");
        $card->setParent($this->id);

        return $card;
    }

    private function hydrateChildCard(ItemCard $card, CardItemDTO $dto, int $position): void
    {
        [$defaultClickAction, $defaultClickParams] = $this->resolveDefaultClickConfig();
        $aspectRatio = is_string($this->config['image_aspect_ratio'] ?? null)
            ? $this->config['image_aspect_ratio']
            : '16/9';

        $card->setParent($this->id);
        $card->cardOrder($position);
        $this->applyInheritedImageConfigToCard($card);
        $card->applyFromDTO($dto, $defaultClickAction, $defaultClickParams, $aspectRatio);
        $this->applyGridPresentationToCard($card);
    }

    private function applyInheritedImageConfigToCard(ItemCard $card): void
    {
        $storagePath = is_string($this->config['_storage_path'] ?? null) && $this->config['_storage_path'] !== ''
            ? $this->config['_storage_path']
            : null;
        $placeholder = is_array($this->config['_image_placeholder'] ?? null)
            ? $this->getImagePlaceholderConfig()
            : null;

        $card->applyInheritedImageConfig($storagePath, $placeholder);
    }

    private function propagateImageConfigToCards(): void
    {
        foreach ($this->cards as $card) {
            $this->applyInheritedImageConfigToCard($card);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function getImagePlaceholderConfig(): array
    {
        $raw = $this->config['_image_placeholder'] ?? null;

        return is_array($raw) ? $raw : [];
    }

    /**
     * @param  CardItemDTO|array<string, mixed>  $changes
     */
    private function syncStaticItemPatch(int|string $id, CardItemDTO|array $changes): void
    {
        $raw = $this->config['static_items'] ?? null;
        if (! is_array($raw)) {
            return;
        }

        $targetKey = (string) $id;
        $updated = false;

        foreach ($raw as $index => $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $entryId = $entry['id'] ?? null;
            if (! is_int($entryId) && ! is_string($entryId)) {
                continue;
            }
            if ((string) $entryId !== $targetKey) {
                continue;
            }

            if ($changes instanceof CardItemDTO) {
                $raw[$index] = $changes->toArray();
            } else {
                /** @var array<string, mixed> $entry */
                $raw[$index] = self::applyPatchToStaticEntry($entry, $changes, $id);
            }
            $updated = true;
            break;
        }

        if ($updated) {
            $this->config['static_items'] = array_values($raw);
        }
    }

    private function syncStaticItemRemove(string $key): bool
    {
        $raw = $this->config['static_items'] ?? null;
        if (! is_array($raw)) {
            return false;
        }

        $removed = false;
        $remaining = [];

        foreach ($raw as $entry) {
            if (is_array($entry) && (is_int($entry['id'] ?? null) || is_string($entry['id'] ?? null)) && (string) $entry['id'] === $key) {
                $removed = true;

                continue;
            }
            $remaining[] = $entry;
        }

        if ($removed) {
            $this->config['static_items'] = $remaining;
        }

        return $removed;
    }

    private function syncStaticItemPrepend(CardItemDTO $dto): bool
    {
        $raw = $this->config['static_items'] ?? null;
        if (! is_array($raw)) {
            return false;
        }

        $key = (string) $dto->id;
        $existed = false;
        $remaining = [];

        foreach ($raw as $entry) {
            if (is_array($entry) && (is_int($entry['id'] ?? null) || is_string($entry['id'] ?? null)) && (string) $entry['id'] === $key) {
                $existed = true;

                continue;
            }
            $remaining[] = $entry;
        }

        array_unshift($remaining, $dto->toArray());
        $this->config['static_items'] = $remaining;

        return $existed;
    }

    /**
     * @param  array<string, mixed>  $entry
     * @param  array<string, mixed>  $changes
     * @return array<string, mixed>
     */
    private static function applyPatchToStaticEntry(array $entry, array $changes, int|string $id): array
    {
        $keyMap = [
            'badgeStyle' => 'badge_style',
            'metricLabel' => 'metric_label',
            'metricStyle' => 'metric_style',
            'imageAlt' => 'image_alt',
            'imageFit' => 'image_fit',
            'imageStorage' => 'image_storage',
            'imagePlaceholder' => 'image_placeholder',
            'imagePlaceholderAspect' => 'image_placeholder_aspect',
            'imagePlaceholderId' => 'image_placeholder_id',
            'imagePlaceholderBackground' => 'image_placeholder_background',
            'imagePlaceholderText' => 'image_placeholder_text',
            'aspectRatio' => 'aspect_ratio',
            'footerText' => 'footer_text',
            'clickAction' => 'click_action',
            'clickParameters' => 'click_parameters',
            'accentColor' => 'accent_color',
        ];

        $merged = $entry;
        foreach ($changes as $rawKey => $value) {
            $key = $keyMap[$rawKey] ?? $rawKey;
            if ($value === null) {
                unset($merged[$key]);

                continue;
            }
            if ($value instanceof Style) {
                $merged[$key] = $value->value;

                continue;
            }
            if ($value instanceof AspectRatio) {
                $merged[$key] = $value->value;

                continue;
            }
            $merged[$key] = $value;
        }

        return CardItemDTO::fromArray($merged, $id)->toArray();
    }

    private static function resolveStyle(Style|string $style, Style $fallback = Style::DEFAULT): string
    {
        if ($style instanceof Style) {
            return $style->value;
        }

        $parsed = Style::tryFrom(strtolower(trim($style)));

        return $parsed !== null ? $parsed->value : $fallback->value;
    }

    private function applyGridPresentationToCard(ItemCard $card): void
    {
        $layout = is_string($this->config['card_layout'] ?? null) ? $this->config['card_layout'] : 'vertical';
        $variant = is_string($this->config['variant'] ?? null) ? $this->config['variant'] : 'elevated';
        $density = is_string($this->config['density'] ?? null) ? $this->config['density'] : 'comfortable';
        $maxActions = is_numeric($this->config['max_visible_actions'] ?? null) ? (int) $this->config['max_visible_actions'] : 2;
        $maxTags = is_numeric($this->config['max_visible_tags'] ?? null) ? (int) $this->config['max_visible_tags'] : 3;

        $card
            ->layout($layout)
            ->variant($variant)
            ->density($density)
            ->maxVisibleActions($maxActions)
            ->maxVisibleTags($maxTags);
    }

    private function propagatePresentationToCards(): void
    {
        foreach ($this->cards as $card) {
            $this->applyGridPresentationToCard($card);
        }
    }

    private function reindexCardPositions(): void
    {
        $ordered = array_values($this->cards);
        usort($ordered, static fn (ItemCard $a, ItemCard $b): int => $a->getCardOrder() <=> $b->getCardOrder());

        $reindexed = [];
        foreach ($ordered as $idx => $card) {
            $card->cardOrder($idx);
            $reindexed[$card->getItemKey()] = $card;
        }
        $this->cards = $reindexed;
    }

    /**
     * @return array{0: ?string, 1: array<string, mixed>}
     */
    private function resolveDefaultClickConfig(): array
    {
        $raw = $this->config['on_card_click'] ?? null;
        if (is_string($raw) && trim($raw) !== '') {
            return [trim($raw), []];
        }
        if (is_array($raw)) {
            $action = is_string($raw['action'] ?? null) && trim($raw['action']) !== '' ? trim($raw['action']) : null;
            /** @var array<string, mixed> $params */
            $params = is_array($raw['parameters'] ?? null) ? $raw['parameters'] : [];

            return [$action, $params];
        }

        return [null, []];
    }

    /**
     * @param  array<string, mixed>  $filter
     */
    private function appendFilter(array $filter): static
    {
        $normalized = self::normalizeFilterDefinition($filter);
        if ($normalized === null) {
            return $this;
        }

        $current = is_array($this->config['filters'] ?? null) ? $this->config['filters'] : [];
        $current[] = $normalized;
        $this->setConfig('filters', array_values($current));

        if (
            array_key_exists('default', $normalized)
            && $normalized['default'] !== null
            && $normalized['default'] !== ''
            && $normalized['default'] !== []
        ) {
            $this->setFilter($normalized['name'], $normalized['default']);
        }

        return $this;
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array{
     *   name: string,
     *   label: string,
     *   type: 'select'|'multi_select'|'toggle'|'chips',
     *   placeholder?: string,
     *   options?: list<array{value: string, label: string}>,
     *   default?: mixed
     * }|null
     */
    private static function normalizeFilterDefinition(array $raw): ?array
    {
        $rawName = $raw['name'] ?? '';
        $name = is_scalar($rawName) ? trim((string) $rawName) : '';
        if ($name === '') {
            return null;
        }

        $rawLabel = $raw['label'] ?? $name;
        $label = is_scalar($rawLabel) && trim((string) $rawLabel) !== '' ? trim((string) $rawLabel) : $name;

        $rawType = is_string($raw['type'] ?? null) ? strtolower(trim($raw['type'])) : 'select';
        $type = match ($rawType) {
            'multi_select', 'multiselect', 'multiple' => 'multi_select',
            'toggle', 'boolean', 'bool' => 'toggle',
            'chips', 'chip', 'pills' => 'chips',
            default => 'select',
        };

        $normalized = [
            'name' => $name,
            'label' => $label,
            'type' => $type,
        ];

        if (isset($raw['placeholder']) && is_scalar($raw['placeholder']) && trim((string) $raw['placeholder']) !== '') {
            $normalized['placeholder'] = trim((string) $raw['placeholder']);
        }

        if ($type !== 'toggle') {
            $rawOptions = isset($raw['options']) && is_array($raw['options']) ? $raw['options'] : [];
            $normalized['options'] = self::normalizeFilterOptions($rawOptions);
        }

        if (array_key_exists('default', $raw)) {
            $normalized['default'] = $raw['default'];
        }

        return $normalized;
    }

    /**
     * @param  array<int|string, mixed>  $rawOptions
     * @return list<array{value: string, label: string}>
     */
    private static function normalizeFilterOptions(array $rawOptions): array
    {
        $options = [];

        foreach ($rawOptions as $key => $entry) {
            if (is_array($entry)) {
                $rawVal = $entry['value'] ?? $entry['id'] ?? (is_string($key) ? $key : '');
                $rawLbl = $entry['label'] ?? $entry['name'] ?? $rawVal;
                $val = is_scalar($rawVal) ? (string) $rawVal : '';
                $lbl = is_scalar($rawLbl) ? (string) $rawLbl : $val;
                if ($val !== '' || $lbl !== '') {
                    $options[] = ['value' => $val, 'label' => $lbl];
                }
            } elseif (is_string($key) && is_scalar($entry)) {
                $options[] = ['value' => $key, 'label' => (string) $entry];
            } elseif (is_scalar($entry)) {
                $str = (string) $entry;
                $options[] = ['value' => $str, 'label' => $str];
            }
        }

        return $options;
    }

    /**
     * @param  array<string, mixed>  $extraContext
     */
    private function buildQueryFromCurrentState(array $extraContext = []): CardQuery
    {
        $pagination = $this->getPagination();
        $sortBy = is_string($this->config['sort_by'] ?? null) ? $this->config['sort_by'] : null;
        $sortDir = is_string($this->config['sort_direction'] ?? null) && $this->config['sort_direction'] === 'desc'
            ? 'desc'
            : 'asc';

        /** @var array<string, mixed> $context */
        $context = array_merge($this->getServiceContext(), $extraContext);

        return new CardQuery(
            search: (string) ($this->getSearchTerm() ?? ''),
            page: $pagination['page'],
            perPage: $pagination['per_page'],
            sortBy: $sortBy,
            sortDirection: $sortDir,
            filters: $this->getActiveFilters(),
            context: $context,
        );
    }

    private function executeServiceQuery(object $service, CardQuery $query): CardPageResult
    {
        if ($service instanceof CardListable) {
            return $service->query($query);
        }

        if ($service instanceof Listable) {
            $context = array_merge($query->context, [
                'page' => $query->page,
                'per_page' => $query->perPage,
                'sort_by' => $query->sortBy,
                'sort_direction' => $query->sortDirection,
                'filters' => $query->filters,
            ]);

            $items = $service->list($query->search, $context);

            return CardPageResult::fromCollection($items, $query);
        }

        throw new \BadMethodCallException('CardGrid service does not implement CardListable or Listable.');
    }

    private function executeStaticQuery(CardQuery $query): CardPageResult
    {
        $raw = $this->config['static_items'] ?? [];
        if (! is_array($raw)) {
            return CardPageResult::make([], 0, $query->page, $query->perPage);
        }

        /** @var list<array<string, mixed>> $items */
        $items = array_values(array_filter($raw, static fn (mixed $v): bool => is_array($v)));

        $term = mb_strtolower(trim($query->search));
        if ($term !== '') {
            $items = array_values(array_filter($items, static function (array $item) use ($term): bool {
                $parts = [
                    is_scalar($item['title'] ?? null) ? (string) $item['title'] : '',
                    is_scalar($item['subtitle'] ?? null) ? (string) $item['subtitle'] : '',
                    is_scalar($item['eyebrow'] ?? null) ? (string) $item['eyebrow'] : '',
                    is_scalar($item['description'] ?? null) ? (string) $item['description'] : '',
                    is_scalar($item['badge'] ?? null) ? (string) $item['badge'] : '',
                ];
                if (isset($item['tags']) && is_array($item['tags'])) {
                    foreach ($item['tags'] as $t) {
                        if (is_scalar($t)) {
                            $parts[] = (string) $t;
                        }
                    }
                }

                return str_contains(mb_strtolower(implode(' ', $parts)), $term);
            }));
        }

        foreach ($query->filters as $filterKey => $filterVal) {
            $items = array_values(array_filter($items, static function (array $item) use ($filterKey, $filterVal): bool {
                $meta = is_array($item['meta'] ?? null) ? $item['meta'] : [];
                $candidate = array_key_exists($filterKey, $meta) ? $meta[$filterKey] : ($item[$filterKey] ?? null);

                if (is_array($filterVal)) {
                    $wanted = array_map(static fn (mixed $v): string => is_scalar($v) ? (string) $v : '', $filterVal);
                    if (is_array($candidate)) {
                        $actual = array_map(static fn (mixed $v): string => is_scalar($v) ? (string) $v : '', $candidate);

                        return count(array_intersect($actual, $wanted)) > 0;
                    }

                    return is_scalar($candidate) && in_array((string) $candidate, $wanted, true);
                }

                if (is_bool($filterVal)) {
                    return (bool) $candidate === $filterVal;
                }

                return is_scalar($candidate) && is_scalar($filterVal)
                    && mb_strtolower((string) $candidate) === mb_strtolower((string) $filterVal);
            }));
        }

        if ($query->sortBy !== null) {
            $field = $query->sortBy;
            $dir = $query->sortDirection === 'desc' ? -1 : 1;
            usort($items, static function (array $a, array $b) use ($field, $dir): int {
                $metaA = is_array($a['meta'] ?? null) ? $a['meta'] : [];
                $metaB = is_array($b['meta'] ?? null) ? $b['meta'] : [];
                $va = $a[$field] ?? $metaA[$field] ?? '';
                $vb = $b[$field] ?? $metaB[$field] ?? '';

                if (is_numeric($va) && is_numeric($vb)) {
                    return ((float) $va <=> (float) $vb) * $dir;
                }

                $sa = is_scalar($va) ? (string) $va : '';
                $sb = is_scalar($vb) ? (string) $vb : '';

                return strcasecmp($sa, $sb) * $dir;
            });
        }

        return CardPageResult::fromCollection($items, $query);
    }

    /**
     * @return array<string, mixed>
     */
    private function getServiceContext(): array
    {
        $params = $this->config['parameters'] ?? [];

        return is_array($params) ? $params : [];
    }

    private function hydrateServicePageIfStale(): void
    {
        if (! $this->serviceDataStale || $this->itemsManuallySet) {
            return;
        }

        $service = $this->getService();
        if ($service === null) {
            $this->serviceDataStale = false;

            return;
        }

        $this->serviceDataStale = false;
        $result = $this->executeServiceQuery($service, $this->buildQueryFromCurrentState());
        $this->syncCardsFromPageResult($result, append: false);
    }

    private function markServiceDataStaleIfBound(): void
    {
        if (! $this->itemsManuallySet && $this->serviceInstance !== null) {
            $this->serviceDataStale = true;
        }
    }

    /**
     * @param  object|class-string  $service
     */
    private function resolveServiceArgument(object|string $service): object
    {
        if (is_object($service)) {
            return $service;
        }

        if (! class_exists($service) && ! app()->bound($service)) {
            throw new \InvalidArgumentException("CardGrid service class [{$service}] does not exist.");
        }

        $resolved = app($service);
        if (! is_object($resolved)) {
            throw new \InvalidArgumentException("CardGrid service [{$service}] did not resolve to an object.");
        }

        return $resolved;
    }

    private static function implementsSupportedContract(object $service): bool
    {
        return $service instanceof CardListable || $service instanceof Listable;
    }
}
