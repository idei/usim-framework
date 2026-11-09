<?php

namespace Idei\Usim\Support;

use Idei\Usim\Components\Container;
use Idei\Usim\Components\UIComponent;
use Idei\Usim\Contracts\UIElement;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;
use Illuminate\Support\Str;
use InvalidArgumentException;
use ReflectionClass;
use Symfony\Component\Yaml\Yaml;

/**
 * Loads declarative container layout definitions (.layout.yaml) and applies
 * them to a Container and its child UIElement instances.
 */
class ContainerLayoutLoader
{
    /**
     * In-memory cache of parsed YAML layout files keyed by "realPath:mtime".
     *
     * @var array<string, array<string, mixed>>
     */
    protected static array $parsedCache = [];

    /**
     * Resolve and parse a YAML layout file.
     *
     * @param  string|null  $path  Explicit path, filename, or null to infer from calling Screen class
     * @param  string|null  $callingContext  Calling Screen class name
     * @return array<string, mixed>
     */
    public static function loadFile(?string $path = null, ?string $callingContext = null): array
    {
        $resolvedPath = self::resolvePath($path, $callingContext);
        $mtime = @filemtime($resolvedPath) ?: 0;
        $cacheKey = "{$resolvedPath}:{$mtime}";

        if (isset(self::$parsedCache[$cacheKey])) {
            return self::$parsedCache[$cacheKey];
        }

        $parsed = Yaml::parseFile($resolvedPath);
        if (! is_array($parsed)) {
            throw new InvalidArgumentException("Layout file [{$resolvedPath}] must contain a valid YAML mapping.");
        }

        /** @var array<string, mixed> $normalized */
        $normalized = $parsed;

        return self::$parsedCache[$cacheKey] = $normalized;
    }

    /**
     * Resolve the filesystem path for a layout file reference.
     */
    public static function resolvePath(?string $path = null, ?string $callingContext = null): string
    {
        $contextDir = self::resolveContextDirectory($callingContext);

        if ($path === null || trim($path) === '') {
            if ($callingContext === null || $contextDir === null) {
                throw new InvalidArgumentException('Cannot auto-resolve layout file without a valid Screen context.');
            }

            $defaultFile = $contextDir.'/'.Str::kebab(class_basename($callingContext)).'.layout.yaml';
            if (is_file($defaultFile)) {
                return (string) realpath($defaultFile);
            }

            throw new InvalidArgumentException("Default layout file [{$defaultFile}] not found for [{$callingContext}].");
        }

        $trimmed = trim($path);
        $candidates = [$trimmed];

        if (! str_ends_with($trimmed, '.yaml') && ! str_ends_with($trimmed, '.yml')) {
            $candidates[] = "{$trimmed}.layout.yaml";
            $candidates[] = "{$trimmed}.yaml";
        }

        if ($contextDir !== null) {
            foreach ($candidates as $candidate) {
                $relativeToContext = $contextDir.'/'.ltrim($candidate, '/');
                if (is_file($relativeToContext)) {
                    return (string) realpath($relativeToContext);
                }
            }
        }

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return (string) realpath($candidate);
            }

