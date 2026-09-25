<?php

namespace Idei\Usim\Widgets;

use Idei\Usim\Components\Container;
use Idei\Usim\Contracts\UIElement;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;

/**
 * Declarative Box/Container widget for spacing, sizing and styling.
 */
class Box extends Widget
{
    public function __construct(
        protected ?Widget $child = null,
        protected int|Spacing|null $padding = null,
        protected int|Spacing|null $margin = null,
        protected int|Size|string|null $width = null,
        protected int|Size|string|null $height = null,
        protected int|Size|string|null $maxWidth = null,
        protected int|Size|string|null $minHeight = null,
        protected bool $plain = false,
        protected int|bool|null $shadow = null,
        protected int|string|null $rounded = null,
        protected ?string $title = null,
        protected bool $centerHorizontal = false,
        ?string $key = null
    ) {
        parent::__construct($key);
    }

    public function mount(Container $parent, string $contextClass): UIElement
    {
        $name = $this->key ?? 'box_' . substr(md5(uniqid((string)mt_rand(), true)), 0, 8);
        $container = UI::container($name, $contextClass);

        if ($this->plain) {
            $container->plain();
        }

        if ($this->title !== null) {
            $container->title($this->title);
        }

        if ($this->centerHorizontal) {
            $container->centerHorizontal();
        }

        if ($this->padding !== null) {
            $container->padding(is_int($this->padding) ? Spacing::px($this->padding) : $this->padding);
        }

        if ($this->margin !== null) {
            $container->margin(is_int($this->margin) ? Spacing::px($this->margin) : $this->margin);
        }

        if ($this->width !== null) {
            $container->width(is_int($this->width) ? Size::px($this->width) : (is_string($this->width) ? Size::custom($this->width) : $this->width));
        }

        if ($this->height !== null) {
            $container->height(is_int($this->height) ? Size::px($this->height) : (is_string($this->height) ? Size::custom($this->height) : $this->height));
        }

        if ($this->maxWidth !== null) {
            $container->maxWidth(is_int($this->maxWidth) ? Size::px($this->maxWidth) : (is_string($this->maxWidth) ? Size::custom($this->maxWidth) : $this->maxWidth));
        }

        if ($this->minHeight !== null) {
            $container->minHeight(is_int($this->minHeight) ? Size::px($this->minHeight) : (is_string($this->minHeight) ? Size::custom($this->minHeight) : $this->minHeight));
        }

        if ($this->shadow !== null) {
            $container->shadow($this->shadow);
        }

        if ($this->rounded !== null) {
            $container->rounded($this->rounded);
        }

        if ($this->child !== null) {
            $this->child->mount($container, $contextClass);
        }

        $parent->add($container);
        return $container;
    }
}
