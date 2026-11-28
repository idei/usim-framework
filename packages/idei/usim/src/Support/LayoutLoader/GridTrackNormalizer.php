<?php

declare(strict_types=1);

namespace Idei\Usim\Support\LayoutLoader;

/**
 * Pure CSS Grid track normalization, column counting, span resolution, and cell placement computation.
 */
final class GridTrackNormalizer
{
    /**
     * @param  array<string, mixed>  $rule
     * @return array{gridColumn: string, gridRow: ?string, colSpan: int}
     */
    public function resolveGridPlacement(array $rule, int $defaultSpan, int $columnCount): array
    {
        [$rowSpan, $colSpan] = $this->resolveSpan($rule['span'] ?? null, $defaultSpan, $columnCount);
        $at = $rule['at'] ?? null;

        if (is_array($at) && count($at) >= 2) {
            $atValues = array_values($at);
            $startRow = is_numeric($atValues[0]) ? (int) $atValues[0] : 1;
            $startCol = is_numeric($atValues[1]) ? (int) $atValues[1] : 1;

            return [
                'gridColumn' => $colSpan > 1 ? "{$startCol} / span {$colSpan}" : (string) $startCol,
                'gridRow' => $rowSpan > 1 ? "{$startRow} / span {$rowSpan}" : (string) $startRow,
                'colSpan' => $colSpan,
            ];
        }

        return [
            'gridColumn' => "span {$colSpan}",
            'gridRow' => $rowSpan > 1 ? "span {$rowSpan}" : null,
            'colSpan' => $colSpan,
        ];
    }

    /**
     * @return array{0: int, 1: int} [rowSpan, colSpan]
     */
    public function resolveSpan(mixed $span, int $defaultColSpan, int $columnCount): array
    {
        if ($span === null) {
            return [1, $defaultColSpan];
        }

        if ($span === 'full') {
            return [1, $columnCount];
        }

        if (is_int($span) || (is_string($span) && is_numeric($span))) {
            return [1, max(1, (int) $span)];
        }

        if (is_array($span)) {
            return $this->resolveArraySpan($span, $defaultColSpan, $columnCount);
        }

        return [1, $defaultColSpan];
    }

    public function resolveDefaultColSpan(mixed $defaultSpan, int $columnCount): int
    {
        if ($defaultSpan === 'full') {
            return $columnCount;
        }

        if (is_int($defaultSpan) || (is_string($defaultSpan) && is_numeric($defaultSpan))) {
            return max(1, (int) $defaultSpan);
        }

        return 1;
    }

    public function resolveColumnCount(mixed $cols): int
    {
        if (is_int($cols)) {
            return max(1, $cols);
        }

        if (is_array($cols)) {
            return max(1, count($cols));
        }

        if (is_string($cols)) {
            return $this->resolveStringColumnCount($cols);
        }

        return 1;
    }

    public function normalizeTrackList(mixed $tracks): string
    {
        if (is_int($tracks)) {
            return "repeat({$tracks}, 1fr)";
        }

        if (is_string($tracks)) {
            $trimmed = trim($tracks);

            return ctype_digit($trimmed) ? "repeat({$trimmed}, 1fr)" : $trimmed;
        }

        if (is_array($tracks)) {
            return $this->normalizeArrayTrackList($tracks);
        }

        return '';
    }

    public function normalizeTrackToken(string|int|float $token): string
    {
        if (is_int($token) || is_float($token)) {
            return "{$token}px";
        }

        $trimmed = trim($token);

        return is_numeric($trimmed) ? "{$trimmed}px" : $trimmed;
    }

    /**
     * @param  array<int|string, mixed>  $span
     * @return array{0: int, 1: int}
     */
    private function resolveArraySpan(array $span, int $defaultColSpan, int $columnCount): array
    {
        $values = array_values($span);
        $rowSpan = isset($values[0]) && is_numeric($values[0]) ? max(1, (int) $values[0]) : 1;
        $colRaw = $values[1] ?? $defaultColSpan;

        if ($colRaw === 'full') {
            return [$rowSpan, $columnCount];
        }

        $colSpan = is_numeric($colRaw) ? max(1, (int) $colRaw) : $defaultColSpan;

        return [$rowSpan, $colSpan];
    }

    private function resolveStringColumnCount(string $cols): int
    {
        $trimmed = trim($cols);
        if (ctype_digit($trimmed)) {
            return max(1, (int) $trimmed);
        }

        if (preg_match('/^repeat\(\s*(\d+)\s*,/i', $trimmed, $matches) === 1) {
            return max(1, (int) $matches[1]);
        }

        $parts = preg_split('/\s+/', $trimmed);

        return is_array($parts) ? max(1, count(array_filter($parts))) : 1;
    }

    /**
     * @param  array<int|string, mixed>  $tracks
     */
    private function normalizeArrayTrackList(array $tracks): string
    {
        $tokens = [];
        foreach ($tracks as $track) {
            if (is_string($track) || is_int($track) || is_float($track)) {
                $tokens[] = $this->normalizeTrackToken($track);
            }
        }

        return implode(' ', $tokens);
    }
}
