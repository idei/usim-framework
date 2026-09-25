<?php

namespace Idei\Usim\Widgets;

use Idei\Usim\Components\Container;
use Idei\Usim\Contracts\UIElement;

/**
 * Base abstract class for all declarative Widgets in USIM.
 * Follows the Single Responsibility and Open/Closed principles.
 */
abstract class Widget
{
    public function __construct(
        protected ?string $key = null
    ) {}

    public function getKey(): ?string
    {
        return $this->key;
    }

    /**
     * Mounts this widget and its descendants into the target parent Container.
     */
    abstract public function mount(Container $parent, string $contextClass): UIElement;
}
