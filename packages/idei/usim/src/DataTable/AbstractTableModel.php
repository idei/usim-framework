<?php

namespace Idei\Usim\DataTable;

use Idei\Usim\Components\Table;

/**
 * Abstract Data Table Model
 *
 * Provides pagination logic and data management for table components.
 * Implementations should override the data source methods.
 */
abstract class AbstractTableModel
{
    protected Table $tableBuilder;

    public function __construct(Table $tableBuilder)
    {
        $this->tableBuilder = $tableBuilder;
    }

    /**
     * Get table columns definition
     *
     * This method should return an array defining the table columns,
     * including their names, types, and any other relevant metadata. For example:
     *
     * [
     *     ['name' => 'id', 'type' => 'int'],
     *     ['name' => 'title', 'type' => 'string'],
     *     ['name' => 'created_at', 'type' => 'datetime'],
     * ]
     *
     * @return array<string, array<string, mixed>>
     */
    abstract public function getColumns(): array;

    /**
     * @return list<array<string, mixed>>
     */
    abstract public function getFormattedPageData(int $currentPage, int $perPage): array;

    /**
     * Get data for the current page
     *
     * @return list<array<string, mixed>>
     */
    abstract public function getPageData(): array;

    /**
     * Get pagination data from the Table
     *
     * @return array{
     *   enabled: bool,
     *   per_page: int,
     *   current_page: int,
     *   total_items: int,
     *   can_next: bool,
     *   can_prev: bool,
     *   total_pages: int,
     *   show_controls: bool,
     *   labels: array{previous: string, next: string, showing: string}
     * }
     */
    public function getPaginationData(): array
    {
        return $this->tableBuilder->getPaginationData();
    }

    /**
     * Get total number of items
     *
     * @return int
     */
    public function getTotalItems(): int
    {
        return $this->countTotal();
    }

    /**
     * Count total items
     * Default implementation counts getAllData()
     * Override for more efficient counting
     *
     * @return int
     */
    abstract protected function countTotal(): int;

    /**
     * Updates the content of the row.
     *
     * @param int $rowIndex Row index to update, in the current page
     * @param array<string, mixed> $newData New data for the row.
     * @return void
     */
    public function updateRow(int $rowIndex, array $newData): void
    {
    }

    /**
     * Updates the content of a specific cell.
     *
     * @param int $rowIndex Row index to update, in the current page
     * @param int $columnIndex Column index to update
     * @param mixed $newValue New value for the cell.
     * @return void
     */
    public function updateCell(int $rowIndex, int $columnIndex, $newValue): void
    {
    }

    /**
     * Get the configuration for "removed" row display
     *
     * Returns an array that defines how removed rows should appear.
     * Services can override this to customize the removal appearance.
     *
     * @return array{
     *   primary_message: string,
     *   secondary_message: string,
     *   id_placeholder: string,
     *   button_placeholder: string,
     *   empty_placeholder: string
     * } Configuration for removed row display
     */
    public function getRemovedRowConfig(): array
    {
        return [
            'primary_message' => '[REMOVED]',   // Main removal message
            'secondary_message' => '-',         // Secondary placeholder
            'id_placeholder' => '-',            // ID column placeholder
            'button_placeholder' => '-',        // Button column placeholder
            'empty_placeholder' => '',          // Empty cell placeholder
        ];
    }

    /**
     * Get removal values for all columns based on configuration
     *
     * @param int $columnCount The number of columns
     * @return list<string> Values for each column when row is removed
     */
    public function getRemovalValues(int $columnCount): array
    {
        $config = $this->getRemovedRowConfig();
        $values = [];

        for ($i = 0; $i < $columnCount; $i++) {
            if ($i === 0) {
                $values[$i] = $config['id_placeholder']; // ID column
            } elseif ($i === 1) {
                $values[$i] = $config['primary_message']; // Main content column
            } elseif ($i >= $columnCount - 2) {
                $values[$i] = $config['button_placeholder']; // Button columns (usually last 2)
            } else {
                $values[$i] = $config['secondary_message']; // Data columns
            }
        }

        return $values;
    }
}
