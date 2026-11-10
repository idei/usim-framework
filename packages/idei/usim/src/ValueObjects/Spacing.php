<?php

// ValueObjects/Spacing.php

namespace Idei\Usim\ValueObjects;

final class Spacing
{
    public const array SCALE = [
        'none' => '0',
        '0' => '0',
        'xs' => '4px',
        's' => '8px',
        'sm' => '8px',
        'm' => '16px',
        'md' => '16px',
        'l' => '24px',
        'lg' => '24px',
        'xl' => '32px',
        '2xl' => '48px',
        'xxl' => '48px',
        '3xl' => '64px',
        'xxxl' => '64px',
    ];

    private function __construct(private readonly string $value) {}

    // Factory methods
    public static function none(): self
    {
        return new self(self::SCALE['none']);
    }

    public static function xs(): self
    {
        return new self(self::SCALE['xs']);
    }

    public static function s(): self
    {
        return new self(self::SCALE['s']);
    }

    public static function sm(): self
    {
        return new self(self::SCALE['sm']);
    }

    public static function m(): self
    {
        return new self(self::SCALE['m']);
    }

    public static function md(): self
    {
        return new self(self::SCALE['md']);
    }

    public static function l(): self
    {
        return new self(self::SCALE['l']);
    }

    public static function lg(): self
    {
        return new self(self::SCALE['lg']);
    }

    public static function xl(): self
    {
        return new self(self::SCALE['xl']);
    }

    public static function xxl(): self
    {
        return new self(self::SCALE['xxl']);
    }

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
        ?Spacing $right = null,
        ?Spacing $bottom = null,
        ?Spacing $left = null,
    ): self {
        $t = (string) self::from($top);
        if ($right === null) {
            return new self($t);
        }

        $r = (string) self::from($right);
        if ($bottom === null) {
            return new self("$t $r");
        }

        $b = (string) self::from($bottom);
        if ($left === null) {
            return new self("$t $r $b");
        }

        $l = (string) self::from($left);

        return new self("$t $r $b $l");
    }

    // Resolver universal — igual que Size
    public static function from(Spacing|string|int $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        if (is_int($value)) {
            return self::px($value);
        }

        $trimmed = strtolower(trim($value));
        if (isset(self::SCALE[$trimmed])) {
            return new self(self::SCALE[$trimmed]);
        }

        return new self($value);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
