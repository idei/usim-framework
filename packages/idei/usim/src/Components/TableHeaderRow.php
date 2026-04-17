<?php

namespace Idei\Usim\Components;

use Idei\Usim\Contracts\UIElement;

/**
 * Builder for Table Header Row UI components
 *
 * Represents a header row in a table. This component must be associated with a Table
 * and contains TableHeaderCell components.
 */
class TableHeaderRow extends UIComponent
{
    /** @var Table|null The parent table */
    private ?Table $table;

    /** @var array<TableHeaderCell> Array of header cells in this row */
    private array $cellComponents = [];

    /**
     * Create a new table header row
     *
     * @param Table $table The parent table this header row belongs to
     * @param string|null $name Optional name for the header row
     */
    public function __construct(?Table $table = null, ?string $name = null)
    {
        $this->table = $table;
        parent::__construct($name);
    }

    protected function getDefaultConfig(): array
    {
        return [];
    }

    public function connectChild(UIElement $element): void
    {
        if ($element instanceof TableHeaderCell) {
            $this->addCell($element);
        }
    }

    /**
     * Create and add a new header cell to this row
     *
     * @param string|null $name Optional name for the cell
     * @return TableHeaderCell The created header cell
     */
    public function createCell(?string $name = null): TableHeaderCell
    {
        $cell = new TableHeaderCell($this, $name);
        $cell->setParent($this->id);
        $this->cellComponents[] = $cell;
        return $cell;
    }

    /**
     * Add an existing header cell to this row
     *
     * @param TableHeaderCell $cell The header cell to add
     * @return self For method chaining
     */
    public function addCell(TableHeaderCell $cell): self
    {
        $cell->setParent($this->id);
        $this->cellComponents[] = $cell;
        return $this;
    }

    /**
     * Get all header cell components
     *
     * @return array<TableHeaderCell>
     */
    public function getCells(): array
    {
        return $this->cellComponents;
    }

    /**
     * Get the parent table
     *
     * @return Table
     */
    public function getTable(): Table
    {
        return $this->table;
    }

    /**
     * {@inheritDoc}
     *
     * Includes all header cell components in the flat JSON structure
     */
    /**
     * {@inheritDoc}
     */
    public function toJson(?int $order = null): array
    {
        // Get base config and filter nulls
        $config = array_filter($this->config, fn($value) => $value !== null);

        // Remove 'visible' if it's true (default value)
        if (isset($config['visible']) && $config['visible'] === true) {
            unset($config['visible']);
        }

        // Exclude additional keys
        $excludeKeys = $this->getExcludedJsonKeys();
        if (!empty($excludeKeys)) {
            $config = array_diff_key($config, array_flip($excludeKeys));
        }

        // CRITICAL: Include component ID in config for frontend lookups
        $config['_id'] = $this->id;

        // Start with this header row
        $result = [$this->id => $config];

        // Add all header cell components
        foreach ($this->cellComponents as $cell) {
            $cellJson = $cell->toJson();
            $result = $result + $cellJson;
        }

        return $result;
    }

    /**
     * Exclude 'name' from JSON output
     *
     * @return array List of keys to exclude
     */
    protected function getExcludedJsonKeys(): array
    {
        return ['name'];
    }
}
