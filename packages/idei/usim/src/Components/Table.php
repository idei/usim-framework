<?php

namespace Idei\Usim\Components;

use Idei\Usim\Components\Container;
use Idei\Usim\Components\TableRow;
use Idei\Usim\Contracts\UIElement;
use Idei\Usim\DataTable\AbstractTableModel;
use Idei\Usim\Enums\SelectionMode;
use Idei\Usim\ValueObjects\Size;

/**
 * Table Builder
 *
 * A table with fixed dimensions (rows × columns) where:
 * - Structure is defined upfront
 * - All cells are initially empty
 * - Cells are identified by (row, col) coordinates
 * - Cell names follow pattern: "{row}_{col}"
 */
class Table extends UIComponent
{
    public const DEFAULT_COLUMN_WIDTH = 160;
    public const DEFAULT_PAGINATION_PER_PAGE = 7;
    public const DEFAULT_ROW_MIN_HEIGHT = 45;
    public const DEFAULT_BODY_HEIGHT = 520;
    public const DEFAULT_BODY_OVERFLOW_X = 'visible';
    public const DEFAULT_BODY_OVERFLOW_Y = 'visible';

    /** @var Container The rows container */
    private Container $rowsContainer;

    /** @var TableHeaderRow|null The header row (optional) */
    private ?TableHeaderRow $headerRow = null;

    /** @var AbstractTableModel|null The data model instance */
    private ?AbstractTableModel $model = null;

    /** @var int Number of data rows (excluding header) */
    private int $rows;

    /** @var int Number of columns */
    private int $cols;

    /** @var array Matrix of cell builders [row][col] */
    private array $cells = [];

    /** @var array Array of row builders */
    private array $rowBuilders = [];

    /** @var array Column width configuration [col => int] */
    private array $columnWidths = [];

    /**
     * Create a new table
     *
     * @param string|null $name Table name
     * @param int $rows Number of data rows (0 for dynamic)
     * @param int $cols Number of columns (0 for dynamic)
     */
    public function __construct(?string $name = null, int $rows = 0, int $cols = 0)
    {
        parent::__construct($name);

        // Create the rows container with a table-scoped name to avoid ID collisions
        // when multiple tables are rendered inside the same screen.
        $this->rowsContainer = new Container($this->internalComponentName('rows'));
        $this->rowsContainer->setParent($this->id);
        $this->config['rows_container'] = $this->rowsContainer->getId();

        $this->rows = $rows;
        $this->cols = $cols;

        $this->setConfig('rows', $rows);
        $this->setConfig('cols', $cols);

        // Initialize empty cells if dimensions are provided
        if ($rows > 0 && $cols > 0) {
            $this->initializeEmptyCells();
        }
    }

    protected function getDefaultConfig(): array
    {
        return [
            'title' => '',
            'header_row' => null,
            'column_widths' => [],
            'pagination' => [
                'enabled' => true,
                'per_page' => self::DEFAULT_PAGINATION_PER_PAGE,
                'current_page' => 1,
                'total_items' => 0,
                'can_next' => true,
                'can_prev' => false,
                'total_pages' => 0,
                'show_controls' => true,
                'labels' => [
                    'previous' => t('usim.table.pagination.previous'),
                    'next' => t('usim.table.pagination.next'),
                    'showing' => t('usim.table.pagination.showing'),
                ],
            ],
            'rows' => 0,
            'cols' => 0,
            'align' => 'left', // Alignment: left, center, right
            'border_radius' => '4px',
            'box_shadow' => null,
            'sort_column' => null,
            'sort_direction' => 'asc', // asc or desc
            'search_term' => null,
            'row_min_height' => self::DEFAULT_ROW_MIN_HEIGHT,
            'body_height' => self::DEFAULT_BODY_HEIGHT,
            'body_min_height' => self::DEFAULT_BODY_HEIGHT,
            'body_max_height' => self::DEFAULT_BODY_HEIGHT,
            'body_overflow_x' => self::DEFAULT_BODY_OVERFLOW_X,
            'body_overflow_y' => self::DEFAULT_BODY_OVERFLOW_Y,
            'selection_mode' => SelectionMode::NONE->value,
            'selected_rows' => [], // Array of selected row indices (single mode uses first element)
        ];
    }

    public function page(?int $page): self
    {
        if ($page === null) {
            $pagination = $this->config['pagination'];
            $page = $pagination['current_page'];
        }
        $pagination = $this->config['pagination'];

        if ($page < 1) {
            $page = 1;
        } elseif ($page > $pagination['total_pages']) {
            $page = $pagination['total_pages'];
        }

        // Update current page BEFORE calling updatePaginationData
        $pagination['current_page'] = $page;
        $this->setConfig('pagination', $pagination);
        $this->updatePaginationData();

        // Update table data for the new page
        $this->updateTableData();

        return $this;
    }

    public function setSearchTerm(string $search): self
    {
        $this->setConfig('search_term', $search === '' ? null : $search);
        $model = $this->getModel();
        if ($model) {
            // Reset to first page and force pagination recalculation after search.
            $pagination = $this->config['pagination'];
            $pagination['current_page'] = 1;
            $this->setConfig('pagination', $pagination);

            $this->updatePaginationData();
            $this->updateTableData();
        }
        return $this;
    }

    public function getSearchTerm(): ?string
    {
        return $this->config['search_term'];
    }

