<?php

namespace Idei\Usim\ValueObjects;

final class Size
{
    private function __construct(
        private readonly string $value
    ) {
    }

    // Factory methods for common size units and keywords
    public static function px(int $value): self
    {
        return new self("{$value}px");
    }

    public static function pct(int|float $value): self
    {
        return new self("{$value}%");
    }

    public static function em(int|float $value): self
    {
        return new self("{$value}em");
    }

    public static function rem(int|float $value): self
    {
        return new self("{$value}rem");
    }

    public static function vw(int|float $value): self
    {
        return new self("{$value}vw");
    }

    public static function vh(int|float $value): self
    {
        return new self("{$value}vh");
    }

    public static function auto(): self
    {
        return new self('auto');
    }

    public static function fitContent(): self
    {
        return new self('fit-content');
    }

    public static function minContent(): self
    {
        return new self('min-content');
    }

    public static function maxContent(): self
    {
        return new self('max-content');
    }

    /**
     * Resolves Size|int into a Size instance.
     * An int is always assumed to be in px.
     */
    public static function from(Size|int $value): self
    {
        return $value instanceof self ? $value : self::px($value);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
