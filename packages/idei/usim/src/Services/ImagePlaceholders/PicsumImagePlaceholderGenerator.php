<?php

namespace Idei\Usim\Services\ImagePlaceholders;

use Idei\Usim\Contracts\ImagePlaceholderGeneratorInterface;
use Idei\Usim\ValueObjects\ImagePlaceholder;

class PicsumImagePlaceholderGenerator implements ImagePlaceholderGeneratorInterface
{
    public function generate(ImagePlaceholder $placeholder): string
    {
        $seed = rawurlencode($placeholder->seedPrefix.$placeholder->id);

        return sprintf(
            'https://picsum.photos/seed/%s/%d/%d',
            $seed,
            $placeholder->width,
            $placeholder->height,
        );
    }
}
