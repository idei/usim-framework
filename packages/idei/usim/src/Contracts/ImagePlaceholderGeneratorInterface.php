<?php

namespace Idei\Usim\Contracts;

use Idei\Usim\ValueObjects\ImagePlaceholder;

interface ImagePlaceholderGeneratorInterface
{
    public function generate(ImagePlaceholder $placeholder): string;
}
