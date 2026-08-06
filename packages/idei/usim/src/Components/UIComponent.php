<?php

namespace Idei\Usim\Components;

use Idei\Usim\Concerns\HasMargin;
use Idei\Usim\Concerns\HasSizing;
use Idei\Usim\Contracts\Marginable;
use Idei\Usim\Contracts\Sizeable;
use Idei\Usim\Contracts\UIElement;
use Idei\Usim\Support\UIIdGenerator;

/**
 * Abstract base class for all leaf UI components (Button, Label, Table, etc.)
 *
 * This class implements the common functionality for leaf nodes in the UI tree.
 * Leaf components cannot have children and represent atomic UI elements.
 *
 * @phpstan-consistent-constructor
 */
abstract class UIComponent implements UIElement, Sizeable, Marginable
{
    use HasSizing, HasMargin;

    protected int $id;
    protected string $type;
    protected ?string $name = null;
    protected int|string|null $parent = null;
    /** @var array<string, mixed> */
    protected array $config = [];

    public function __construct(?string $name = null)
    {
        $this->name = $name;

        // Detectar automáticamente el contexto desde la clase que invoca
        $context = $this->detectCallingContext();

        // Generar ID según si tiene nombre o no
        if ($this->name !== null) {
            // Updated to use Centralized Generator (Aware of Manifest)
            $this->id = UIIdGenerator::generateFromName($context, $this->name);
        } else {
            // ID AUTO-INCREMENT: Para componentes temporales sin nombre
            $this->id = UIIdGenerator::generate($context);
        }

        $this->type = $this->getTypeFromClassName();
        $this->config = array_merge([
            'type' => $this->type,
            'visible' => true,
            'parent' => null,
        ], $this->getDefaultConfig());

        // Only include 'name' if it's not null
        if ($this->name !== null) {
            $this->config['name'] = $this->name;
        }
    }
    public function isContainer(): bool
    {
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public static function deserialize(int $id, array $data): self
    {
        /** @var static $component */
        $component = new static();
        $component->id = $id;
        $component->type = $data['type'] ?? 'unknown';
        $component->name = $data['name'] ?? null;
        $component->parent = $data['parent'] ?? null;
        $component->config = array_merge($component->config, $data);

        return $component;
    }

    /**
     * {@inheritDoc}
     */
    public function connectChild(UIElement $element): void
    {
    }

    /**
     * {@inheritDoc}
     */
    public function postConnect(): void
    {
        // No-op for leaf components
    }

    public function config(string $key, mixed $value): static
    {
        return $this->setConfig($key, $value);
    }

    public function toString(): string
    {
        return sprintf(
            "%s (ID: %d, Name: %s, Parent: %s)",
            ucfirst($this->type),
            $this->id,
            $this->name ?? 'null',
            $this->parent !== null ? (string) $this->parent : 'null'
        );
    }

    /**
     * Detecta automáticamente la clase que está invocando el builder
     * Busca en el stack trace la primera clase fuera del namespace UI
     *
     * @return string El nombre completo con namespace de la clase invocante
     */
    private function detectCallingContext(): string
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);

        // Buscar en el stack trace la primera clase que NO sea del namespace UI (Legacy o Package)
        foreach ($trace as $frame) {
            if (isset($frame['class'])) {
                // Skip internal classes from Legacy Framework (App/UI/Components)
                if (str_starts_with($frame['class'], 'App\\UI\\Components\\')) {
                    continue;
                }
                // Skip internal classes from the package framework.
                if (str_starts_with($frame['class'], 'Idei\\Usim\\')) {
                    continue;
                }
                // Skip Http Controllers
                if (str_starts_with($frame['class'], 'Idei\\Usim\\Http\\')) {
                    continue;
                }

                // Found the consumer service!
                return $frame['class'];
            }
        }