    public function sortedBy(?string $column, ?string $direction = 'asc'): self
    {
        if ($column !== null) {
            $column = strtolower(str_replace(' ', '_', $column));

            // Toggle direction if sorting by the same column
            if ($column === $this->config['sort_column']) {
                $direction = $this->config['sort_direction'] === 'asc' ? 'desc' : 'asc';
            }
        }
        $this->setConfig('sort_column', $column);
        $this->setConfig('sort_direction', $direction);
        return $this;
    }

    public function getSortColumn(): ?string
    {
        return $this->config['sort_column'];
    }

    public function getSortDirection(): ?string
    {
        return $this->config['sort_direction'];
    }

    /**
     * Set or get row selection mode.
     *
     * If $mode is null, returns current mode.
     *
     * @param SelectionMode|null $mode
     * @return static|string
     */
    public function selectionMode(?SelectionMode $mode = null): static|string
    {
        if ($mode === null) {
            return $this->config['selection_mode'];
        }

        return $this->setConfig('selection_mode', $mode->value);
    }

    /**
     * Set or get selected row identifiers based on the current selection mode.
     *
     * If $rows is null, returns the current selection:
     * - null when selection is disabled
     * - a single row identifier in single selection mode
     * - an array of row identifiers in multiple selection mode
     *
     * @param array|string|int|null $rows Row identifier or identifiers to select.
     * @return static|array|string|int|null
     */
    public function select(array|string|int|null $rows = null): static|array|string|int|null
    {
        $selected = $this->config['selected_rows'] ?? [];
        if ($rows === null) {
            if ($this->config['selection_mode'] === SelectionMode::NONE->value) {
                return null;
            }
            if ($this->config['selection_mode'] === SelectionMode::SINGLE->value) {
                $singleSelected = !empty($selected) ? $selected[0] : null;
                return $singleSelected;
            }
            return $selected;
        }

        if (!\is_array($rows)) {
            $rows = [$rows];
        }

        return $this->setConfig('selected_rows', $rows);
    }

    public function refresh(): void
    {
        $this->page(null);
    }

    /**
     * Refresh column definitions (labels and widths) from the data model.
     *
     * Call this after any state change that affects column metadata — for example,
     * when a language filter changes and the selected-language column needs a new
     * label and/or width without reloading the whole table.
     *
     * @return self
     */
    public function refreshColumns(): self
    {
        $model = $this->getModel();
        if (!$model) {
            return $this;
        }

        $columns = $model->getColumns();

        // Update column widths on all existing cells
        $columnIndex = 0;
        $tableWidth = 0;
        foreach ($columns as $column) {
            $width = $this->resolveColumnWidth($column);
            $this->columnWidth($columnIndex, $width);
            $tableWidth += $width;
            $columnIndex++;
        }

        $this->setConfig('width', $tableWidth);

        // Rebuild header row labels and sort actions
        $headerData = [];
        foreach ($columns as $column) {
            $label = is_array($column) ? ($column['label'] ?? '') : (string) $column;
            $sortBy = is_array($column) ? ($column['sort_by'] ?? null) : null;
            $headerData[] = ['label' => $label, 'sort_by' => $sortBy];
        }
        $this->fillHeaderRow($headerData);

        return $this;
    }

    /**
     * Split formatted row data into column values and row-level metadata.
     *
     * Reserved keys:
     * - __row_style: table row semantic style (default, warning, success, etc.)
     * - __row_selected: boolean selected state
     * - __row_action: custom backend action for row click
     * - __row_parameters: custom backend parameters for row click
     * - _model_id: model identifier exposed as parameter model_id
     *
     * @param array $rowData
     * @return array{0: array, 1: array{style: string, selected: bool, action: ?string, parameters: array<string, mixed>}}
     */
    private function splitFormattedRowData(array $rowData): array
    {
        $style = (string) ($rowData['__row_style'] ?? 'default');
        $action = $rowData['__row_action'] ?? null;

        $parameters = $rowData['__row_parameters'] ?? [];
        if (!is_array($parameters)) {
            $parameters = [];
        }

        $modelId = $rowData['_model_id'] ?? null;
        if ($modelId !== null && !array_key_exists('model_id', $parameters)) {
            $parameters['model_id'] = $modelId;
        }

        $selected = (bool) ($rowData['__row_selected'] ?? false);

        unset(
            $rowData['__row_style'],
            $rowData['__row_selected'],
            $rowData['__row_action'],
            $rowData['__row_parameters'],
            $rowData['_model_id']
        );

        return [
            $rowData,
            [
                'style' => $style,
                'selected' => $selected,
                'action' => is_string($action) && $action !== '' ? $action : null,
                'parameters' => $parameters,
            ]
        ];
    }

    /**
     * Apply row-level metadata after the row has been prepared.
     *
     * @param int $row
     * @param array{style: string, selected: bool, action: ?string, parameters: array<string, mixed>} $meta
     * @return void
     */
    private function applyRowMetadata(int $row, array $meta): void
    {
        if (!isset($this->rowBuilders[$row])) {
            return;
        }

        $this->rowBuilders[$row]
            ->style($meta['style'] !== '' ? $meta['style'] : 'default')
            ->selected($meta['selected'])
            ->action($meta['action'] ?? (empty($meta['parameters']) ? null : $this->getRowClickActionName()))
            ->parameters(empty($meta['parameters']) ? null : $meta['parameters']);
    }

    /**
     * Apply inline style metadata declared in a formatted cell array.
     *
     * @param TableCell $cell
     * @param array $value
     * @return void
     */
    private function applyCellMetadata(TableCell $cell, array $value): void
    {
        if (isset($value['background_color']) && $value['background_color'] !== '') {
            $cell->backgroundColor((string) $value['background_color']);
        }

        if (isset($value['text_color']) && $value['text_color'] !== '') {
            $cell->textColor((string) $value['text_color']);
        }

        if (isset($value['border_color']) && $value['border_color'] !== '') {
            $cell->borderColor((string) $value['border_color']);
        }
    }

