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
    /** @var array<string, int> */
    protected static array $counters = [];

    public static function resetCounters(): void
    {
        self::$counters = [];
    }

    public static function nextAutoKey(string $prefix): string
    {
        self::$counters[$prefix] = (self::$counters[$prefix] ?? 0) + 1;
        return $prefix . '_' . self::$counters[$prefix];
    }

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
