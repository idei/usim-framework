<?php

declare(strict_types=1);

namespace Idei\Usim\Support\LayoutLoader;

/**
 * Tracks auto-flow grid rows to compute `grid_template_rows` when children declare `row` or `row_height`.
 */
final class GridRowTrackAccumulator
{
    private bool $hasChildRowTrack = false;

    /** @var list<string> */
    private array $rowTracks = [];

    private int $currentColCursor = 0;

    private string $currentRowTrack = 'auto';

    public function __construct(
        private readonly int $columnCount,
        private readonly GridTrackNormalizer $trackNormalizer,
    ) {}

    /**
     * @param  array<string, mixed>  $rule
     */
    public function recordVisibleChild(int $colSpan, array $rule): void
    {
        $effectiveColSpan = min(max($colSpan, 1), $this->columnCount);

        if ($this->currentColCursor + $effectiveColSpan > $this->columnCount) {
            $this->flushCurrentRow();
        }

        $rawRowTrack = $rule['row'] ?? $rule['row_height'] ?? null;
        if (is_string($rawRowTrack) || is_int($rawRowTrack) || is_float($rawRowTrack)) {
            $this->currentRowTrack = $this->trackNormalizer->normalizeTrackToken($rawRowTrack);
            $this->hasChildRowTrack = true;
        }

        $this->currentColCursor += $effectiveColSpan;
        if ($this->currentColCursor >= $this->columnCount) {
            $this->flushCurrentRow();
        }
    }

    public function buildTemplateRows(): ?string
    {
        if (! $this->hasChildRowTrack) {
            return null;
        }

        $tracks = $this->rowTracks;
        if ($this->currentColCursor > 0) {
            $tracks[] = $this->currentRowTrack;
        }

        return $tracks !== [] ? implode(' ', $tracks) : null;
    }

    private function flushCurrentRow(): void
    {
        $this->rowTracks[] = $this->currentRowTrack;
        $this->currentColCursor = 0;
        $this->currentRowTrack = 'auto';
    }
}