    /**
     * Update table data for the current page
     * Clears existing rows and fills them with data from the current page
     */
    private function updateTableData(): void
    {
        $model = $this->getModel();
        if (!$model) {
            return;
        }

        $pagination = $this->config['pagination'];
        $currentPage = $pagination['current_page'];
        $perPage = $pagination['per_page'];

        // Ensure row capacity matches current page size (important after search/filters).
        $this->ensureRowCapacity($perPage);

        // Clear current rows
        $this->clearRows();

        // Fetch data for current page
        $formattedData = $model->getFormattedPageData($currentPage, $perPage);

        // Protocol-driven row structure sync:
        // - shrinking rows => mark extra TableRow as parent=null (frontend removes from DOM)
        // - growing rows => create/re-attach rows and cells with proper parents
        $targetRows = min(count($formattedData), max(0, (int) $perPage));
        $this->syncRowStructure($targetRows);

        $this->rows = $targetRows;
        $this->setConfig('rows', $this->rows);

        // Fill rows with new data and track actual row count
        $row = 0;
        foreach ($formattedData as $rowData) {
            if ($row >= $this->rows) {
                break;
            }
            [$rowData, $rowMeta] = $this->splitFormattedRowData($rowData);
            $rowValues = array_values($rowData);
            $this->fillRow($row, $rowValues);
            $this->applyRowMetadata($row, $rowMeta);
            $row++;
        }

        // Update $this->rows with the actual number of rows displayed
        // This is important for the last page which may have fewer rows than per_page
        $this->rows = $row;
        $this->setConfig('rows', $this->rows);
    }

    /**
     * Synchronize row/cell component structure for the target row count.
     *
     * USIM protocol rules:
     * - Removed rows must be sent with parent=null.
     * - Added rows must include correct parent (rows container), and cells with row parent.
     *
     * @param int $targetRows
     * @return void
     */
    private function syncRowStructure(int $targetRows): void
    {
        $targetRows = max(0, $targetRows);
        $existingRows = count($this->rowBuilders);

        // Grow structure when needed.
        if ($existingRows < $targetRows) {
            for ($row = $existingRows; $row < $targetRows; $row++) {
                // Keep table internals unnamed to avoid deterministic ID hash collisions.
                $rowBuilder = $this->createRow();
                $rowBuilder->row($row);

                $rowMinHeight = $this->config['row_min_height'] ?? null;

                if ($rowMinHeight !== null) {
                    $rowBuilder->minHeight(Size::from($rowMinHeight));
                }

                $this->rowBuilders[$row] = $rowBuilder;
                $this->cells[$row] = [];

                for ($col = 0; $col < $this->cols; $col++) {
                    $cell = $rowBuilder->createCell();
                    $cell->text('')->column($col);

                    if (isset($this->columnWidths[$col])) {
                        $width = $this->columnWidths[$col];
                        $cell->widthConstraints($width, $width);
                    }

                    $this->cells[$row][$col] = $cell;
                }
            }
        }

        // Active rows: ensure parent points to rows container and cells to row.
        for ($row = 0; $row < $targetRows; $row++) {
            if (!isset($this->rowBuilders[$row])) {
                continue;
            }

            $rowBuilder = $this->rowBuilders[$row];
            $rowBuilder->setParent($this->rowsContainer->getId());
            $rowBuilder->row($row);

            $cells = $rowBuilder->getCells();
            if (!isset($this->cells[$row])) {
                $this->cells[$row] = $cells;
            }

            foreach ($cells as $cell) {
                $cell->setParent($rowBuilder->getId());
            }
        }

        // Removed rows: mark parent=null so frontend removes from DOM.
        for ($row = $targetRows; $row < count($this->rowBuilders); $row++) {
            if (isset($this->rowBuilders[$row])) {
                $this->rowBuilders[$row]->setParent(null);

                // Keep row/cell lifecycle symmetric in incremental updates:
                // if a row is removed, detach all its cells too so they can be
                // reattached deterministically when that row becomes visible again.
                foreach ($this->rowBuilders[$row]->getCells() as $cell) {
                    $cell->setParent(null);
                }
            }
        }
    }

    /**
     * Ensure the table has enough row builders and cells for the required size.
     *
     * @param int $requiredRows
     * @return void
     */
    private function ensureRowCapacity(int $requiredRows): void
    {
        if ($requiredRows <= 0) {
            return;
        }

        $existingRows = count($this->rowBuilders);

        if ($existingRows < $requiredRows) {
            for ($row = $existingRows; $row < $requiredRows; $row++) {
                // Keep table internals unnamed to avoid deterministic ID hash collisions.
                $rowBuilder = $this->createRow();
                $rowBuilder->row($row);

                $rowMinHeight = $this->config['row_min_height'] ?? null;

                if ($rowMinHeight !== null) {
                    $rowBuilder->minHeight(Size::from($rowMinHeight));
                }

                $this->rowBuilders[$row] = $rowBuilder;
                $this->cells[$row] = [];

                for ($col = 0; $col < $this->cols; $col++) {
                    $cell = $rowBuilder->createCell();
                    $cell->text('')->column($col);

                    if (isset($this->columnWidths[$col])) {
                        $width = $this->columnWidths[$col];
                        $cell->widthConstraints($width, $width);
                    }

                    $this->cells[$row][$col] = $cell;
                }
            }
        }

        $this->rows = $requiredRows;
        $this->setConfig('rows', $this->rows);
    }

