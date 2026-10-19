<?php

namespace Idei\Usim\ValueObjects;

final readonly class ImagePlaceholder
{
    public function __construct(
        public string $id,
        public int $width,
        public int $height,
        public ?string $caption,
        public string $backgroundColor,
        public string $textColor,
        public string $seedPrefix,
    ) {}
}
