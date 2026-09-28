<?php

namespace Idei\Usim\Navigation;

use Closure;
use Idei\Usim\Components\MenuDropdown;
use Idei\Usim\Navigation\Contracts\MenuProviderInterface;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;

class MenuBuilder
{
    protected ?string $id;
    /** @var list<MenuItem> */
    protected array $items = [];
    /** @var array<string, mixed> */
    protected array $triggerConfig = [];
    protected string $position = 'bottom-left';
    protected ?string $width = null;
    protected bool $visible = true;

    public function __construct(?string $id = null)
    {
        $this->id = $id;
    }

    public static function make(?string $id = null): self
    {
        return new self($id);
    }

    public function trigger(
        ?string $label = null,
        ?string $icon = null,
        ?string $image = null,
        ?string $alt = null,
        string $style = 'default'
    ): self {
        $this->triggerConfig = [
            'label' => $label,
            'icon' => $icon,
            'image' => $image,
            'alt' => $alt,
            'style' => $style,
        ];
        return $this;
    }

    public function position(string $position): self
    {
        $this->position = $position;
        return $this;
    }

    public function width(Size|string $width): self
    {
        $this->width = $width instanceof Size ? (string) $width : $width;
        return $this;
    }

    public function visible(bool $visible): self
    {
        $this->visible = $visible;
        return $this;
    }

    public function link(
        string $label,
        string $url,
        ?string $icon = null,
        Closure|bool|null $when = null
    ): MenuItem {
        $item = MenuItem::make($label)
            ->url($url)
            ->icon($icon)
            ->when($when);

        $this->items[] = $item;
        return $item;
    }

    /**
     * @param class-string<\Idei\Usim\Screen> $screenClass
     */
    public function screen(
        string $screenClass,
        ?string $label = null,
        ?string $icon = null,
        Closure|bool|null $when = null
    ): MenuItem {
        $item = MenuItem::make($label)
            ->screen($screenClass, $label, $icon)
            ->when($when);

        $this->items[] = $item;
        return $item;
    }

    /**
     * @param array<string, mixed> $params
     */
    public function action(
        string $label,
        string $action,
        array $params = [],
        ?string $icon = null,
        Closure|bool|null $when = null
    ): MenuItem {
        $item = MenuItem::make($label)
            ->action($action, $params)
            ->icon($icon)
            ->when($when);

        $this->items[] = $item;
        return $item;
    }

    public function submenu(
        string $label,
        Closure $callback,
        ?string $icon = null,
        Closure|bool|null $when = null
    ): MenuItem {
        $parentItem = MenuItem::make($label)
            ->icon($icon)
            ->when($when);

        $subBuilder = new self();
        $callback($subBuilder);

        foreach ($subBuilder->getItems() as $subItem) {
            $parentItem->addChild($subItem);
        }

        $this->items[] = $parentItem;
        return $parentItem;
    }

    public function separator(Closure|bool|null $when = null): MenuItem
    {
        $item = MenuItem::separator()->when($when);
        $this->items[] = $item;
        return $item;
    }

    public function item(MenuItem $item): self
    {
        $this->items[] = $item;
        return $this;
    }

    /**
     * Compose using a MenuProviderInterface
     *
     * @param class-string<MenuProviderInterface>|MenuProviderInterface $provider
     */
    public function provider(string|MenuProviderInterface $provider): self
    {
        $instance = is_string($provider) ? app($provider) : $provider;
        $instance->build($this);
        return $this;
    }

    /**
     * @return list<MenuItem>
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function toArray(): array
    {
        $result = [];
        foreach ($this->items as $item) {
            $itemData = $item->toArray();
            if ($itemData !== null) {
                $result[] = $itemData;
            }
        }
        return $result;
    }

    /**
     * Populate an existing MenuDropdown instance with this builder's configuration and visible items.
     */
    public function populate(MenuDropdown $dropdown): MenuDropdown
    {
        $dropdown->clearItems();
        $dropdown->position($this->position);
        $dropdown->visible($this->visible);

        if ($this->width !== null) {
            $dropdown->width(Size::from($this->width));
        }

        if (!empty($this->triggerConfig)) {
            $label = $this->triggerConfig['label'] ?? '☰';
            $icon = $this->triggerConfig['icon'] ?? null;
            $image = $this->triggerConfig['image'] ?? null;
            $alt = $this->triggerConfig['alt'] ?? null;
            $style = $this->triggerConfig['style'] ?? 'default';

            if ($image !== null) {
                $dropdown->triggerImage($image, $alt ?? ($label ?? 'Menu'), $label, $style);
            } else {
                $dropdown->trigger($label ?? '☰', $icon, $style);
            }
        }

        foreach ($this->items as $item) {
            $itemData = $item->toArray();
            if ($itemData === null) {
                continue;
            }

            if (($itemData['type'] ?? '') === 'separator') {
                $dropdown->separator();
                continue;
            }

            $label = $itemData['label'] ?? '';
            $icon = $itemData['icon'] ?? null;
            $url = $itemData['url'] ?? null;
            $action = $itemData['action'] ?? null;
            $params = $itemData['params'] ?? [];
            $submenu = $itemData['submenu'] ?? [];

            if ($url !== null) {
                $dropdown->link($label, $url, $icon);
            } elseif (!empty($submenu)) {
                $dropdown->item($label, $action, $params, $icon, $submenu);
            } else {
                $dropdown->item($label, $action, $params, $icon);
            }
        }

        return $dropdown;
    }

    /**
     * Render as a new MenuDropdown component.
     */
    public function render(?string $id = null): MenuDropdown
    {
        $componentId = $id ?? ($this->id ?? 'menu_' . uniqid());
        $dropdown = UI::menuDropdown($componentId);
        return $this->populate($dropdown);
    }
}
