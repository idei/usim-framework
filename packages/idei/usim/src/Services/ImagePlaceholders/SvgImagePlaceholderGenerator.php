<?php

namespace Idei\Usim\Services\ImagePlaceholders;

use Idei\Usim\Contracts\ImagePlaceholderGeneratorInterface;
use Idei\Usim\ValueObjects\ImagePlaceholder;
use InvalidArgumentException;

class SvgImagePlaceholderGenerator implements ImagePlaceholderGeneratorInterface
{
    public function generate(ImagePlaceholder $placeholder): string
    {
        $this->assertColor($placeholder->backgroundColor);
        $this->assertColor($placeholder->textColor);

        $caption = htmlspecialchars($placeholder->caption ?? '', ENT_QUOTES | ENT_XML1, 'UTF-8');
        $svg = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d"><rect width="100%%" height="100%%" fill="%s"/><text x="50%%" y="50%%" dominant-baseline="middle" text-anchor="middle" font-family="sans-serif" font-size="24" fill="%s">%s</text></svg>',
            $placeholder->width,
            $placeholder->height,
            $placeholder->width,
            $placeholder->height,
            $placeholder->backgroundColor,
            $placeholder->textColor,
            $caption,
        );

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    private function assertColor(string $color): void
    {
        if (preg_match('/^#[0-9a-fA-F]{3}(?:[0-9a-fA-F]{3})?$/D', $color) !== 1) {
            throw new InvalidArgumentException('Image placeholder colors must be 3- or 6-digit hex colors.');
        }
    }
}