            if (function_exists('base_path')) {
                $fromBase = base_path($candidate);
                if (is_file($fromBase)) {
                    return (string) realpath($fromBase);
                }
            }
        }

        throw new InvalidArgumentException("Layout file [{$path}] not found.");
    }

    /**
     * Extract ordered slot names (string identifiers) declared in the layout's children list.
     *
     * @param  array<string, mixed>  $definition
     * @return list<string>
     */
    public static function extractChildSlotNames(array $definition): array
    {
        $grid = isset($definition['grid']) && is_array($definition['grid'])
            ? $definition['grid']
            : $definition;

        $children = $grid['children'] ?? null;
        if (! is_array($children)) {
            return [];
        }

        $slotNames = [];
        foreach ($children as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $slot = $entry['slot'] ?? null;
            if (is_string($slot) && trim($slot) !== '' && ! is_numeric($slot)) {
                $slotNames[] = trim($slot);
            }
        }

        return $slotNames;
    }

    /**
     * Apply container-level settings immediately (layout type, columns, gap, padding, etc.).
     *
     * @param  array<string, mixed>  $definition
     */
    public static function applyContainerConfig(Container $container, array $definition): void
    {
        // Support optional top-level container box properties
        self::applyBoxProperties($container, $definition);

        if (isset($definition['grid']) && is_array($definition['grid'])) {
            /** @var array<string, mixed> $grid */
            $grid = $definition['grid'];
            self::applyGridContainerConfig($container, $grid);
        }
    }

    /**
     * Apply both container-level and child-level layout rules to a Container and its current children.
     *
     * @param  array<string, mixed>  $definition
     */
    public static function applyToContainer(Container $container, array $definition): void
    {
        self::applyContainerConfig($container, $definition);

        if (isset($definition['grid']) && is_array($definition['grid'])) {
            /** @var array<string, mixed> $grid */
            $grid = $definition['grid'];
            self::applyGridChildrenConfig($container, $grid);
        }
    }

    /**
     * @param  array<string, mixed>  $props
     */
    protected static function applyBoxProperties(Container $container, array $props): void
    {
        if (isset($props['appearance']) && is_string($props['appearance'])) {
            $container->appearance($props['appearance']);
        }

        if (isset($props['max_width']) && (is_int($props['max_width']) || is_string($props['max_width']))) {
            $container->maxWidth(Size::from($props['max_width']));
        }

        if (isset($props['width']) && (is_int($props['width']) || is_string($props['width']))) {
            $container->width(Size::from($props['width']));
        }

        if (isset($props['height']) && (is_int($props['height']) || is_string($props['height']))) {
            $container->height(Size::from($props['height']));
        }

        if (isset($props['padding']) && (is_int($props['padding']) || is_string($props['padding']))) {
            $container->padding(is_int($props['padding']) ? Spacing::px($props['padding']) : Spacing::from($props['padding']));
        }

        if (isset($props['gap']) && (is_int($props['gap']) || is_string($props['gap']))) {
            $container->gap(is_int($props['gap']) ? Spacing::px($props['gap']) : Spacing::from($props['gap']));
        }

        if (isset($props['row_gap']) && (is_int($props['row_gap']) || is_string($props['row_gap']))) {
            $container->rowGap(is_int($props['row_gap']) ? Spacing::px($props['row_gap']) : Spacing::from($props['row_gap']));
        }

        if (isset($props['column_gap']) && (is_int($props['column_gap']) || is_string($props['column_gap']))) {
            $container->columnGap(is_int($props['column_gap']) ? Spacing::px($props['column_gap']) : Spacing::from($props['column_gap']));
        }
    }

    /**
     * @param  array<string, mixed>  $grid
     */
    protected static function applyGridContainerConfig(Container $container, array $grid): void
    {
        $container->layout(LayoutType::GRID);
        self::applyBoxProperties($container, $grid);

        $cols = $grid['cols'] ?? $grid['columns'] ?? null;
        if ($cols !== null) {
            $templateCols = self::normalizeTrackList($cols);
            if ($templateCols !== '') {
                $container->gridTemplateColumns($templateCols);
            }
        }

        $rows = $grid['rows'] ?? null;
        if ($rows !== null) {
            $templateRows = self::normalizeTrackList($rows);
            if ($templateRows !== '') {
                $container->gridTemplateRows($templateRows);
            }
        }
    }

    /**
     * Apply grid child placement, ordering, spans, alignments, and auto-computed row tracks.
     *
     * @param  array<string, mixed>  $grid
     */
    protected static function applyGridChildrenConfig(Container $container, array $grid): void
    {
        $columnCount = self::resolveColumnCount($grid['cols'] ?? $grid['columns'] ?? 1);
        $defaultSpan = self::resolveDefaultColSpan($grid['default_span'] ?? 1, $columnCount);
        $defaultAlign = isset($grid['align']) && is_string($grid['align']) ? trim($grid['align']) : null;
        $defaultJustify = isset($grid['justify']) && is_string($grid['justify']) ? trim($grid['justify']) : null;

        $rulesByName = [];
        $rulesByPosition = [];
        $orderedSlotNames = [];

        if (isset($grid['children']) && is_array($grid['children'])) {
            foreach (array_values($grid['children']) as $idx => $entry) {
                if (! is_array($entry)) {
                    continue;
                }
                /** @var array<string, mixed> $rule */
                $rule = $entry;
                $position = $idx + 1;
                $slot = $rule['slot'] ?? $position;

                if (is_int($slot) || (is_string($slot) && ctype_digit(trim($slot)))) {
                    $slotPos = (int) $slot;
                    $rulesByPosition[$slotPos] = $rule;
                    $orderedSlotNames[] = (string) $slotPos;
                } elseif (is_string($slot) && trim($slot) !== '') {
                    $slotName = trim($slot);
                    $rulesByName[$slotName] = $rule;
                    $rulesByPosition[$position] = $rule;
                    $orderedSlotNames[] = $slotName;
                }
            }
        }

        if (isset($grid['items']) && is_array($grid['items'])) {
            foreach ($grid['items'] as $slotKey => $entry) {
                if (! is_array($entry)) {
                    continue;
                }
                /** @var array<string, mixed> $rule */
                $rule = $entry;
                if (is_int($slotKey)) {
                    $rulesByPosition[$slotKey] = $rule;
                } elseif (ctype_digit(trim($slotKey))) {
                    $rulesByPosition[(int) $slotKey] = $rule;
                } elseif (trim($slotKey) !== '') {
                    $rulesByName[trim($slotKey)] = $rule;
                }
            }
        }

        $currentChildren = $container->getChildren();
        if ($orderedSlotNames === [] && count($currentChildren) > 1) {
            foreach ($currentChildren as $child) {
                $childSlot = ($child instanceof UIComponent || $child instanceof Container) ? $child->getSlot() : null;
                if (is_int($childSlot) || (is_string($childSlot) && ctype_digit(trim($childSlot)))) {
                    $orderedSlotNames = array_map('strval', range(1, count($currentChildren)));
                    break;
                }
            }
        }

        // Reorder container children according to slot assignments and YAML slot order
        if ($orderedSlotNames !== []) {
            $container->reorderChildrenByNames($orderedSlotNames);
            $currentChildren = $container->getChildren();
        }

        // Auto-name unnamed children from their explicit string slot or resolved positional slot
        foreach ($currentChildren as $idx => $child) {
            $pos = $idx + 1;
            if ($child->getName() !== null) {
                continue;
            }

            $explicitSlot = ($child instanceof UIComponent || $child instanceof Container) ? $child->getSlot() : null;
            if (is_string($explicitSlot) && trim($explicitSlot) !== '' && ! ctype_digit(trim($explicitSlot))) {
                $child->setName(trim($explicitSlot));

                continue;
            }

            if (isset($rulesByPosition[$pos])) {
                $slotCandidate = $rulesByPosition[$pos]['slot'] ?? null;
                if (is_string($slotCandidate) && trim($slotCandidate) !== '' && ! ctype_digit(trim($slotCandidate))) {
                    $child->setName(trim($slotCandidate));
                }
            }
        }

        $hasExplicitContainerRows = isset($grid['rows']);
        $hasChildRowTrack = false;
        $rowTracks = [];
        $currentColCursor = 0;
        $currentRowTrack = 'auto';

        foreach ($currentChildren as $idx => $child) {
            $pos = $idx + 1;
            $explicitSlot = ($child instanceof UIComponent || $child instanceof Container) ? $child->getSlot() : null;
            $childName = $child->getName();

            $rule = [];
            if ($explicitSlot !== null) {
                if (is_int($explicitSlot) || ctype_digit(trim($explicitSlot))) {
                    $rule = $rulesByPosition[(int) $explicitSlot] ?? [];
                } elseif (trim($explicitSlot) !== '') {
                    $slotKey = trim($explicitSlot);
                    $rule = $rulesByName[$slotKey] ?? ($rulesByPosition[$pos] ?? []);
                }
            } elseif ($childName !== null && isset($rulesByName[$childName])) {
                $rule = $rulesByName[$childName];
            } elseif (isset($rulesByPosition[$pos])) {
                $rule = $rulesByPosition[$pos];
            }

            [$rowSpan, $colSpan] = self::resolveSpan($rule['span'] ?? null, $defaultSpan, $columnCount);

            $at = $rule['at'] ?? null;
            if (is_array($at) && count($at) >= 2) {
                $atValues = array_values($at);
                $startRow = is_numeric($atValues[0]) ? (int) $atValues[0] : 1;
                $startCol = is_numeric($atValues[1]) ? (int) $atValues[1] : 1;

                $gridRow = $rowSpan > 1 ? "{$startRow} / span {$rowSpan}" : (string) $startRow;
                $gridCol = $colSpan > 1 ? "{$startCol} / span {$colSpan}" : (string) $startCol;
            } else {
                $gridCol = "span {$colSpan}";
                $gridRow = $rowSpan > 1 ? "span {$rowSpan}" : null;
            }

            $align = isset($rule['align']) && is_string($rule['align'])
                ? trim($rule['align'])
                : $defaultAlign;

            $justify = isset($rule['justify']) && is_string($rule['justify'])
                ? trim($rule['justify'])
                : $defaultJustify;

            self::applyPlacementToElement($child, $gridCol, $gridRow, $align, $justify);

            // Track auto-flow rows to compute grid_template_rows when any child defines `row` / `row_height`
            if (! $hasExplicitContainerRows && $child->isVisible()) {
                $effectiveColSpan = min(max($colSpan, 1), $columnCount);
                if ($currentColCursor + $effectiveColSpan > $columnCount) {
                    $rowTracks[] = $currentRowTrack;
                    $currentColCursor = 0;
                    $currentRowTrack = 'auto';
                }

                $rawRowTrack = $rule['row'] ?? $rule['row_height'] ?? null;
                if ($rawRowTrack !== null && (is_string($rawRowTrack) || is_int($rawRowTrack) || is_float($rawRowTrack))) {
                    $currentRowTrack = self::normalizeTrackToken($rawRowTrack);
                    $hasChildRowTrack = true;
                }

                $currentColCursor += $effectiveColSpan;
                if ($currentColCursor >= $columnCount) {
                    $rowTracks[] = $currentRowTrack;
                    $currentColCursor = 0;
                    $currentRowTrack = 'auto';
                }
            }
        }

        if (! $hasExplicitContainerRows && $hasChildRowTrack) {
            if ($currentColCursor > 0) {
                $rowTracks[] = $currentRowTrack;
            }
            if ($rowTracks !== []) {
                $container->gridTemplateRows(implode(' ', $rowTracks));
            }
        }
    }

    protected static function applyPlacementToElement(
        UIElement $element,
        string $gridColumn,
        ?string $gridRow,
        ?string $alignSelf,
        ?string $justifySelf
    ): void {
        if ($element instanceof UIComponent || $element instanceof Container) {
            $element->gridColumn($gridColumn);

            if ($gridRow !== null) {
                $element->gridRow($gridRow);
            }

            if ($alignSelf !== null && $alignSelf !== '') {
                $element->alignSelf($alignSelf);
            }

            if ($justifySelf !== null && $justifySelf !== '') {
                $element->justifySelf($justifySelf);
            }
        }
    }

    /**
     * @return array{0: int, 1: int} [rowSpan, colSpan]
     */
    protected static function resolveSpan(mixed $span, int $defaultColSpan, int $columnCount): array
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
            $values = array_values($span);
            $rowSpan = isset($values[0]) && is_numeric($values[0]) ? max(1, (int) $values[0]) : 1;
            $colRaw = $values[1] ?? $defaultColSpan;
            $colSpan = $colRaw === 'full'
                ? $columnCount
                : (is_numeric($colRaw) ? max(1, (int) $colRaw) : $defaultColSpan);

            return [$rowSpan, $colSpan];
        }

        return [1, $defaultColSpan];
    }

    protected static function resolveDefaultColSpan(mixed $defaultSpan, int $columnCount): int
    {
        if ($defaultSpan === 'full') {
            return $columnCount;
        }

        if (is_int($defaultSpan) || (is_string($defaultSpan) && is_numeric($defaultSpan))) {
            return max(1, (int) $defaultSpan);
        }

        return 1;
    }

    protected static function resolveColumnCount(mixed $cols): int
    {
        if (is_int($cols)) {
            return max(1, $cols);
        }

        if (is_array($cols)) {
            return max(1, count($cols));
        }

        if (is_string($cols)) {
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

        return 1;
    }

    protected static function normalizeTrackList(mixed $tracks): string
    {
        if (is_int($tracks)) {
            return "repeat({$tracks}, 1fr)";
        }

        if (is_string($tracks)) {
            $trimmed = trim($tracks);

            return ctype_digit($trimmed) ? "repeat({$trimmed}, 1fr)" : $trimmed;
        }

        if (is_array($tracks)) {
            $tokens = [];
            foreach ($tracks as $track) {
                if (is_string($track) || is_int($track) || is_float($track)) {
                    $tokens[] = self::normalizeTrackToken($track);
                }
            }

            return implode(' ', $tokens);
        }

        return '';
    }

    protected static function normalizeTrackToken(string|int|float $token): string
    {
        if (is_int($token) || is_float($token)) {
            return "{$token}px";
        }

        $trimmed = trim($token);

        return is_numeric($trimmed) ? "{$trimmed}px" : $trimmed;
    }

    protected static function resolveContextDirectory(?string $callingContext): ?string
    {
        if ($callingContext === null || ! class_exists($callingContext)) {
            return null;
        }

        $reflection = new ReflectionClass($callingContext);
        $fileName = $reflection->getFileName();

        return is_string($fileName) ? dirname($fileName) : null;
    }
}
