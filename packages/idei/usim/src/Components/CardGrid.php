<?php

namespace Idei\Usim\Components;

use Idei\Usim\Contracts\CardGrid\CardListable;
use Idei\Usim\Contracts\ItemList\Listable;
use Idei\Usim\DTOs\CardActionDTO;
use Idei\Usim\DTOs\CardItemDTO;
use Idei\Usim\DTOs\CardPageResult;
use Idei\Usim\DTOs\CardQuery;
use Idei\Usim\DTOs\ListItemDTO;
use Idei\Usim\Enums\Style;
use Idei\Usim\ValueObjects\Size;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;

/**
 * Builder for CardGrid UI components.
 *
 * Responsive 2D card grid component supporting:
 * - Service binding via CardListable (and seamless fallback for Listable)
 * - Asynchronous querying via GET /api/ui-data (search, sort, integrated filters, pagination)
 * - Classic numbered pagination ('pages') and incremental 'load_more' mode
 * - Standardized client-side card rendering with media, icons, badges, metrics, attributes, tags, and actions
 * - Semantic per-card styling compatible with USIM Light/Dark design tokens
 * - Card click and per-card action events dispatched to Screen via POST /api/ui-event
 */
class CardGrid extends UIComponent
{
    public const SERVICE_ACTION_PREFIX = '__usim_card_grid:';

    protected ?object $serviceInstance = null;

    protected bool $serviceDataStale = false;

    protected bool $itemsManuallySet = false;

    /**
     * Surgical patch operations queued during the current event lifecycle.
     *
     * @var list<array<string, mixed>>
     */
    protected array $pendingPatchOps = [];

    protected ?int $currentPatchSeq = null;

