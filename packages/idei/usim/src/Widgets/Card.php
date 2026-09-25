<?php

namespace Idei\Usim\Widgets;

use Idei\Usim\Components\Container;
use Idei\Usim\Contracts\UIElement;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Spacing;

/**
 * Declarative Card widget.
 * Renders a structured, styled card container supporting title, subtitle and child widget.
 */
class Card extends Widget
{
    public function __construct(
        protected ?string $title = null,
        protected ?string $subtitle = null,
        protected ?string $icon = null,
        protected ?Widget $child = null,
        protected int|Spacing|null $padding = 16,
        ?string $key = null
    ) {
        parent::__construct($key);
    }

    public function mount(Container $parent, string $contextClass): UIElement
    {
        $name = $this->key ?? 'card_' . substr(md5(uniqid((string)mt_rand(), true)), 0, 8);
        $card = UI::container($name, $contextClass)
            ->shadow(1)
            ->rounded(8)
            ->padding(is_int($this->padding) ? Spacing::px($this->padding) : $this->padding)
            ->gap(Spacing::px(10));

        if ($this->title !== null) {
            $card->title($this->title);
        }

        if ($this->subtitle !== null) {
            $card->add(
                UI::label($name . '_subtitle')
                    ->text($this->subtitle)
                    ->style('info')
            );
        }

        if ($this->child !== null) {
            $this->child->mount($card, $contextClass);
        }

        $parent->add($card);
        return $card;
    }
}