    /**
     * Get the data model instance
     *
     * @return AbstractTableModel|null
     */
    public function getModel(): ?AbstractTableModel
    {
        if ($this->model === null) {
            $modelClass = $this->config['data_model'] ?? null;
            if ($modelClass) {
                $this->model = new $modelClass($this);
            }
        }
        return $this->model;
    }

    /**
     * Get the current configuration
     *
     * @return array
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    public function updatePaginationData(): void
    {
        $model = $this->getModel();
        $totalItems = $model->getTotalItems();
        // por compatibilidad momentánea paara testing
        $this->config['total_items'] = $totalItems;

        $pagination = $this->config['pagination'];
        $currentPage = $pagination['current_page'];
        $perPage = $pagination['per_page'];

        // If pagination is disabled (per_page = 0), render all rows in a single logical page.
        // Use total item count as effective page size to keep existing flows intact.
        if (($pagination['enabled'] ?? true) === false || $perPage <= 0) {
            $perPage = max(1, $totalItems);
            $pagination['per_page'] = $perPage;
        }

        $pagination['total_items'] = $totalItems;
        $pagination['total_pages'] = (int) ceil($totalItems / $perPage);
        $pagination['can_next'] = $currentPage < $pagination['total_pages'];
        $pagination['can_prev'] = $currentPage > 1;

        // Hide pagination controls if there's only one page
        $pagination['show_controls'] = $pagination['total_pages'] > 1;

        if ($currentPage > $pagination['total_pages']) {
            $currentPage = max(1, $pagination['total_pages']);
            $pagination['current_page'] = $currentPage;
        }

        $this->config['pagination'] = $pagination;
    }

    public function connectChild(UIElement $element): void
    {
        if ($element instanceof Container) {
            if ($this->isRowsContainer($element)) {
                $this->rowsContainer = $element;
                $this->config['rows_container'] = $element->getId();
            }
            return;
        }

        if ($element instanceof TableHeaderRow) {
            $this->headerRow = $element;
            $this->config['header_row'] = $element->getId();
            return;
        }

        if ($element instanceof TableRow) {
            $this->addRow($element);
            return;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function postConnect(): void
    {
        $this->cols = $this->config['cols'];

        // Recover persisted column widths after cache deserialization so
        // dynamically re-created rows keep the same fixed widths.
        $rawColumnWidths = $this->config['column_widths'] ?? [];
        $this->columnWidths = [];
        if (is_array($rawColumnWidths)) {
            foreach ($rawColumnWidths as $col => $width) {
                $colIndex = (int) $col;
                if ($colIndex < 0) {
                    continue;
                }

                $this->columnWidths[$colIndex] = $this->normalizeColumnWidthValue($width);
            }
        }

        // After deserialization and reconnection, rebuild the row builders array
        // and cells matrix from the rowsContainer
        $this->reconstructRowBuilders();
        $this->reconstructCellsMatrix();

        // quiero logear el contenido de la celda [4][1] para verificar (use toString())
        // Log::debug("Contenido de la celda [4][1]: " . $this->cells[4][1]->toString());
    }

    /**
     * Reconstruct the rowBuilders array from rowsContainer's children
     *
     * After deserialization, $rowBuilders is empty so we need to rebuild it
     * by iterating through the rowsContainer's children.
     *
     * @return void
     */
    private function reconstructRowBuilders(): void
    {
        $this->rowBuilders = [];
        $rowIndex = 0;

        // Get all children from rowsContainer
        $children = $this->rowsContainer->getChildren();

        foreach ($children as $child) {
            // Only process TableRow instances
            if ($child instanceof TableRow) {
                $this->rowBuilders[$rowIndex] = $child;
                $rowIndex++;
            }
        }

        $this->rows = count($this->rowBuilders);
    }

    /**
     * Reconstruct the cells matrix from the component hierarchy
     *
     * Iterates through rows stored in $rowBuilders and extracts their cells
     * to rebuild the $this->cells two-dimensional array.
     *
     * This is called after deserialization when the component tree is fully
     * reconnected, ensuring we have access to all cell components.
     *
     * @return void
     */
    private function reconstructCellsMatrix(): void
    {
        $this->cells = [];

        // Iterate through all row builders and extract their cells
        foreach ($this->rowBuilders as $rowIndex => $rowBuilder) {
            $cellsInRow = $rowBuilder->getCells();

            // Log::debug("Reconstructing cells for row $rowIndex: " . $cellsInRow[1]->toString());
            if (!empty($cellsInRow)) {
                $this->cells[$rowIndex] = $cellsInRow;
            }
        }
        // Mostrar en debug los contenidos de las celdas reconstruidas
        // for ($i = 0; $i < count($this->cells); $i++) {
        //     for ($j = 0; $j < count($this->cells[$i]); $j++) {
        //         $cell = $this->cells[$i][$j];
        //         Log::debug("Celda [$i][$j]: " . $cell->getText());
        //     }
        // }
        // Log::debug("Reconstructed cells matrix with " . $this->getCell(0, 1)->getText());
    }

    /**
     * Create and return a header row for this table
     * Only one header row is allowed per table
     *
     * @param string|null $name Optional name for the header row
     * @return TableHeaderRow The header row builder
     */
    public function createHeaderRow(?string $name = null): TableHeaderRow
    {
        if ($this->headerRow !== null) {
            throw new \LogicException("Table already has a header row. Only one header row is allowed per table.");
        }

        $this->headerRow = new TableHeaderRow($this, $name ?? 'header');
        $this->headerRow->setParent($this->id);
        $this->config['header_row'] = $this->headerRow->getId();

        return $this->headerRow;
    }

