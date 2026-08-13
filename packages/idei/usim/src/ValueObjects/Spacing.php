<?php
// ValueObjects/Spacing.php
namespace Idei\Usim\ValueObjects;

final class Spacing
{
    private function __construct(private readonly string $value)
    {
    }

    // Factory methods
    public static function px(int $value): self
    {
        return new self("{$value}px");
    }

    public static function rem(int|float $value): self
    {
        return new self("{$value}rem");
    }

    public static function em(int|float $value): self
    {
        return new self("{$value}em");
    }

    public static function pct(int|float $value): self
    {
        return new self("{$value}%");
    }

    public static function zero(): self
    {
        return new self('0');
    }

    public static function auto(): self
    {
        return new self('auto');
    }

    // Shorthand multi-value method for spacing, similar to CSS (1/2/3/4 values)
    public static function each(
        Spacing $top,
        Spacing|null $right = null,
        Spacing|null $bottom = null,
        Spacing|null $left = null,
    ): self {
        $t = (string) self::from($top);
        if ($right === null)
            return new self($t);

        $r = (string) self::from($right);
        if ($bottom === null)
            return new self("$t $r");

        $b = (string) self::from($bottom);
        if ($left === null)
            return new self("$t $r $b");

        $l = (string) self::from($left);
        return new self("$t $r $b $l");
    }

    // Resolver universal — igual que Size
    public static function from(Spacing|string $value): self
    {
        if ($value instanceof self)
            return $value;

        return new self($value);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