        return 'default';
    }


    /**
     * Extract the component type from the class name
     * Example: "Button" -> "button"
     */
    private function getTypeFromClassName(): string
    {
        $className = (new \ReflectionClass($this))->getShortName();
        return strtolower(str_replace('Builder', '', $className));
    }

    /**
     * Get the default configuration for this component type
     * Must be implemented by each concrete component
     *
     * @return array<string, mixed> Default configuration values
     */
    abstract protected function getDefaultConfig(): array;

    /**
     * {@inheritDoc}
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Get the component name
     *
     * @return string|null Component name or null if not set
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * {@inheritDoc}
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * {@inheritDoc}
     */
    public function isVisible(): bool
    {
        return $this->config['visible'] ?? true;
    }

    /**
     * {@inheritDoc}
     */
    public function setVisible(bool $visible): static
    {
        $this->config['visible'] = $visible;
        return $this;
    }

    /**
     * Fluent API for setting visibility
     */
    public function visible(bool $visible = true): static
    {
        return $this->setVisible($visible);
    }

    /**
     * {@inheritDoc}
     * @return static
     */
    public function name(?string $name): static
    {
        $this->name = $name;
        if ($name !== null) {
            $this->config['name'] = $name;
        } else {
            unset($this->config['name']);
        }
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function setName(?string $name): static
    {
        $this->name = $name;
        if ($name !== null) {
            $this->config['name'] = $name;
        } else {
            unset($this->config['name']);
        }
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getParent(): int|string|null
    {
        return $this->parent;
    }

    /**
     * {@inheritDoc}
     */
    public function setParent(int|string|null $parent): static
    {
        $this->parent = $parent;
        $this->config['parent'] = $parent;
        return $this;
    }

    /**
     * Fluent API for setting parent
     *
     * @param int|string|null $parent The parent (int = parent ID, string = parent name, null = delete)
     * @return static For method chaining
     */
    public function parent(int|string|null $parent): static
    {
        return $this->setParent($parent);
    }

    /**
     * Assign this component to a named or identified tab within a tabbed container.
     *
     * @param int|string|null $tab The target tab id/name, or null to clear it
     * @return static
     */
    public function tab(int|string|null $tab): static
    {
        if ($tab === null) {
            unset($this->config['tab']);
            return $this;
        }

        $this->config['tab'] = is_string($tab) ? trim($tab) : $tab;
        return $this;
    }

    /**
     * {@inheritDoc}
     *
     * For leaf components, returns the configuration wrapped in the component ID
     * Null values are filtered out from the configuration
     *
     * @return array<int, array<string, mixed>>
     */
    public function toJson(?int $order = null): array
    {
        // Filter out null values from config
        $config = array_filter($this->config, fn($value) => $value !== null);

        // Keep explicit parent=null for protocol-driven removals in incremental updates.
        if (array_key_exists('parent', $this->config) && $this->config['parent'] === null) {
            $config['parent'] = null;
        }

        // Remove default visible value to save JSON size
        if (isset($config['visible']) && $config['visible'] === true) {
            unset($config['visible']);
        }

        // Add _order if provided by parent
        if ($order !== null) {
            $config['_order'] = $order;
        }

        // Return as associative array with component ID as key
        return [$this->id => $config];
    }

    /**
     * Get list of config keys to exclude from JSON output
     * Override in subclasses to customize
     *
     * @return list<string> List of keys to exclude
     */
    protected function getExcludedJsonKeys(): array
    {
        return [];
    }

    /**
     * Get the component configuration (without ID wrapper)
     * Useful for internal operations
     *
     * @return array<string, mixed> The configuration array
     */
    protected function getConfig(): array
    {
        return $this->config;
    }

    /**
     * {@inheritDoc}
     */
    protected function setConfig(string $key, mixed $value): static
    {
        $this->config[$key] = $value;
        return $this;
    }

    /**
     * Get a configuration value
     *
     * Allows reading component properties from event handlers.
     * Useful for getting current state like text, value, checked, etc.
     *
     * @param string $key The configuration key
     * @param mixed $default Default value if key doesn't exist
     * @return mixed The configuration value or default
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * Método de utilidad para debugging - obtiene información del contexto
     *
     * @param string $context Nombre del contexto
     * @return array<string, mixed> Información del contexto (offset, contador, etc)
     */
    public static function getContextInfo(string $context): array
    {
        return UIIdGenerator::getContextInfo($context);
    }

    // ========================================================================
    // POSITIONING METHODS
    // ========================================================================

    /**
     * Set component position anchor or CSS position mode.
     *
     * Anchor values: TOP_LEFT, TOP_CENTER, TOP_RIGHT,
     * MIDDLE_LEFT, CENTER, MIDDLE_RIGHT,
     * BOTTOM_LEFT, BOTTOM_CENTER, BOTTOM_RIGHT.
     *
     * Aliases supported: TOP_MIDDLE, LEFT_MIDDLE, RIGHT_MIDDLE,
     * LEFT_TOP, LEFT_BOTTOM, RIGHT_TOP, RIGHT_BOTTOM.
     *
     * CSS values supported for compatibility: static, relative, absolute, fixed, sticky.
     *
     * @param string $position Anchor name or CSS position value
     * @return static For method chaining
     */
    public function position(string $position): static
    {
        return $this->setConfig('position', $position);
    }

    /**
     * Set positioning mode (recommended with anchor-based positioning).
     *
     * @param string $mode One of: absolute, fixed, relative, static, sticky
     * @return static For method chaining
     */
    public function positionMode(string $mode = 'absolute'): static
    {
        return $this->setConfig('position_mode', strtolower($mode));
    }

    /**
     * Set horizontal offset from anchor.
     *
     * @param int|string $offset Number (px) or CSS value (e.g. 1rem, 10%)
     * @return static For method chaining
     */
    public function offsetX(int|string $offset): static
    {
        return $this->setConfig('position_offset_x', $offset);
    }

    /**
     * Set vertical offset from anchor.
     *
     * @param int|string $offset Number (px) or CSS value (e.g. 1rem, 10%)
     * @return static For method chaining
     */
    public function offsetY(int|string $offset): static
    {
        return $this->setConfig('position_offset_y', $offset);
    }

    /**
     * Set both horizontal and vertical offsets from anchor.
     *
     * @param int|string $offsetX Horizontal offset
     * @param int|string $offsetY Vertical offset
     * @return static For method chaining
     */
    public function offsets(int|string $offsetX, int|string $offsetY): static
    {
        $this->setConfig('position_offset_x', $offsetX);
        $this->setConfig('position_offset_y', $offsetY);
        return $this;
    }

    /**
     * Set z-index.
     *
     * @param int|string $zIndex Z-index value
     * @return static For method chaining
     */
    public function zIndex(int|string $zIndex): static
    {
        return $this->setConfig('z_index', $zIndex);
    }
}