    public function __construct(?string $name = null)
    {
        parent::__construct($name);
        $this->type = 'card_grid';
        $this->config['type'] = 'card_grid';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getDefaultConfig(): array
    {
        return [
            // Header
            'title' => null,
            'subtitle' => null,
            'show_count' => true,
            'header_action' => null,

            // Items & Pagination
            'items' => [],
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

            // Remote Data Query (GET /api/ui-data) & Service Binding
            'action' => null,
            'parameters' => [],
            'auto_fetch' => true,
            'reload_token' => 0,
            'card_patches' => null,
            'loading' => false,
            'service' => null,

            // Grid & Standardized Card Appearance
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

            // Events
            'on_card_click' => null,

            // States & Copy
            'disabled' => false,
            'empty_message' => 'No hay elementos para mostrar',
            'no_results_message' => 'No se encontraron resultados con los criterios actuales',
            'min_chars_message' => 'Escribe al menos :count caracteres para buscar...',
            'error_message' => 'No se pudieron cargar los elementos',
            'load_more_label' => 'Cargar más',
            'clear_filters_label' => 'Limpiar filtros',
            'sort_label' => 'Ordenar',
            'filters_label' => 'Filtros',
            'retry_label' => 'Reintentar',
        ];
    }

    // ─── Header ───────────────────────────────────────────────────────────────

    public function title(?string $title): static
    {
        return $this->setConfig('title', $title);
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
     * Configure an optional global CTA button in the grid header (e.g. "+ Nuevo").
     *
     * @param  array<string, mixed>  $parameters
     */
    public function headerAction(
        ?string $label,
        ?string $action = null,
        array $parameters = [],
        ?string $icon = null,
        Style|string $style = Style::PRIMARY
    ): static {
        if ($label === null || trim($label) === '') {
            return $this->setConfig('header_action', null);
        }

        if ($style instanceof Style) {
            $resolvedStyle = $style->value;
        } else {
            $parsedStyle = Style::tryFrom(strtolower(trim($style)));
            $resolvedStyle = $parsedStyle !== null ? $parsedStyle->value : Style::PRIMARY->value;
        }

        return $this->setConfig('header_action', [
            'label' => $label,
            'action' => $action,
            'parameters' => $parameters,
            'icon' => $icon,
            'style' => $resolvedStyle,
        ]);
    }

    // ─── Items & Pagination Result ────────────────────────────────────────────

    /**
     * Set static or initial card items.
     *
     * @param  Collection<covariant int|string, covariant mixed>|array<int|string, mixed>  $items
     */
    public function items(Collection|array $items): static
    {
        $this->itemsManuallySet = true;
        $this->serviceDataStale = false;

        $normalized = self::normalizeItems($items);
        $this->setConfig('items', $normalized);

        $pagination = $this->getPagination();
        $total = count($normalized);
        $perPage = max(1, $pagination['per_page']);
        $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 0;
        $page = min($pagination['page'], max(1, $totalPages));

        $pagination['total'] = $total;
        $pagination['total_pages'] = $totalPages;
        $pagination['page'] = $page;
        $pagination['has_more'] = ($page * $perPage) < $total;
        $pagination['can_prev'] = $page > 1;
        $pagination['can_next'] = $pagination['has_more'];

        return $this->setConfig('pagination', $pagination);
    }

    /**
     * Apply a CardPageResult (items + server pagination metadata) directly to the component.
     */
    public function pageResult(CardPageResult $result): static
    {
        $this->serviceDataStale = false;
        $payload = $result->toArray();
        $this->setConfig('items', $payload['items']);

        $pagination = array_merge($this->getPagination(), $payload['pagination']);

        return $this->setConfig('pagination', $pagination);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getItems(): array
    {
        $this->hydrateServicePageIfStale();

        $raw = $this->config['items'] ?? [];
        if (! is_array($raw)) {
            return [];
        }

        return self::normalizeItems($raw);
    }

    /**
     * {@inheritDoc}
     */
    public function toJson(?int $order = null): array
    {
        $this->hydrateServicePageIfStale();

        return parent::toJson($order);
    }

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

    /**
     * Set number of cards per page.
     */
    public function perPage(int $perPage): static
    {
        $pagination = $this->getPagination();
        $pagination['per_page'] = max(1, min(200, $perPage));
        $this->markServiceDataStaleIfBound();

        return $this->setConfig('pagination', $pagination);
    }

    /**
     * Set selectable page size options (multiples of 12 work best across 1-4 columns).
     *
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
     * Define sortable fields shown in the integrated sort selector.
     *
     * Accepts:
     * - [['field' => 'title', 'label' => 'Título'], ...]
     * - ['title' => 'Título', 'release_year' => 'Año']
     *
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
     * Set declarative filter definitions rendered inside the CardGrid toolbar.
     *
     * Supported types: 'select', 'multi_select', 'toggle', 'chips'.
     *
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
            $this->setConfig('active_filters', $defaults);
        }

        return $this;
    }

    /**
     * Add a single-select dropdown filter to the integrated toolbar.
     *
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
     * Add a multi-select checkbox dropdown filter to the integrated toolbar.
     *
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

    /**
     * Add a boolean toggle pill filter to the integrated toolbar.
     */
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
     * Add a quick-select chips filter row to the integrated toolbar.
     *
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
     * Set active filter values map.
     *
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

    /**
     * Set or clear a single active filter value.
     */
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

    // ─── Remote Data Query & Service Binding ──────────────────────────────────

    /**
     * Set the action used to fetch card pages asynchronously via GET /api/ui-data.
     *
     * @param  array<string, mixed>  $parameters
     */
    public function action(?string $action, array $parameters = []): static
    {
        $this->setConfig('action', $action);
        if ($parameters !== []) {
            $this->setConfig('parameters', $parameters);
        }

        return $this;
    }

    /**
     * Alias for action() matching ItemList API.
     *
     * @param  array<string, mixed>  $parameters
     */
    public function dataAction(?string $action, array $parameters = []): static
    {
        return $this->action($action, $parameters);
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    public function parameters(array $parameters): static
    {
        return $this->setConfig('parameters', $parameters);
    }

    public function autoFetch(bool $autoFetch = true): static
    {
        return $this->setConfig('auto_fetch', $autoFetch);
    }

    /**
     * Trigger a reactive client-side reload of the current card page via GET /api/ui-data.
     */
    public function reload(): static
    {
        $current = $this->config['reload_token'] ?? 0;
        $next = is_int($current) ? $current + 1 : 1;

        return $this->setConfig('reload_token', $next);
    }

    /**
     * Surgically patch a single card on the client without re-fetching GET /api/ui-data
     * or re-rendering other cards.
     *
     * Accepts either a full CardItemDTO (replaces that single card) or a partial
     * associative array of changed fields (e.g. ['badge' => '★ Favorita', 'badge_style' => Style::WARNING]).
     * Explicit null values clear the corresponding optional slot on the client.
     *
     * @param  CardItemDTO|array<string, mixed>  $changes
     */
    public function patchCard(int|string $id, CardItemDTO|array $changes): static
    {
        if ($changes instanceof CardItemDTO) {
            return $this->queuePatchOp([
                'op' => 'replace',
                'id' => $id,
                'data' => $changes->toArray(),
            ]);
        }

        return $this->queuePatchOp([
            'op' => 'patch',
            'id' => $id,
            'data' => self::normalizePartialCardPatch($changes),
        ]);
    }

    /**
     * Surgically remove a single card from the client DOM without re-fetching GET /api/ui-data.
     */
    public function removeCard(int|string $id): static
    {
        return $this->queuePatchOp([
            'op' => 'remove',
            'id' => $id,
        ]);
    }

    /**
     * Surgically prepend a new card to the client grid without re-fetching GET /api/ui-data.
     *
     * @param  CardItemDTO|array<string, mixed>  $card
     */
    public function prependCard(CardItemDTO|array $card): static
    {
        return $this->queuePatchOp([
            'op' => 'prepend',
            'data' => self::normalizeSingleItem($card),
        ]);
    }

    public function loading(bool $loading = true): static
    {
        return $this->setConfig('loading', $loading);
    }

    /**
     * Bind a CardListable (or Listable) service and auto-wire the GET /api/ui-data endpoint.
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

        if ($this->isConfigDefault('action')) {
            $this->action($this->serviceActionFor('query'));
        }

        if (! $this->itemsManuallySet && $this->isConfigDefault('items')) {
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
     * Build the deterministic internal action identifier for a service operation on this CardGrid.
     */
    public function serviceActionFor(string $operation = 'query'): string
    {
        $target = $this->name !== null && trim($this->name) !== ''
            ? trim($this->name)
            : (string) $this->id;

        return self::SERVICE_ACTION_PREFIX.$target.':'.$operation;
    }

    /**
     * Parse an internal CardGrid service action string.
     *
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
        $service = $this->getService();
        if ($service === null) {
            return false;
        }

        return $operation === 'query' && self::implementsSupportedContract($service);
    }

    /**
     * Execute a read-only paginated query against the bound service without mutating component state.
     *
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>
     */
    public function queryServicePage(array $parameters = []): array
    {
        $service = $this->getService();
        if ($service === null) {
            throw new \BadMethodCallException('CardGrid does not have a CardListable or Listable service bound.');
        }

        $pagination = $this->getPagination();
        $defaultSortBy = is_string($this->config['sort_by'] ?? null) ? $this->config['sort_by'] : null;
        $defaultSortDir = is_string($this->config['sort_direction'] ?? null) ? $this->config['sort_direction'] : 'asc';

        $mergedParams = $parameters;
        if (! array_key_exists('filters', $mergedParams) && $this->getActiveFilters() !== []) {
            $mergedParams['filters'] = $this->getActiveFilters();
        }

        $query = CardQuery::fromArray(
            data: $mergedParams,
            defaultPerPage: $pagination['per_page'],
            defaultSortBy: $defaultSortBy,
            defaultSortDirection: $defaultSortDir,
            context: $this->getServiceContext(),
        );

        return $this->executeServiceQuery($service, $query)->toArray();
    }

    /**
     * Refresh the initial items from the bound service and trigger a reactive client reload.
     *
     * @param  array<string, mixed>  $context
     */
    public function refreshFromService(array $context = []): static
    {
        $service = $this->getService();
        if ($service !== null) {
            $result = $this->executeServiceQuery($service, $this->buildQueryFromCurrentState($context));
            $this->pageResult($result);
            $this->reload();
        }

        return $this;
    }

    // ─── Layout & Card Presentation ───────────────────────────────────────────

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

    /**
     * Card orientation: 'vertical' (media top) or 'horizontal' (media left).
     */
    public function cardLayout(string $layout): static
    {
        $normalized = strtolower(trim($layout)) === 'horizontal' ? 'horizontal' : 'vertical';

        return $this->setConfig('card_layout', $normalized);
    }

    /**
     * Card surface variant: 'elevated' | 'outlined' | 'flat'.
     */
    public function variant(string $variant): static
    {
        $normalized = match (strtolower(trim($variant))) {
            'outlined' => 'outlined',
            'flat' => 'flat',
            default => 'elevated',
        };

        return $this->setConfig('variant', $normalized);
    }

    /**
     * Spacing density: 'compact' | 'comfortable' | 'spacious'.
     */
    public function density(string $density): static
    {
        $normalized = match (strtolower(trim($density))) {
            'compact' => 'compact',
            'spacious' => 'spacious',
            default => 'comfortable',
        };

        return $this->setConfig('density', $normalized);
    }

    public function imageAspectRatio(string $ratio): static
    {
        return $this->setConfig('image_aspect_ratio', trim($ratio) !== '' ? trim($ratio) : '16/9');
    }

    public function maxVisibleActions(int $max): static
    {
        return $this->setConfig('max_visible_actions', max(0, min(5, $max)));
    }

    public function maxVisibleTags(int $max): static
    {
        return $this->setConfig('max_visible_tags', max(0, min(10, $max)));
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
     * Register a default Screen event handler when any card is clicked (overridable per CardItemDTO).
     *
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

    // ─── Normalization & Internal Helpers ─────────────────────────────────────

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
        $this->pageResult($result);
    }

    private function markServiceDataStaleIfBound(): void
    {
        if (! $this->itemsManuallySet && $this->serviceInstance !== null) {
            $this->serviceDataStale = true;
        }
    }

    /**
     * Queue a surgical card mutation op into the monotonic `card_patches` diff channel.
     *
     * @param  array<string, mixed>  $op
     */
    private function queuePatchOp(array $op): static
    {
        if ($this->currentPatchSeq === null) {
            $existing = $this->config['card_patches'] ?? null;
            $prevSeq = is_array($existing) && is_numeric($existing['seq'] ?? null)
                ? (int) $existing['seq']
                : 0;
            $this->currentPatchSeq = $prevSeq + 1;
        }

        $this->pendingPatchOps[] = $op;

        return $this->setConfig('card_patches', [
            'seq' => $this->currentPatchSeq,
            'ops' => $this->pendingPatchOps,
        ]);
    }

    /**
     * Normalize a partial card patch payload while preserving explicit null values
     * (so callers can clear optional fields such as badge, metric, or footer_text).
     *
     * @param  array<string, mixed>  $changes
     * @return array<string, mixed>
     */
    private static function normalizePartialCardPatch(array $changes): array
    {
        $keyMap = [
            'badgeStyle' => 'badge_style',
            'metricLabel' => 'metric_label',
            'metricStyle' => 'metric_style',
            'imageAlt' => 'image_alt',
            'imageFit' => 'image_fit',
            'aspectRatio' => 'aspect_ratio',
            'footerText' => 'footer_text',
            'clickAction' => 'click_action',
            'clickParameters' => 'click_parameters',
            'accentColor' => 'accent_color',
        ];

        $styleKeys = ['style', 'badge_style', 'metric_style'];
        $normalized = [];

        foreach ($changes as $rawKey => $value) {
            $key = $keyMap[$rawKey] ?? $rawKey;

            if (in_array($key, $styleKeys, true)) {
                if ($value instanceof Style) {
                    $normalized[$key] = $value->value;
                } elseif (is_string($value)) {
                    $parsed = Style::tryFrom(strtolower(trim($value)));
                    $normalized[$key] = $parsed !== null ? $parsed->value : Style::DEFAULT->value;
                } else {
                    $normalized[$key] = Style::DEFAULT->value;
                }

                continue;
            }

            if ($key === 'actions' && is_array($value)) {
                $actions = [];
                foreach ($value as $entry) {
                    if ($entry instanceof CardActionDTO) {
                        $actions[] = $entry->toArray();
                    } elseif (is_array($entry)) {
                        /** @var array<string, mixed> $entry */
                        $actions[] = CardActionDTO::fromArray($entry)->toArray();
                    }
                }
                $normalized['actions'] = $actions;

                continue;
            }

            $normalized[$key] = $value;
        }

        return $normalized;
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
