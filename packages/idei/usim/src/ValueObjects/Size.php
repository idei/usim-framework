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

    // Shorthand for common sizes

    public static function full(): self
    {
        return self::pct(100);
    }
    public static function half(): self
    {
        return self::pct(50);
    }
    public static function third(): self
    {
        return self::pct(33.333);
    }
    public static function quarter(): self
    {
        return self::pct(25);
    }
    public static function screen(): self
    {
        return new self('100dvh');
    }

    public static function from(Size|int|string $value): self
    {
        if ($value instanceof self)
            return $value;
        if (\is_int($value))
            return self::px($value);
        return new self($value);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