    /**
     * Get the header row if it exists
     *
     * @return TableHeaderRow|null
     */
    public function getHeaderRow(): ?TableHeaderRow
    {
        return $this->headerRow;
    }

    /**
     * Create a new table row associated with this table
     * Automatically adds the row to the table
     *
     * @param string|null $name Optional name for the row
     * @return TableRow The new row builder
     */
    public function createRow(?string $name = null): TableRow
    {
        $row = new TableRow($this, $name);
        $this->addRow($row);
        return $row;
    }

    /**
     * Add a row component to this table
     *
     * @param TableRow $row The row to add
     * @return self For method chaining
     */
    public function addRow(TableRow $row): self
    {
        // Add the row to the rows container
        $this->rowsContainer->add($row);
        return $this;
    }

    /**
     * Get the rows container
     *
     * @return Container
     */
    public function getRowsContainer(): Container
    {
        return $this->rowsContainer;
    }

    /**
     * Initialize all cells as empty
     */
    private function initializeEmptyCells(): void
    {
        // Create header row
        $headerRow = $this->createHeaderRow($this->internalComponentName('header'));

        // Create empty header cells with column index
        for ($col = 0; $col < $this->cols; $col++) {
            $headerRow->createCell($this->internalComponentName("header_$col"))->text('')->column($col);
        }

        // Create data rows with empty cells
        for ($row = 0; $row < $this->rows; $row++) {
            // Keep table internals unnamed to avoid deterministic ID hash collisions.
            $rowBuilder = $this->createRow();
            $rowBuilder->row($row); // Set row index for ordering

            $rowMinHeight = $this->config['row_min_height'] ?? null;

            if ($rowMinHeight !== null) {
                $rowBuilder->minHeight(Size::from($rowMinHeight));
            }
            $this->rowBuilders[$row] = $rowBuilder;

            // Create empty cells for this row with column index
            $this->cells[$row] = [];
            for ($col = 0; $col < $this->cols; $col++) {
                $cell = $rowBuilder->createCell();
                $cell->text('')->column($col); // Empty by default with column index
                $this->cells[$row][$col] = $cell;
            }
        }
    }

    private function internalComponentName(string $suffix): string
    {
        $base = $this->name ?: (string) $this->id;

        return $base . '__' . $suffix;
    }

    private function getRowClickActionName(): string
    {
        $tableName = $this->name ?? 'table';

        return $tableName . '_row_clicked';
    }

    private function isRowsContainer(Container $element): bool
    {
        $name = $element->getName();

        return $name === 'rows'
            || $name === $this->internalComponentName('rows')
            || ($name !== null && str_ends_with($name, '__rows'));
    }

    /**
     * Fill the header row with data
     *
     * @param array $data Array of header cell data with keys:
     *                   - 'label': string, the header text
     *                   - 'sort_by': string|null, the column to sort by when clicked
     * @return self
     */
    public function fillHeaderRow(array $data): self
    {
        $headerRow = $this->getHeaderRow();

        if (!$headerRow) {
            throw new \LogicException("Table dimensions must be set before filling header row");
        }

        $cells = $headerRow->getCells();

        // Generate action name from table name
        $tableName = $this->name ?? 'table';
        $actionName = $tableName . '_column_clicked';

        for ($col = 0; $col < min(count($data), $this->cols); $col++) {
            if (isset($cells[$col])) {
                $columnText = $data[$col]['label'] ?? '';
                $sortBy = $data[$col]['sort_by'] ?? null;
                $cells[$col]
                    ->text($columnText)
                    ->action($actionName)
                    ->setConfig('parameters', ['column_text' => $columnText, 'sort_by' => $sortBy]);
            }
        }

        return $this;
    }

    /**
     * Clear all data rows (set all cells to empty strings)
     * This is useful for pagination or when reloading data
     *
     * @return self
     */
    public function clearRows(): self
    {
        for ($row = 0; $row < $this->rows; $row++) {
            for ($col = 0; $col < $this->cols; $col++) {
                $this->cells[$row][$col]->clearCell();
            }
        }

        return $this;
    }

    /**
     * Fill a data row with values
     *
     * @param int $row Row index (0-based)
     * @param array $data Array of cell data
     *                    - string: text content
     *                    - array with 'text': text content
     *                    - array with 'button': button config
     *                    - array with 'url_image': image config
     * @return self
     */
    public function fillRow(int $row, array $data): self
    {
        if ($row < 0 || $row >= $this->rows) {
            throw new \OutOfBoundsException("Row index $row is out of bounds (0-" . ($this->rows - 1) . ")");
        }

        $this->rowBuilders[$row]
            ->style('default')
            ->selected(false)
            ->action(null)
            ->parameters(null);

        for ($col = 0; $col < min(count($data), $this->cols); $col++) {
            $value = $data[$col];
            $cell = $this->cells[$row][$col];

            // Always reset render/content state to avoid stale visual metadata.
            $cell->clearCell();

            // Log::info("Filling cell at ($row, $col) with value: " . json_encode($value));

            if (is_string($value) || is_numeric($value)) {
                // Simple text (string or number)
                $cell->text((string) $value)->padding(4); // Compact padding for text cells
            } elseif (is_array($value)) {
                if (isset($value['text'])) {
                    $cell->text($value['text'])->padding(4); // Compact padding for text cells
                } elseif (isset($value['button'])) {
                    $cell->button($value['button'])->padding(2); // Even more compact for buttons
                } elseif (isset($value['url_image'])) {
                    $cell->urlImage(
                        $value['url_image'],
                        $value['alt'] ?? null,
                        $value['width'] ?? null,
                        $value['height'] ?? null
                    )->padding(2); // Compact for images
                }

                $this->applyCellMetadata($cell, $value);
            }
        }

        return $this;
    }

