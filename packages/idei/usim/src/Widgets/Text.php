<?php

namespace Idei\Usim\Widgets;

use Idei\Usim\Components\Container;
use Idei\Usim\Contracts\UIElement;
use Idei\Usim\UI;

/**
 * Declarative Text / Label widget.
 */
class Text extends Widget
{
    public function __construct(
        protected string $text,
        protected string $style = 'default',
        protected ?string $fontSize = null,
        protected bool $markdown = false,
        protected bool $visible = true,
        ?string $key = null
    ) {
        parent::__construct($key);
    }

    public function mount(Container $parent, string $contextClass): UIElement
    {
        $name = $this->key ?? 'txt_' . substr(md5(uniqid((string)mt_rand(), true)), 0, 8);
        $label = new \Idei\Usim\Components\Label($name, $contextClass);
        $label->text($this->text)
            ->style($this->style)
            ->visible($this->visible);

        if ($this->fontSize !== null) {
            $label->fontSize($this->fontSize);
        }

        if ($this->markdown) {
            $label->markdown();
        }

        $parent->add($label);
        return $label;
    }
}
