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

        $fontSize = max(12, min(24, (int) floor($placeholder->width / 12)));
        $lineHeight = (int) ceil($fontSize * 1.25);
        $maxCharacters = max(1, (int) floor(($placeholder->width - 32) / ($fontSize * 0.65)));
        $maxLines = max(1, (int) floor(($placeholder->height - 24) / $lineHeight));
        $lines = $this->wrapCaption($placeholder->caption ?? '', $maxCharacters);

        if (count($lines) > $maxLines) {
            $lines = array_slice($lines, 0, $maxLines);
            $lastLine = array_pop($lines);
            $lines[] = rtrim((string) $lastLine).'…';
        }

        $firstLineOffset = -((count($lines) - 1) * $lineHeight / 2);
        $textLines = '';
        foreach ($lines as $index => $line) {
            $escapedLine = htmlspecialchars($line, ENT_QUOTES | ENT_XML1, 'UTF-8');
            $dy = $index === 0 ? $firstLineOffset : $lineHeight;
            $textLines .= sprintf('<tspan x="50%%" dy="%.1f">%s</tspan>', $dy, $escapedLine);
        }

        $svg = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d"><rect width="100%%" height="100%%" fill="%s"/><text x="50%%" y="50%%" dominant-baseline="middle" text-anchor="middle" font-family="sans-serif" font-size="%d" fill="%s">%s</text></svg>',
            $placeholder->width,
            $placeholder->height,
            $placeholder->width,
            $placeholder->height,
            $placeholder->backgroundColor,
            $fontSize,
            $placeholder->textColor,
            $textLines,
        );

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    /**
     * @return list<string>
     */
    private function wrapCaption(string $caption, int $maxCharacters): array
    {
        $words = preg_split('/\s+/u', trim($caption), -1, PREG_SPLIT_NO_EMPTY);
        if ($words === false) {
            throw new InvalidArgumentException('Image placeholder caption must be valid UTF-8.');
        }

        $lines = [];
        $line = '';
        foreach ($words as $word) {
            preg_match_all('/\X/u', $word, $matches);
            $characters = $matches[0];

            while (count($characters) > $maxCharacters) {
                if ($line !== '') {
                    $lines[] = $line;
                    $line = '';
                }

                $lines[] = implode('', array_slice($characters, 0, $maxCharacters));
                $characters = array_slice($characters, $maxCharacters);
            }

            $remainingWord = implode('', $characters);
            if ($remainingWord === '') {
                continue;
            }

            $candidate = $line === '' ? $remainingWord : "{$line} {$remainingWord}";
            preg_match_all('/\X/u', $candidate, $candidateMatches);
            if ($line !== '' && count($candidateMatches[0]) > $maxCharacters) {
                $lines[] = $line;
                $line = $remainingWord;
            } else {
                $line = $candidate;
            }
        }

        if ($line !== '') {
            $lines[] = $line;
        }

        return $lines;
    }

    private function assertColor(string $color): void
    {
        if (preg_match('/^#[0-9a-fA-F]{3}(?:[0-9a-fA-F]{3})?$/D', $color) !== 1) {
            throw new InvalidArgumentException('Image placeholder colors must be 3- or 6-digit hex colors.');
        }
    }
}
