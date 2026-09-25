<?php

namespace Idei\Usim\Widgets;

use Idei\Usim\Components\Container;
use Idei\Usim\Contracts\UIElement;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;

/**
 * Declarative Button widget.
 */
class Button extends Widget
{
    /**
     * @param array<string, mixed> $params
     */
    public function __construct(
        protected string $label,
        protected ?string $onPressed = null,
        protected array $params = [],
        protected string $style = 'primary',
        protected ?string $icon = null,
        protected bool $disabled = false,
        protected ?string $tooltip = null,
        protected int|Size|null $width = null,
        ?string $key = null
    ) {
        parent::__construct($key);
    }

    public function mount(Container $parent, string $contextClass): UIElement
    {
        $name = $this->key ?? 'btn_' . substr(md5(uniqid((string)mt_rand(), true)), 0, 8);
        $button = new \Idei\Usim\Components\Button($name, $contextClass);
        $button->label($this->label)
            ->style($this->style)
            ->enabled(!$this->disabled);

        if ($this->onPressed !== null) {
            $button->action($this->onPressed, $this->params);
        }

        if ($this->icon !== null) {
            $button->icon($this->icon);
        }

        if ($this->tooltip !== null) {
            $button->tooltip($this->tooltip);
        }

        if ($this->width !== null) {
            $button->width(is_int($this->width) ? Size::px($this->width) : $this->width);
        }

        $parent->add($button);
        return $button;
    }
}
