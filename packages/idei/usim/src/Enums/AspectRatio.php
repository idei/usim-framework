<?php

namespace Idei\Usim\Enums;

use InvalidArgumentException;

/**
 * Aspect ratio options for UI components
 */
enum AspectRatio: string
{
    case _1_1 = '1:1';
    case _4_3 = '4:3';
    case _3_4 = '3:4';
    case _16_9 = '16:9';
    case _9_16 = '9:16';

    /**
     * Get all aspect ratio values as an array of strings.
     *
     * @return array<string> An array of aspect ratio strings.
     */
    public static function all(): array
    {
        $all = self::cases();
        return array_map(fn($case) => $case->value, $all);
    }

    public static function default(): self
    {
        return self::_16_9;
    }

    /**
     * Calculate the missing dimension from the aspect ratio.
     *
     * If both dimensions are provided, they are returned unchanged.
     *
     * @return array{int, int} The calculated width and height.
     */
    public function dimensionsFor(?int $width = null, ?int $height = null): array
    {
        if (($width !== null && $width <= 0) || ($height !== null && $height <= 0)) {
            throw new InvalidArgumentException('Dimensions must be positive integers.');
        }

        if ($width === null && $height === null) {
            return [0, 0];
        }

        $aspectRatio = $this->widthRatio() / $this->heightRatio();

        if ($width === null) {
            $width = (int) round($height * $aspectRatio);
        } elseif ($height === null) {
            $height = (int) round($width * (1 / $aspectRatio));
        }

        return [$width, $height];
    }

    public function widthRatio(): int
    {
        return (int) explode(':', $this->value, 2)[0];
    }

    public function heightRatio(): int
    {
        return (int) explode(':', $this->value, 2)[1];
    }
}
