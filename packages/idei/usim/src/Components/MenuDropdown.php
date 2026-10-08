<?php

namespace Idei\Usim\Components;

use Idei\Usim\Navigation\TriggerConfig;
use Idei\Usim\Screen;

/**
 * Menu Dropdown Builder
 *
 * Builds dropdown menu structures with support for nested submenus.
 *
 * This component provides a fluent interface for constructing dropdown menus
 * with support for multiple levels of nesting, custom styling, and event handlers.
 * It generates semantic HTML markup with proper accessibility attributes and
 * integrates with the component lifecycle for dynamic menu management.
 */
class MenuDropdown extends UIComponent
{
    /** @var list<array<string, mixed>> */
    private array $items = [];

    public function getDefaultConfig(): array
    {
        return [
            'name' => $this->name,
            'items' => [],
        ];
    }

    /**
     * Clear all menu items
     */
    public function clearItems(): self
    {
        $this->items = [];

        return $this;
    }

    /**
     * Override toJson to ensure items are included in config
     */
    public function toJson(?int $order = null): array
    {
        // Copy items to config before rendering
        $this->config['items'] = $this->items;

        // Call parent implementation
        return parent::toJson($order);
    }

    /**
     * {@inheritDoc}
     */
    /**
     * @param  array<string, mixed>  $config
     */
    public static function deserialize(int $id, array $config): self
    {
        /** @var MenuDropdown $component */
        $component = parent::deserialize($id, $config);
        if (isset($config['items']) && is_array($config['items'])) {
            $items = [];
            foreach ($config['items'] as $item) {
                if (is_array($item)) {
                    $items[] = $item;
                }
            }
            $component->items = $items;
        }

        return $component;
    }

    /**
     * Add a menu item
     *
     * @param  string  $label  Item label
     * @param  string|null  $action  Action to trigger (optional if has submenu)
     * @param  array<string, mixed>  $params  Action parameters
     * @param  string|null  $icon  Icon emoji or text
     * @param  list<array<string, mixed>>  $submenu  Submenu items
     */
    public function item(
        string $label,
        ?string $action = null,
        array $params = [],
        ?string $icon = null,
        array $submenu = [],
        bool $visible = true
    ): self {
        $item = [
            'label' => $label,
            'action' => $action,
            'params' => $params,
            'icon' => $icon,
            'submenu' => $submenu,
        ];

        if (! $visible) {
            return $this;
        }

        $this->items[] = $item;

        return $this;
    }

    /**
     * Add a separator line
     */
    public function separator(bool $visible = true): self
    {
        if (! $visible) {
            return $this;
        }

        $this->items[] = [
            'type' => 'separator',
        ];

        return $this;
    }

    /**
     * Add a screen item to the menu automatically using its metadata.
     * Checks access permissions before adding.
     *
     * @param  string  $screenClass  The fully qualified class name of the screen
     */
    public function screen(string $screenClass, ?string $label = null, ?string $icon = null): self
    {
        if (! class_exists($screenClass) || ! is_subclass_of($screenClass, Screen::class)) {
            return $this;
        }

        // Check if user has access to this screen
        /** @var array{allowed: bool} $access */
        $access = $screenClass::checkAccess();
        if (! $access['allowed']) {
            return $this;
        }

        // Get Metadata
        $label = $label ?? $screenClass::getMenuLabel();
        $icon = $icon ?? $screenClass::getMenuIcon();
        $url = $screenClass::getRoutePath();

        // Add link without explicit permission check (already checked above)
        // We use 'no-auth' effectively/implicitly because we already filtered it,
        // but passing null lets the rendering engine decide if it needs any client-side protection.
        // Since we resolved it server-side, we can just add it.
        return $this->link($label, $url, $icon);
    }

    /**
     * Add a menu item with URL navigation
     *
     * @param  string  $label  Item label
     * @param  string  $url  URL to navigate to
     * @param  string|null  $icon  Icon emoji or text
     */
    public function link(string $label, string $url, ?string $icon = null, bool $visible = true): self
    {
        if (! $visible) {
            return $this;
        }
        $item = [
            'label' => $label,
            'url' => $url,
            'icon' => $icon,
        ];
        $this->items[] = $item;

        return $this;
    }

    /**
     * Add a submenu item
     *
     * @param  string  $label  Parent item label
     * @param  callable  $callback  Callback to build submenu items
     * @param  string|null  $icon  Parent icon
     */
    public function submenu(string $label, callable $callback, ?string $icon = null, bool $visible = true): self
    {
        if (! $visible) {
            return $this;
        }

        $submenuBuilder = new self($label.'_submenu');
        $callback($submenuBuilder);

        $item = [
            'label' => $label,
            'icon' => $icon,
            'submenu' => $submenuBuilder->items,
        ];

        $this->items[] = $item;

        return $this;
    }

    /**
     * Set the caller service ID for action callbacks
     *
     * @param  string  $serviceId  Service component ID
     */
    public function callerServiceId(string $serviceId): self
    {
        $this->config['_caller_service_id'] = $serviceId;

        return $this;
    }

    /**
     * Customize the trigger button
     *
     * @param  string  $label  Button text
     * @param  string|null  $icon  Button icon
     * @param  TriggerConfig|string  $label  Button text or TriggerConfig DTO
     * @param  string|null  $icon  Button icon
     * @param  string  $style  Button style (primary, secondary, etc.)
     */
    public function trigger(TriggerConfig|string $label = '☰', ?string $icon = null, string $style = 'default'): self
    {
        if ($label instanceof TriggerConfig) {
            $label->applyTo($this);

            return $this;
        }

        $this->config['trigger'] = [
            'label' => $label,
            'icon' => $icon,
            'style' => $style,
        ];

        return $this;
    }

    /**
     * Set an image as the trigger icon (e.g., user profile photo)
     *
     * @param  string  $imageUrl  URL of the image
     * @param  string  $alt  Alt text for accessibility
     * @param  string|null  $label  Optional text label next to image
     * @param  string  $style  Button style
     */
    public function triggerImage(string $imageUrl, string $alt = 'User', ?string $label = null, string $style = 'default'): self
    {
        $this->config['trigger'] = [
            'image' => $imageUrl,
            'alt' => $alt,
            'label' => $label,
            'style' => $style,
        ];

        return $this;
    }

    /**
     * Set menu positioning
     *
     * @param  string  $position  'bottom-left', 'bottom-right', 'top-left', 'top-right'
     */
    public function position(string $position = 'bottom-left'): static
    {
        $this->config['position'] = $position;

        return $this;
    }
}
