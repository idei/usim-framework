<?php

namespace Idei\Usim\Navigation;

use Closure;
use Idei\Usim\Screen;
use Illuminate\Support\Facades\Gate;

class MenuItem
{
    protected ?string $label = null;
    protected ?string $icon = null;
    protected ?string $url = null;
    protected ?string $action = null;
    /** @var array<string, mixed> */
    protected array $params = [];
    /** @var class-string<Screen>|null */
    protected ?string $screenClass = null;
    protected bool $isSeparator = false;
    /** @var list<MenuItem> */
    protected array $children = [];
    /** @var list<Closure|bool> */
    protected array $conditions = [];
    /** @var list<string> */
    protected array $permissions = [];

    public function __construct(?string $label = null)
    {
        $this->label = $label;
    }

    public static function make(?string $label = null): self
    {
        return new self($label);
    }

    public static function separator(): self
    {
        $item = new self();
        $item->isSeparator = true;
        return $item;
    }

    public function label(string $label): self
    {
        $this->label = $label;
        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function icon(?string $icon): self
    {
        $this->icon = $icon;
        return $this;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function url(string $url): self
    {
        $this->url = $url;
        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    /**
     * @param array<string, mixed> $params
     */
    public function action(string $action, array $params = []): self
    {
        $this->action = $action;
        $this->params = $params;
        return $this;
    }

    public function getAction(): ?string
    {
        return $this->action;
    }

    /**
     * @return array<string, mixed>
     */
    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * @param class-string<Screen> $screenClass
     */
    public function screen(string $screenClass, ?string $label = null, ?string $icon = null): self
    {
        $this->screenClass = $screenClass;
        if ($label !== null) {
            $this->label = $label;
        }
        if ($icon !== null) {
            $this->icon = $icon;
        }
        return $this;
    }

    /**
     * @return class-string<Screen>|null
     */
    public function getScreenClass(): ?string
    {
        return $this->screenClass;
    }

    public function when(Closure|bool|null $condition): self
    {
        if ($condition !== null) {
            $this->conditions[] = $condition;
        }
        return $this;
    }

    public function can(string $permission): self
    {
        $this->permissions[] = $permission;
        return $this;
    }

    public function addChild(MenuItem $child): self
    {
        $this->children[] = $child;
        return $this;
    }

    /**
     * @return list<MenuItem>
     */
    public function getChildren(): array
    {
        return $this->children;
    }

    public function isSeparator(): bool
    {
        return $this->isSeparator;
    }

    /**
     * Evaluates all visibility conditions, permissions, and screen access dynamically.
     */
    public function isVisible(): bool
    {
        // 1. Evaluate explicit conditions
        foreach ($this->conditions as $condition) {
            $result = $condition instanceof Closure ? $condition() : $condition;
            if (!$result) {
                return false;
            }
        }

        // 2. Evaluate Laravel permissions / gates
        foreach ($this->permissions as $permission) {
            if (!Gate::allows($permission)) {
                return false;
            }
        }

        // 3. Evaluate Screen permissions dynamically using checkAccess()
        if ($this->screenClass !== null) {
            if (!class_exists($this->screenClass) || !is_subclass_of($this->screenClass, Screen::class)) {
                return false;
            }

            /** @var array{allowed: bool} $access */
            $access = $this->screenClass::checkAccess();
            if (!($access['allowed'] ?? false)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Resolves metadata and converts to an array structure suitable for MenuDropdown.
     *
     * @return array<string, mixed>|null Returns null if not visible.
     */
    public function toArray(): ?array
    {
        if (!$this->isVisible()) {
            return null;
        }

        if ($this->isSeparator) {
            return ['type' => 'separator'];
        }

        $label = $this->label;
        $icon = $this->icon;
        $url = $this->url;

        // Auto-resolve metadata from screen class if needed
        if ($this->screenClass !== null) {
            $label = $label ?? $this->screenClass::getMenuLabel();
            $icon = $icon ?? $this->screenClass::getMenuIcon();
            $url = $url ?? $this->screenClass::getRoutePath();
        }

        $visibleChildren = [];
        foreach ($this->children as $child) {
            $childArray = $child->toArray();
            if ($childArray !== null) {
                $visibleChildren[] = $childArray;
            }
        }

        $data = [
            'label' => $label ?? '',
            'icon' => $icon,
            'url' => $url,
            'action' => $this->action,
            'params' => $this->params,
        ];

        if (!empty($visibleChildren)) {
            $data['submenu'] = $visibleChildren;
        }

        return $data;
    }
}
