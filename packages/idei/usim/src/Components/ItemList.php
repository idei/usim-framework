<?php

namespace Idei\Usim\Components;

use Idei\Usim\Enums\SelectionMode;
use Idei\Usim\ValueObjects\Size;

/**
 * Builder for ItemList UI components.
 *
 * Vertical generic list component supporting:
 * - Single and multiple item selection (SelectionMode::NONE, SINGLE, MULTIPLE)
 * - CRUD operations (inline or delegated to backend modal/events)
 * - Client-side filtering and asynchronous data querying via GET /api/ui-data
 * - Automatic loading spinner and selected items persistence across searches
 * - Native form value collection compatibility with USIM buttons and forms
 */
class ItemList extends UIComponent
{
    public function __construct(?string $name = null)
    {
        parent::__construct($name);
        $this->type = 'item_list';
        $this->config['type'] = 'item_list';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getDefaultConfig(): array
    {
        return [
            'title' => null,
            'label' => null,
            'help_text' => null,
            'error' => null,
            'items' => [],
            'selection_mode' => SelectionMode::NONE->value,
            'selected_items' => [],
            'keep_selected_visible' => true,

            // Search / Filter
            'searchable' => false,
            'search_placeholder' => 'Buscar...',
            'search_term' => null,
            'min_search_chars' => 0,
            'debounce' => 300,

            // Remote Data Query (GET /api/ui-data)
            'action' => null,
            'parameters' => [],
            'auto_fetch' => true,
            'reload_token' => 0,
            'loading' => false,

            // CRUD: Create
            'creatable' => false,
            'create_inline' => true,
            'create_placeholder' => 'Nuevo elemento...',
            'create_button_label' => 'Agregar',

            // CRUD: Update
            'editable' => false,
            'edit_inline' => true,
            'save_button_label' => 'Guardar',
            'cancel_button_label' => 'Cancelar',

            // CRUD: Delete
            'deletable' => false,
            'bulk_deletable' => false,
            'bulk_delete_label' => 'Eliminar seleccionados',

            // States & Appearance
            'disabled' => false,
            'readonly' => false,
            'required' => false,
            'empty_message' => 'No hay elementos para mostrar',
            'no_results_message' => 'No se encontraron resultados',
            'min_chars_message' => 'Escribe al menos :count caracteres para buscar...',
            'max_height' => '420px',
            'min_height' => null,
            'scrollbar' => 'auto',
            'variant' => 'outlined',
            'density' => 'comfortable',
            'show_dividers' => true,
            'show_select_all' => true,

            // Event handlers
            'on_select' => null,
            'on_create' => null,
            'on_update' => null,
            'on_delete' => null,
            'on_bulk_delete' => null,
            'on_search' => null,
        ];
    }

    /**
     * Set header title for the list card/box.
     */
    public function title(?string $title): static
    {
        return $this->setConfig('title', $title);
    }

    /**
     * Set form field label shown above the component.
     */
    public function label(?string $label): static
    {
        return $this->setConfig('label', $label);
    }

    /**
     * Set help tooltip text shown next to the label.
     */
    public function helpText(?string $helpText): static
    {
        return $this->setConfig('help_text', $helpText);
    }

    /**
     * Set or clear validation error message.
     */
    public function error(?string $error): static
    {
        return $this->setConfig('error', $error);
    }

    /**
     * Alias for error() to match Select API.
     */
    public function errorMessage(?string $message): static
    {
        return $this->error($message);
    }

    /**
     * Set the list items.
     *
     * Accepts:
     * - list of associative arrays: [['id' => 1, 'label' => 'Item', 'subtitle' => '...', 'icon' => '...', 'badge' => '...'], ...]
     * - select-style options: [['value' => '1', 'label' => 'Item'], ...]
     * - key-value map: ['1' => 'Item 1', '2' => 'Item 2']
     * - simple scalar list: ['Item 1', 'Item 2']
     *
     * @param  array<int|string, mixed>  $items
     */
    public function items(array $items): static
    {
        return $this->setConfig('items', self::normalizeItems($items));
    }

    /**
     * Alias for items() to match Select API.
     *
     * @param  array<int|string, mixed>  $options
     */
    public function options(array $options): static
    {
        return $this->items($options);
    }

    /**
     * Get the normalized items currently configured on the component.
     *
     * @return list<array<string, mixed>>
     */
    public function getItems(): array
    {
        $raw = $this->config['items'] ?? [];
        if (! is_array($raw)) {
            return [];
        }

        return self::normalizeItems($raw);
    }

    /**
     * Add a single item to the list.
     *
     * @param  array<string, mixed>|string  $item
     */
    public function addItem(array|string $item, bool $prepend = false): static
    {
        $items = $this->getItems();
        $normalized = self::normalizeSingleItem($item, count($items) + 1);

        if ($prepend) {
            array_unshift($items, $normalized);
        } else {
            $items[] = $normalized;
        }

        return $this->setConfig('items', array_values($items));
    }

    /**
     * Update an existing item by its ID.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function updateItem(int|string $id, array $attributes): static
    {
        $targetId = (string) $id;
        $items = $this->getItems();

        foreach ($items as $index => $item) {
            $itemId = isset($item['id']) && (is_int($item['id']) || is_string($item['id']))
                ? (string) $item['id']
                : '';

            if ($itemId === $targetId) {
                $merged = array_merge($item, $attributes);
                $merged['id'] = $item['id'];
                $items[$index] = self::normalizeSingleItem($merged, $index);
                break;
            }
        }

        return $this->setConfig('items', array_values($items));
    }

    /**
     * Remove a single item by its ID and deselect it if selected.
     */
    public function removeItem(int|string $id): static
    {
        return $this->removeItems([$id]);
    }

    /**
     * Remove multiple items by their IDs and deselect them if selected.
     *
     * @param   array<int, int|string>  $ids
     */
    public function removeItems(array $ids): static
    {
        $removeSet = array_map(static fn(int|string $val): string => (string) $val, $ids);
        $items = array_values(array_filter(
            $this->getItems(),
            static function (array $item) use ($removeSet): bool {
                $itemId = isset($item['id']) && (is_int($item['id']) || is_string($item['id']))
                    ? (string) $item['id']
                    : '';

                return ! in_array($itemId, $removeSet, true);
            }
        ));

        $this->setConfig('items', $items);

        $selected = $this->getSelectedIds();
        $remainingSelected = array_values(array_filter(
            $selected,
            static fn(int|string $selId): bool => ! in_array((string) $selId, $removeSet, true)
        ));

        if (count($remainingSelected) !== count($selected)) {
            $this->setConfig('selected_items', $remainingSelected);
        }

        return $this;
    }

    /**
     * Find an item in the current items list by ID.
     *
     * @return array<string, mixed>|null
     */
    public function findItem(int|string $id): ?array
    {
        $targetId = (string) $id;
        foreach ($this->getItems() as $item) {
            $itemId = isset($item['id']) && (is_int($item['id']) || is_string($item['id']))
                ? (string) $item['id']
                : '';
            if ($itemId === $targetId) {
                return $item;
            }
        }

        return null;
    }

    /**
     * Clear all items and reset selection.
     */
    public function clearItems(): static
    {
        $this->setConfig('items', []);
        $this->setConfig('selected_items', []);

        return $this;
    }

    /**
     * Set or get item selection mode.
     *
     * If $mode is null, returns current mode string ('none', 'single', 'multiple').
     *
     * @return ($mode is null ? string : static)
     */
    public function selectionMode(SelectionMode|string|null $mode = null): static|string
    {
        if ($mode === null) {
            $selectionMode = $this->config['selection_mode'] ?? null;

            return is_string($selectionMode)
                ? $selectionMode
                : SelectionMode::NONE->value;
        }

        if (\is_string($mode)) {
            $mode = SelectionMode::tryFrom(strtolower(trim($mode))) ?? SelectionMode::NONE;
        }

        $this->setConfig('selection_mode', $mode->value);

        if ($mode === SelectionMode::NONE) {
            $this->setConfig('selected_items', []);
        } elseif ($mode === SelectionMode::SINGLE) {
            $selected = $this->getSelectedIds();
            if (\count($selected) > 1) {
                $this->setConfig('selected_items', [$selected[0]]);
            }
        }

        return $this;
    }

    /**
     * Shortcut to enable single selection mode.
     */
    public function singleSelect(): static
    {
        return $this->selectionMode(SelectionMode::SINGLE);
    }

    /**
     * Shortcut to enable multiple selection mode.
     */
    public function multipleSelect(bool $showSelectAll = true): static
    {
        $this->selectionMode(SelectionMode::MULTIPLE);

        return $this->showSelectAll($showSelectAll);
    }

    /**
     * Shortcut to disable selection.
     */
    public function noSelection(): static
    {
        return $this->selectionMode(SelectionMode::NONE);
    }

    /**
     * Set or get selected item identifiers based on the current selection mode.
     *
     * When called with no arguments (or null when reading), returns:
     * - null when selection_mode is 'none'
     * - int|string|null in 'single' mode
     * - list<int|string> in 'multiple' mode
     *
     * To clear selection explicitly, use clearSelection() or select([]).
     *
     * @param  array<int, mixed>|string|int|null  $items
     * @return ($items is null ? list<int|string>|string|int|null : static)
     */
    public function select(array|string|int|null $items = null): static|array|string|int|null
    {
        $selected = $this->getSelectedIds();
        $mode = $this->selectionMode();

        if (func_num_args() === 0 || $items === null) {
            if ($mode === SelectionMode::NONE->value) {
                return null;
            }
            if ($mode === SelectionMode::SINGLE->value) {
                return $selected[0] ?? null;
            }

            return $selected;
        }

        if ($mode === SelectionMode::NONE->value) {
            return $this->setConfig('selected_items', []);
        }

        $normalized = self::normalizeSelectedIds($items);
        if ($mode === SelectionMode::SINGLE->value) {
            $normalized = isset($normalized[0]) ? [$normalized[0]] : [];
        }

        return $this->setConfig('selected_items', $normalized);
    }

    /**
     * Get the selected item IDs always as a list.
     *
     * @return list<int|string>
     */
    public function getSelectedIds(): array
    {
        return self::normalizeSelectedIds($this->config['selected_items'] ?? []);
    }

    /**
     * Clear all selected items.
     */
    public function clearSelection(): static
    {
        return $this->setConfig('selected_items', []);
    }

    /**
     * Toggle selection state of a single item ID according to current selection mode.
     */
    public function toggleSelect(int|string $id): static
    {
        $mode = $this->selectionMode();
        if ($mode === SelectionMode::NONE->value) {
            return $this;
        }

        $selected = $this->getSelectedIds();
        $target = (string) $id;
        $exists = false;
        $filtered = [];

        foreach ($selected as $existingId) {
            if ((string) $existingId === $target) {
                $exists = true;
            } else {
                $filtered[] = $existingId;
            }
        }

        if ($mode === SelectionMode::SINGLE->value) {
            return $this->setConfig('selected_items', $exists ? [] : [$id]);
        }

        if (! $exists) {
            $filtered[] = $id;
        }

        return $this->setConfig('selected_items', $filtered);
    }

    /**
     * Set selected value(s) (alias for select() for form component consistency).
     *
     * @param  array<int, mixed>|string|int|null  $value
     */
    public function value(array|string|int|null $value): static
    {
        $normalized = self::normalizeSelectedIds($value);
        $mode = $this->selectionMode();
        if ($mode === SelectionMode::SINGLE->value && count($normalized) > 1) {
            $normalized = [$normalized[0]];
        }

        return $this->setConfig('selected_items', $normalized);
    }

    /**
     * Get current selected value(s) based on selection mode.
     *
     * @return list<int|string>|string|int|null
     */
    public function getValue(): array|string|int|null
    {
        $selected = $this->getSelectedIds();
        $mode = $this->selectionMode();

        if ($mode === SelectionMode::NONE->value) {
            return null;
        }

        if ($mode === SelectionMode::SINGLE->value) {
            return $selected[0] ?? null;
        }

        return $selected;
    }

    /**
     * Keep already-selected items visible/accessible when filtering or querying via search.
     */
    public function keepSelectedVisible(bool $keep = true): static
    {
        return $this->setConfig('keep_selected_visible', $keep);
    }

    /**
     * Show or hide the master "Select All" checkbox in multiple selection mode.
     */
    public function showSelectAll(bool $show = true): static
    {
        return $this->setConfig('show_select_all', $show);
    }

    /**
     * Enable or disable the search/filter input in the list header.
     */
    public function searchable(bool $searchable = true, ?string $placeholder = null): static
    {
        $this->setConfig('searchable', $searchable);
        if ($placeholder !== null) {
            $this->setConfig('search_placeholder', $placeholder);
        }

        return $this;
    }

    /**
     * Set placeholder text for the search input.
     */
    public function searchPlaceholder(string $placeholder): static
    {
        return $this->setConfig('search_placeholder', $placeholder);
    }

    /**
     * Set or clear the current search term.
     */
    public function setSearchTerm(?string $search): static
    {
        $normalized = $search !== null ? trim($search) : null;

        return $this->setConfig('search_term', $normalized === '' ? null : $normalized);
    }

    /**
     * Get the current search term.
     */
    public function getSearchTerm(): ?string
    {
        $term = $this->config['search_term'] ?? null;

        return is_string($term) && $term !== '' ? $term : null;
    }

    /**
     * Set minimum characters required before triggering search.
     */
    public function minSearchChars(int $chars, ?string $message = null): static
    {
        $this->setConfig('min_search_chars', max(0, $chars));
        if ($message !== null) {
            $this->setConfig('min_chars_message', $message);
        }

        return $this;
    }

    /**
     * Set debounce delay in milliseconds for search input queries.
     */
    public function debounce(int $ms): static
    {
        return $this->setConfig('debounce', max(0, $ms));
    }

    /**
     * Set the action to fetch items asynchronously from the server via GET /api/ui-data.
     * Maps to Screen::get<PascalCaseAction>($params).
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
     * Alias for action() to explicitly indicate remote data querying via GET /api/ui-data.
     *
     * @param  array<string, mixed>  $parameters
     */
    public function dataAction(?string $action, array $parameters = []): static
    {
        return $this->action($action, $parameters);
    }

    /**
     * Set parameters to send with the GET /api/ui-data query action request.
     *
     * @param  array<string, mixed>  $parameters
     */
    public function parameters(array $parameters): static
    {
        return $this->setConfig('parameters', $parameters);
    }

    /**
     * Enable or disable automatic data fetching via GET /api/ui-data on initial mount.
     */
    public function autoFetch(bool $autoFetch = true): static
    {
        return $this->setConfig('auto_fetch', $autoFetch);
    }

    /**
     * Trigger a reactive reload of remote items via GET /api/ui-data on the client.
     */
    public function reload(): static
    {
        $current = $this->config['reload_token'] ?? 0;
        $next = is_int($current) ? $current + 1 : 1;

        return $this->setConfig('reload_token', $next);
    }

    /**
     * Set explicit loading state.
     */
    public function loading(bool $loading = true): static
    {
        return $this->setConfig('loading', $loading);
    }

    /**
     * Enable or disable item creation (CRUD: Create).
     *
     * @param  bool  $creatable  Whether creation is enabled
     * @param  bool  $inline  True for inline input in header, false to trigger button event (e.g. open modal)
     * @param  string|null  $placeholder  Placeholder for inline input
     * @param  string|null  $buttonLabel  Label for create button
     */
    public function creatable(
        bool $creatable = true,
        bool $inline = true,
        ?string $placeholder = null,
        ?string $buttonLabel = null
    ): static {
        $this->setConfig('creatable', $creatable);
        $this->setConfig('create_inline', $inline);

        if ($placeholder !== null) {
            $this->setConfig('create_placeholder', $placeholder);
        }

        if ($buttonLabel !== null) {
            $this->setConfig('create_button_label', $buttonLabel);
        }

        return $this;
    }

    /**
     * Enable or disable item editing (CRUD: Update).
     *
     * @param  bool  $editable  Whether editing is enabled
     * @param  bool  $inline  True for inline row editing, false to dispatch event directly (e.g. open modal)
     */
    public function editable(bool $editable = true, bool $inline = true): static
    {
        $this->setConfig('editable', $editable);
        $this->setConfig('edit_inline', $inline);

        return $this;
    }

    /**
     * Enable or disable item deletion (CRUD: Delete).
     *
     * @param  bool  $deletable  Whether per-row deletion is enabled
     * @param  bool  $allowBulk  Whether bulk deletion of selected items is enabled in MULTIPLE mode
     * @param  string|null  $bulkLabel  Custom label for bulk delete button
     */
    public function deletable(
        bool $deletable = true,
        bool $allowBulk = true,
        ?string $bulkLabel = null
    ): static {
        $this->setConfig('deletable', $deletable);
        $this->setConfig('bulk_deletable', $deletable && $allowBulk);

        if ($bulkLabel !== null) {
            $this->setConfig('bulk_delete_label', $bulkLabel);
        }

        return $this;
    }

    /**
     * Shortcut to enable or disable all CRUD operations (Create, Update, Delete) at once.
     */
    public function crud(bool $enabled = true, bool $inline = true): static
    {
        $this->creatable($enabled, $inline);
        $this->editable($enabled, $inline);
        $this->deletable($enabled, $enabled);

        return $this;
    }

    /**
     * Configure inline creation mode without altering creatable flag.
     */
    public function createInline(bool $inline = true): static
    {
        return $this->setConfig('create_inline', $inline);
    }

    /**
     * Set placeholder text for the inline creation input.
     */
    public function createPlaceholder(string $placeholder): static
    {
        return $this->setConfig('create_placeholder', $placeholder);
    }

    /**
     * Set label for the create button.
     */
    public function createButtonLabel(string $label): static
    {
        return $this->setConfig('create_button_label', $label);
    }

    /**
     * Configure inline editing mode without altering editable flag.
     */
    public function editInline(bool $inline = true): static
    {
        return $this->setConfig('edit_inline', $inline);
    }

    /**
     * Enable or disable bulk deletion of selected items.
     */
    public function allowBulkDelete(bool $allow = true): static
    {
        return $this->setConfig('bulk_deletable', $allow);
    }

    /**
     * Set custom label for the bulk delete action button.
     */
    public function bulkDeleteLabel(string $label): static
    {
        return $this->setConfig('bulk_delete_label', $label);
    }

    /**
     * Trigger a backend action when selection changes.
     *
     * @param  array<string, mixed>  $parameters
     */
    public function onSelect(string $action, array $parameters = []): static
    {
        return $this->setConfig('on_select', [
            'action' => $action,
            'parameters' => $parameters,
        ]);
    }

    /**
     * Alias for onSelect() for consistency with Select/Input components.
     *
     * @param  array<string, mixed>  $parameters
     */
    public function onChange(string $action, array $parameters = []): static
    {
        return $this->onSelect($action, $parameters);
    }

    /**
     * Trigger a backend action when creating an item.
     *
     * @param  array<string, mixed>  $parameters
     */
    public function onCreate(string $action, array $parameters = []): static
    {
        if (! ($this->config['creatable'] ?? false)) {
            $this->setConfig('creatable', true);
        }

        return $this->setConfig('on_create', [
            'action' => $action,
            'parameters' => $parameters,
        ]);
    }

    /**
     * Trigger a backend action when updating an item.
     *
     * @param  array<string, mixed>  $parameters
     */
    public function onUpdate(string $action, array $parameters = []): static
    {
        if (! ($this->config['editable'] ?? false)) {
            $this->setConfig('editable', true);
        }

        return $this->setConfig('on_update', [
            'action' => $action,
            'parameters' => $parameters,
        ]);
    }

    /**
     * Alias for onUpdate().
     *
     * @param  array<string, mixed>  $parameters
     */
    public function onEdit(string $action, array $parameters = []): static
    {
        return $this->onUpdate($action, $parameters);
    }

    /**
     * Trigger a backend action when deleting an item.
     *
     * @param  array<string, mixed>  $parameters
     */
    public function onDelete(string $action, array $parameters = []): static
    {
        if (! ($this->config['deletable'] ?? false)) {
            $this->setConfig('deletable', true);
        }

        return $this->setConfig('on_delete', [
            'action' => $action,
            'parameters' => $parameters,
        ]);
    }

    /**
     * Trigger a backend action when bulk-deleting selected items.
     *
     * @param  array<string, mixed>  $parameters
     */
    public function onBulkDelete(string $action, array $parameters = []): static
    {
        $this->setConfig('bulk_deletable', true);

        return $this->setConfig('on_bulk_delete', [
            'action' => $action,
            'parameters' => $parameters,
        ]);
    }

    /**
     * Trigger a backend action via POST /api/ui-event when searching (alternative to GET /api/ui-data).
     *
     * @param  array<string, mixed>  $parameters
     */
    public function onSearch(string $action, array $parameters = []): static
    {
        if (! ($this->config['searchable'] ?? false)) {
            $this->setConfig('searchable', true);
        }

        return $this->setConfig('on_search', [
            'action' => $action,
            'parameters' => $parameters,
        ]);
    }

    /**
     * Set maximum height of the scrollable list body (e.g. '400px', 400, Size::px(400)).
     */
    public function maxHeight(int|string|Size|null $height): static
    {
        if ($height instanceof Size) {
            $height = (string) $height;
        } elseif (is_int($height)) {
            $height = "{$height}px";
        }

        return $this->setConfig('max_height', $height);
    }

    /**
     * Set minimum height of the list body.
     */
    public function minHeight(int|string|Size|null $height): static
    {
        if ($height instanceof Size) {
            $height = (string) $height;
        } elseif (is_int($height)) {
            $height = "{$height}px";
        }

        return $this->setConfig('min_height', $height);
    }

    /**
     * Configure scrollbar mode for the list body ('auto', 'always', 'hidden', or boolean).
     *
     * - 'auto': Show scrollbar automatically when content overflows (default).
     * - 'always' | true: Keep scrollbar always visible (overflow-y: scroll).
     * - 'hidden' | false: Allow scrolling with wheel/touch, but hide visual scrollbar track.
     */
    public function scrollbar(string|bool $mode = 'auto'): static
    {
        return $this->setConfig('scrollbar', $mode);
    }

    /**
     * Always display the scrollbar on the list body.
     */
    public function showScrollbar(): static
    {
        return $this->scrollbar('always');
    }

    /**
     * Hide the visual scrollbar while keeping the list body scrollable.
     */
    public function hideScrollbar(): static
    {
        return $this->scrollbar('hidden');
    }

    /**
     * Set message displayed when the list has no items.
     */
    public function emptyMessage(string $message): static
    {
        return $this->setConfig('empty_message', $message);
    }

    /**
     * Set message displayed when a search filter matches no items.
     */
    public function noResultsMessage(string $message): static
    {
        return $this->setConfig('no_results_message', $message);
    }

    /**
     * Set visual variant: 'outlined', 'card', or 'plain'.
     */
    public function variant(string $variant): static
    {
        return $this->setConfig('variant', $variant);
    }

    /**
     * Set row density: 'compact' or 'comfortable'.
     */
    public function density(string $density): static
    {
        return $this->setConfig('density', $density);
    }

    /**
     * Show or hide divider lines between list rows.
     */
    public function dividers(bool $show = true): static
    {
        return $this->setConfig('show_dividers', $show);
    }

    /**
     * Disable interaction with the component.
     */
    public function disabled(bool $disabled = true): static
    {
        return $this->setConfig('disabled', $disabled);
    }

    /**
     * Set component as read-only (disables CRUD mutations while keeping view/search).
     */
    public function readonly(bool $readonly = true): static
    {
        return $this->setConfig('readonly', $readonly);
    }

    /**
     * Mark selection as required in a form context.
     */
    public function required(bool $required = true): static
    {
        return $this->setConfig('required', $required);
    }

    /**
     * Normalize an arbitrary items array into a standard list of item objects.
     *
     * @param  array<int|string, mixed>  $items
     * @return list<array<string, mixed>>
     */
    public static function normalizeItems(array $items): array
    {
        $normalized = [];
        $isList = array_is_list($items);
        $index = 0;

        foreach ($items as $key => $item) {
            if (! $isList && (is_scalar($item) || $item === null)) {
                $normalized[] = [
                    'id' => $key,
                    'label' => (string) ($item ?? ''),
                ];
            } else {
                $normalized[] = self::normalizeSingleItem($item, $index);
            }
            $index++;
        }

        return $normalized;
    }

    /**
     * Normalize a single item entry.
     *
     * @return array<string, mixed>
     */
    private static function normalizeSingleItem(mixed $item, int $fallbackIndex): array
    {
        if (is_array($item)) {
            $rawId = $item['id'] ?? $item['value'] ?? $item['_model_id'] ?? $fallbackIndex;
            $id = is_int($rawId) || is_string($rawId) ? $rawId : (string) $fallbackIndex;

            $rawLabel = $item['label'] ?? $item['title'] ?? $item['name'] ?? (string) $id;
            $label = is_scalar($rawLabel) ? (string) $rawLabel : (string) $id;

            $normalized = $item;
            $normalized['id'] = $id;
            $normalized['label'] = $label;

            if (isset($item['subtitle']) && is_scalar($item['subtitle'])) {
                $normalized['subtitle'] = (string) $item['subtitle'];
            } elseif (isset($item['description']) && is_scalar($item['description'])) {
                $normalized['subtitle'] = (string) $item['description'];
            }

            return $normalized;
        }

        $scalar = is_scalar($item) ? (string) $item : (string) $fallbackIndex;

        return [
            'id' => $scalar,
            'label' => $scalar,
        ];
    }

    /**
     * Normalize selected IDs into a clean list of int|string.
     *
     * @return list<int|string>
     */
    private static function normalizeSelectedIds(mixed $raw): array
    {
        if ($raw === null || $raw === '' || $raw === false) {
            return [];
        }

        $items = is_array($raw) ? $raw : [$raw];
        $normalized = [];
        $seen = [];

        foreach ($items as $value) {
            if (! is_int($value) && ! is_string($value)) {
                continue;
            }
            if (is_string($value) && trim($value) === '') {
                continue;
            }

            $key = (string) $value;
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $normalized[] = $value;
        }

        return $normalized;
    }
}
