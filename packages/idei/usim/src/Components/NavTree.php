<?php

namespace Idei\Usim\Components;

use Idei\Usim\Navigation\Contracts\MenuTargetInterface;
use Idei\Usim\Screen;

/**
 * Vertical Navigation Tree Component (Sidebar / Inline Accordion)
 *
 * Renders hierarchical navigation menus without floating dropdowns.
 * Supports inline collapsible accordion groups for submenus, icons,
 * separators, active screen/route highlighting, and compact Activity Bar mode.
 */
class NavTree extends UIComponent implements MenuTargetInterface
{
    /** @var list<array<string, mixed>> */
    private array $items = [];

    protected function getDefaultConfig(): array
    {
        return [
            'name' => $this->name,
            'items' => [],
            'active_screen' => null,
            'active_route' => null,
            'active_group' => null,
            'on_group_click' => null,
            'accordion' => false,
            'compact' => false,
        ];
    }

    /**
     * Get the raw normalized navigation items currently stored on this NavTree.
     *
     * @return list<array<string, mixed>>
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * Replace all navigation items on this NavTree (preserving nested submenus of arbitrary depth).
     *
     * @param  list<array<string, mixed>>  $items
     */
    public function setItems(array $items): static
    {
        $this->items = array_values($items);

        return $this->setConfig('items', $this->items);
    }

    public function clearItems(): self
    {
        $this->items = [];

        return $this;
    }

    public function toJson(?int $order = null): array
    {
        $this->config['items'] = $this->items;

        return parent::toJson($order);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function deserialize(int $id, array $config): self
    {
        /** @var NavTree $component */
        $component = parent::deserialize($id, $config);
        if (isset($config['items']) && is_array($config['items'])) {
            /** @var list<array<string, mixed>> $items */
            $items = [];
            foreach ($config['items'] as $item) {
                if (is_array($item)) {
                    /** @var array<string, mixed> $item */
                    $items[] = $item;
                }
            }
            $component->items = $items;
        }

        return $component;
    }

    /**
     * @param  array<string, mixed>  $params
     * @param  list<array<string, mixed>>  $submenu
     */
    public function item(
        string $label,
        ?string $action = null,
        array $params = [],
        ?string $icon = null,
        array $submenu = [],
        bool $visible = true
    ): self {
        if (! $visible) {
            return $this;
        }

        $this->items[] = [
            'label' => $label,
            'action' => $action,
            'params' => $params,
            'icon' => $icon,
            'submenu' => $submenu,
        ];

        return $this;
    }

    public function link(string $label, string $url, ?string $icon = null, bool $visible = true): self
    {
        if (! $visible) {
            return $this;
        }

        $this->items[] = [
            'label' => $label,
            'url' => $url,
            'icon' => $icon,
        ];

        return $this;
    }

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

    public function submenu(string $label, callable $callback, ?string $icon = null, bool $visible = true): self
    {
        if (! $visible) {
            return $this;
        }

        $subBuilder = new self($label.'_submenu');
        $callback($subBuilder);

        $this->items[] = [
            'label' => $label,
            'icon' => $icon,
            'submenu' => $subBuilder->items,
        ];

        return $this;
    }

    /**
     * Highlight the currently active Screen class (and optionally resolve its route).
     *
     * @param  class-string<Screen>|string|null  $screenClass
     */
    public function activeScreen(?string $screenClass): static
    {
        $this->setConfig('active_screen', $screenClass);
        if ($screenClass !== null && class_exists($screenClass) && is_subclass_of($screenClass, Screen::class)) {
            $this->setConfig('active_route', $screenClass::getRoutePath());
        }

        return $this;
    }

    public function activeRoute(?string $route): static
    {
        return $this->setConfig('active_route', $route);
    }

    /**
     * When true, opening one submenu group automatically collapses sibling groups.
     */
    public function accordion(bool $singleOpen = true): static
    {
        return $this->setConfig('accordion', $singleOpen);
    }

    /**
     * Force compact icon-only mode (Activity Bar style).
     */
    public function compact(bool $compact = true): static
    {
        return $this->setConfig('compact', $compact);
    }

    /**
     * Delegate top-level submenu group clicks to a backend Screen action
     * (e.g., to open the group inside a separate collapsible left_panel Split).
     */
    public function onGroupClick(?string $action): static
    {
        return $this->setConfig('on_group_click', $action);
    }

    /**
     * Mark a top-level submenu group as currently active (e.g. open in left_panel).
     */
    public function activeGroup(?string $group): static
    {
        return $this->setConfig('active_group', $group);
    }

    public function callerServiceId(string $serviceId): static
    {
        return $this->setConfig('_caller_service_id', $serviceId);
    }
}
