<?php

namespace Idei\Usim\Widgets;

use Idei\Usim\Components\Container;
use Idei\Usim\Contracts\UIElement;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Spacing;

/**
 * Declarative vertical layout widget.
 */
class Column extends Widget
{
    /**
     * @param list<Widget> $children
     */
    public function __construct(
        protected array $children = [],
        protected int|Spacing|null $gap = null,
        protected ?string $alignItems = null,
        protected ?string $justifyContent = null,
        protected bool $plain = true,
        protected int|Spacing|null $padding = null,
        ?string $key = null
    ) {
        parent::__construct($key);
    }

    public function mount(Container $parent, string $contextClass): UIElement
    {
        $name = $this->key ?? 'col_' . substr(md5(uniqid((string)mt_rand(), true)), 0, 8);
        $container = UI::container($name, $contextClass)
            ->layout(LayoutType::VERTICAL);

        if ($this->plain) {
            $container->plain();
        }

        if ($this->gap !== null) {
            $container->gap(is_int($this->gap) ? Spacing::px($this->gap) : $this->gap);
        }

        if ($this->padding !== null) {
            $container->padding(is_int($this->padding) ? Spacing::px($this->padding) : $this->padding);
        }

        if ($this->alignItems !== null) {
            $container->alignItems($this->alignItems);
        }

        if ($this->justifyContent !== null) {
            $container->justifyContent($this->justifyContent);
        }

        foreach ($this->children as $child) {
            if ($child instanceof Widget) {
                $child->mount($container, $contextClass);
            }
        }

        $parent->add($container);
        return $container;
    }
}
