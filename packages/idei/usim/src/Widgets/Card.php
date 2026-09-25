<?php

namespace Idei\Usim\Widgets;

use Idei\Usim\Components\Container;
use Idei\Usim\Contracts\UIElement;
use Idei\Usim\UI;

/**
 * Declarative Card widget.
 */
class Card extends Widget
{
    public function __construct(
        protected ?string $title = null,
        protected ?string $subtitle = null,
        protected ?string $icon = null,
        protected ?Widget $child = null,
        ?string $key = null
    ) {
        parent::__construct($key);
    }

    public function mount(Container $parent, string $contextClass): UIElement
    {
        $name = $this->key ?? 'card_' . substr(md5(uniqid((string)mt_rand(), true)), 0, 8);
        $card = UI::card($name);

        if ($this->title !== null) {
            $card->title($this->title);
        }

        if ($this->subtitle !== null) {
            $card->subtitle($this->subtitle);
        }

        if ($this->icon !== null) {
            $card->icon($this->icon);
        }

        if ($this->child !== null) {
            $this->child->mount($card, $contextClass);
        }

        $parent->add($card);
        return $card;
    }
}