    /**
     * Get the cell ID for a specific row and column
     *
     * Format: tableId_row_col
     * Example: 88001_0_1 (table 88001, row 0, col 1)
     *
     * @param int $row Row index (0-based)
     * @param int $col Column index (0-based)
     * @return int Cell ID
     */
    public function getCellId(int $row, int $col): int
    {
        if ($row < 0 || $row >= $this->rows) {
            throw new \OutOfBoundsException("Row index $row is out of bounds");
        }

        if ($col < 0 || $col >= $this->cols) {
            throw new \OutOfBoundsException("Column index $col is out of bounds");
        }

        return $this->cells[$row][$col]->getId();
    }

    /**
     * Get a specific cell builder
     *
     * @param int $row Row index (0-based)
     * @param int $col Column index (0-based)
     * @return TableCell
     */
    public function getCell(int $row, int $col): TableCell
    {
        // Log::debug("Getting cell at ($row, $col) {$this->rows}x{$this->cols}");
        if ($row < 0 || $row >= $this->rows) {
            throw new \OutOfBoundsException("Row index $row is out of bounds");
        }

        if ($col < 0 || $col >= $this->cols) {
            throw new \OutOfBoundsException("Column index $col is out of bounds");
        }

        return $this->cells[$row][$col];
    }

    /**
     * Edit the content of a specific cell
     *
     * This is a convenience method to quickly update a cell's text content.
     * For more complex cell modifications (buttons, images, etc.), use getCell()
     * and modify the cell builder directly.
     *
     * @param int $row Row index (0-based)
     * @param int $col Column index (0-based)
     * @param string $text New text content for the cell
     * @return self For method chaining
     */
    public function editCell(int $row, int $col, string $text): self
    {
        $cell = $this->getCell($row, $col);
        $cell->text($text);
        return $this;
    }

    /**
     * Set the table title
     *
     * @param string $title The table title
     * @return self
     */
    public function title(string $title): self
    {
        return $this->setConfig('title', $title);
    }

    /**
     * Set the table alignment within its parent container
     *
     * @param string $align Alignment: 'left', 'center', or 'right'
     * @return self
     */
    public function align(string $align): self
    {
        if (!in_array($align, ['left', 'center', 'right'])) {
            throw new \InvalidArgumentException("Invalid alignment: $align. Use 'left', 'center', or 'right'.");
        }

        return $this->setConfig('align', $align);
    }

    /**
     * Set border radius for the table wrapper.
     *
     * @param string|int $radius CSS radius value or pixels as integer
     * @return self
     */
    public function borderRadius(string|int $radius): self
    {
        if (is_int($radius)) {
            $radius = $radius === 0 ? '0' : "{$radius}px";
        }

        return $this->setConfig('border_radius', $radius);
    }

    /**
     * Convenience alias to set rounded corners.
     *
     * @param string|int|bool $radius Radius value (e.g. 8, '12px', true, false)
     * @return self
     */
    public function rounded(string|int|bool $radius = 8): self
    {
        if (is_bool($radius)) {
            $radius = $radius ? 8 : 0;
        }

        return $this->borderRadius($radius);
    }

    /**
     * Set table shadow
     *
     * @param string|int $intensity Shadow intensity (0=none, 1-3=levels, 'light', 'medium', 'heavy', or custom CSS)
     * @return self For method chaining
     */
    public function shadow(string|int $intensity = 1): self
    {
        if (is_int($intensity)) {
            $shadows = [
                0 => 'none',
                1 => '0 2px 8px rgba(0, 0, 0, 0.1)',
                2 => '0 4px 16px rgba(0, 0, 0, 0.15)',
                3 => '0 8px 32px rgba(0, 0, 0, 0.2)',
            ];
            $shadow = $shadows[$intensity] ?? $shadows[1];
        } else {
            $shadows = [
                'light' => '0 1px 3px rgba(0,0,0,0.1)',
                'medium' => '0 4px 6px rgba(0,0,0,0.1)',
                'heavy' => '0 10px 15px rgba(0,0,0,0.2)'
            ];
            $shadow = $shadows[$intensity] ?? $intensity;
        }
        return $this->setConfig('box_shadow', $shadow);
    }

    /**
     * Set minimum height for all rows
     *
     * @param int $height Minimum height in pixels
     * @return self
     */
    public function rowMinHeight(int $height): self
    {
        $rowMinHeight = Size::from($height);
        $this->setConfig('row_min_height', (string) $rowMinHeight);

        // Apply min height to all existing rows
        foreach ($this->rowBuilders as $row) {
            $row->minHeight($rowMinHeight);
        }

        return $this;
    }

    /**
     * Set or get minimum height for table body area.
     *
     * @param int|string|null $height Height in px (int) or any CSS size string
     * @return static|string|null
     */
    public function bodyMinHeight(int|string|null $height = null): static|string|null
    {
        if ($height === null) {
            return $this->config['body_min_height'] ?? null;
        }

        return $this->setConfig('body_min_height', $this->normalizeCssSize($height));
    }

    /**
     * Set or get maximum height for table body area.
     *
     * @param int|string|null $height Height in px (int) or any CSS size string
     * @return static|string|null
     */
    public function bodyMaxHeight(int|string|null $height = null): static|string|null
    {
        if ($height === null) {
            return $this->config['body_max_height'] ?? null;
        }

        return $this->setConfig('body_max_height', $this->normalizeCssSize($height));
    }

    /**
     * Set the maximum and minimum height for the table body area en pixels.
     *
     * @param int|null $height
     * @return static|string|null
     */
    public function bodyHeight(int|string|null $height): static|string|null
    {
        if ($height === null) {
            return $this->config['body_height'] ?? null;
        }

        $value = $this->normalizeCssSize($height);

        $this->setConfig('body_max_height', $value);
        $this->setConfig('body_min_height', $value);

        return $this->setConfig('body_height', $value);
    }

    /**
     * Set or get horizontal overflow mode for table body area.
     *
     * Allowed values: visible, hidden, auto, scroll.
     *
     * @param string|null $overflow
     * @return static|string|null
     */
    public function bodyOverflowX(?string $overflow = null): static|string|null
    {
        if ($overflow === null) {
            return $this->config['body_overflow_x'] ?? null;
        }

        return $this->setConfig('body_overflow_x', $this->normalizeOverflowValue($overflow));
    }

    /**
     * Set or get vertical overflow mode for table body area.
     *
     * Allowed values: visible, hidden, auto, scroll.
     *
     * @param string|null $overflow
     * @return static|string|null
     */
    public function bodyOverflowY(?string $overflow = null): static|string|null
    {
        if ($overflow === null) {
            return $this->config['body_overflow_y'] ?? null;
        }

        return $this->setConfig('body_overflow_y', $this->normalizeOverflowValue($overflow));
    }

    /**
     * Set overflow mode for table body area.
     *
     * @param string $overflowX
     * @param string|null $overflowY If null, same value as $overflowX is used
     * @return self
     */
    public function bodyOverflow(string $overflowX, ?string $overflowY = null): self
    {
        $normalizedX = $this->normalizeOverflowValue($overflowX);
        $normalizedY = $this->normalizeOverflowValue($overflowY ?? $overflowX);

        $this->setConfig('body_overflow_x', $normalizedX);
        $this->setConfig('body_overflow_y', $normalizedY);

        return $this;
    }

    /**
     * Set fixed width for a specific column.
     *
     * @param int $col Column index (0-based)
     * @param int $width Width in pixels
     * @return self
     */
    public function columnWidth(int $col, int $width): self
    {
        if ($col < 0 || $col >= $this->cols) {
            throw new \OutOfBoundsException("Column index $col is out of bounds (0-" . ($this->cols - 1) . ")");
        }

        $this->columnWidths[$col] = $width;
        $this->setConfig('column_widths', $this->columnWidths);

        // Apply width to all cells in this column (header + data rows)
        if ($this->headerRow) {
            $headerCells = $this->headerRow->getCells();
            if (isset($headerCells[$col])) {
                $headerCells[$col]->widthConstraints($width, $width);
            }
        }

        // Apply to all data row cells in this column
        for ($row = 0; $row < $this->rows; $row++) {
            if (isset($this->cells[$row][$col])) {
                $this->cells[$row][$col]->widthConstraints($width, $width);
            }
        }

        return $this;
    }

    /**
     * Set fixed widths for all columns at once.
     *
     * @param array $widths Array of widths in pixels
     * @return self
     */
    public function columnWidths(array $widths): self
    {
        foreach ($widths as $col => $width) {
            $this->columnWidth((int) $col, $this->normalizeColumnWidthValue($width));
        }

        return $this;
    }

    /**
     * Set pagination page size. If perPage is 0, pagination is disabled.
     *
     * @param int $perPage Number of items per page
     * @return self
     */
    public function pagination(int $perPage = self::DEFAULT_PAGINATION_PER_PAGE): self
    {
        $pagination = $this->config['pagination'];
        $pagination['enabled'] = $perPage > 0;
        $pagination['per_page'] = $perPage;
        $this->setConfig('pagination', $pagination);
        return $this;
    }

    public function getPaginationData(): array
    {
        return $this->config['pagination'];
    }

    /**
     * Configure table using a data model
     * The data model should provide methods like:
     * - getColumns(): array of column definitions
     * - getPaginationInfo(): pagination information
     * - getFormattedPageData(): formatted data for current page
     *
     * @param string $dataModel The data model class name
     * @return self
     * @throws \InvalidArgumentException
     */
    public function dataModel(string $dataModel): self
    {
        $this->validateDataModel($dataModel);
        $this->model = new $dataModel($this);
        $this->setConfig('data_model', $dataModel);

        $columns = $this->model->getColumns();
        $this->initializeTableDimensions($columns);

        if ($this->hasValidDimensions()) {
            $this->initializeEmptyCells();
            $this->configureTableColumns($columns);
            $this->configureTableHeaders($columns);
            $this->fillTableData();
            $this->calculateAndSetTableWidth();
        }

        return $this;
    }

    /**
     * Validate that the data model is a subclass of AbstractTableModel
     *
     * @param string $dataModel
     * @throws \InvalidArgumentException
     */
    private function validateDataModel(string $dataModel): void
    {
        if (!is_subclass_of($dataModel, AbstractTableModel::class)) {
            throw new \InvalidArgumentException(
                "Data model must be a subclass of AbstractTableModel, got: $dataModel"
            );
        }
    }

    /**
     * Initialize table dimensions from the data model
     *
     * @param array $columns
     */
    private function initializeTableDimensions(array $columns): void
    {
        $this->cols = count($columns);
        $this->setConfig('cols', $this->cols);

        $this->updatePaginationData();
        $this->rows = $this->config['pagination']['per_page'];
        $this->setConfig('rows', $this->rows);
    }

    /**
     * Check if table has valid dimensions for rendering
     *
     * @return bool
     */
    private function hasValidDimensions(): bool
    {
        return $this->rows > 0 && $this->cols > 0;
    }

    /**
     * Configure column widths from the data model
     *
     * @param array $columns
     */
    private function configureTableColumns(array $columns): void
    {
        $columnIndex = 0;
        foreach ($columns as $column) {
            $width = $this->resolveColumnWidth($column);
            $this->columnWidth($columnIndex, $width);
            $columnIndex++;
        }
    }

    /**
     * Configure and fill the table header row
     *
     * @param array $columns
     */
    private function configureTableHeaders(array $columns): void
    {
        $headerData = array_values(array_map(
            fn($column) => [
                'label' => $this->extractColumnLabel($column),
                'sort_by' => $this->extractSortByKey($column),
            ],
            $columns
        ));

        $this->fillHeaderRow($headerData);
    }

    /**
     * Extract label from column definition
     *
     * @param mixed $column
     * @return string
     */
    private function extractColumnLabel(mixed $column): string
    {
        return \is_array($column) ? ($column['label'] ?? '') : (string) $column;
    }

    /**
     * Extract sort_by key from column definition
     *
     * @param mixed $column
     * @return string|null
     */
    private function extractSortByKey(mixed $column): ?string
    {
        return \is_array($column) ? ($column['sort_by'] ?? null) : null;
    }

    /**
     * Fill table data rows from the current page of the data model
     */
    private function fillTableData(): void
    {
        $pagination = $this->config['pagination'];
        $currentPage = $pagination['current_page'];
        $perPage = $pagination['per_page'];

        $formattedData = $this->model->getFormattedPageData($currentPage, $perPage);

        $row = 0;
        foreach ($formattedData as $rowData) {
            if ($row >= $this->rows) {
                break;
            }

            [$rowData, $rowMeta] = $this->splitFormattedRowData($rowData);
            $rowValues = array_values($rowData);
            $this->fillRow($row, $rowValues);
            $this->applyRowMetadata($row, $rowMeta);
            $row++;
        }
    }

    /**
     * Calculate table width as the sum of all column widths and store it in config
     *
     * @return int The calculated total width
     */
    private function calculateAndSetTableWidth(): int
    {
        $totalWidth = 0;

        for ($col = 0; $col < $this->cols; $col++) {
            $totalWidth += $this->columnWidths[$col] ?? self::DEFAULT_COLUMN_WIDTH;
        }

        $this->setConfig('width', $totalWidth);
        return $totalWidth;
    }

    /**
     * Normalize CSS size values to consistent string format.
     *
     * @param int|string $value
     * @return string
     */
    private function normalizeCssSize(int|string $value): string
    {
        return \is_int($value) ? "{$value}px" : trim($value);
    }

    /**
     * Validate and normalize overflow values.
     *
     * @param string $value
     * @return string
     */
    private function normalizeOverflowValue(string $value): string
    {
        $normalized = strtolower(trim($value));
        $allowed = ['visible', 'hidden', 'auto', 'scroll'];

        if (!in_array($normalized, $allowed, true)) {
            throw new \InvalidArgumentException(
                "Invalid overflow value: {$value}. Use one of: " . implode(', ', $allowed) . '.'
            );
        }

        return $normalized;
    }

    /**
     * Resolve column width from model metadata.
     *
     * Accepts only integer width in getColumns() definitions.
     * Falls back to DEFAULT_COLUMN_WIDTH when not present.
     *
     * @param mixed $column
     * @return int
     */
    private function resolveColumnWidth(mixed $column): int
    {
        if (is_array($column) && array_key_exists('width', $column)) {
            return $this->normalizeColumnWidthValue($column['width']);
        }

        return self::DEFAULT_COLUMN_WIDTH;
    }

    /**
     * Normalize width values to a safe integer in pixels.
     *
     * @param mixed $width
     * @return int
     */
    private function normalizeColumnWidthValue(mixed $width): int
    {
        if (is_int($width)) {
            return max(0, $width);
        }

        if (is_string($width) && is_numeric($width)) {
            return max(0, (int) $width);
        }

        if (is_array($width)) {
            $legacyWidth = $width['min'] ?? $width[0] ?? $width['max'] ?? $width[1] ?? self::DEFAULT_COLUMN_WIDTH;
            return $this->normalizeColumnWidthValue($legacyWidth);
        }

        return self::DEFAULT_COLUMN_WIDTH;
    }


    /**
     * Get table dimensions
     *
     * @return array ['rows' => int, 'cols' => int]
     */
    public function getDimensions(): array
    {
        return [
            'rows' => $this->rows,
            'cols' => $this->cols,
        ];
    }

    /**
     * Override toJson to include the rows container and header row in flat structure
     */
    public function toJson(?int $order = null): array
    {
        // Get the table's JSON
        $tableJson = parent::toJson();

        // Get the rows container's JSON
        $rowsContainerJson = $this->rowsContainer->toJson();

        // Start with table + rows container
        $result = $tableJson + $rowsContainerJson;

        // Add header row if it exists
        if ($this->headerRow !== null) {
            $headerRowJson = $this->headerRow->toJson();
            $result = $result + $headerRowJson;
        }

        return $result;
    }

    protected function getExcludedJsonKeys(): array
    {
        // Don't exclude 'name' - we need it for cell identification
        return parent::getExcludedJsonKeys();
    }
}
