<?php

namespace Idei\Usim\Support;

use Idei\Usim\Components\Button;
use Idei\Usim\Components\Calendar;
use Idei\Usim\Components\Card;
use Idei\Usim\Components\Carousel;
use Idei\Usim\Components\Checkbox;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\Image;
use Idei\Usim\Components\Input;
use Idei\Usim\Components\ItemList;
use Idei\Usim\Components\Label;
use Idei\Usim\Components\NewLabel;
use Idei\Usim\Components\Select;
use Idei\Usim\Components\Split;
use Idei\Usim\Components\Table;
use Idei\Usim\Components\Textarea;
use Idei\Usim\Components\Timer;
use Idei\Usim\Components\UIComponent;
use Idei\Usim\Components\Uploader;
use Idei\Usim\Contracts\UIElement;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\Support\Translation\TranslationResolver;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;
use Illuminate\Support\Arr;
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
     * Component property keys whose string values accept declarative i18n notation (`.key` or `t:key`).
     *
     * @var list<string>
     */
    protected const TRANSLATABLE_TEXT_PROPERTIES = [
        'text',
        'label',
        'placeholder',
        'help_text',
        'title',
        'description',
        'empty_message',
        'no_results_message',
        'min_chars_message',
        'search_placeholder',
        'create_placeholder',
        'create_button_label',
        'bulk_delete_label',
        'save_button_label',
        'cancel_button_label',
        'tooltip',
    ];

    /**
     * Common USIM component prefixes stripped when resolving convention-based translation keys.
     *
     * @var list<string>
     */
    protected const COMPONENT_PREFIXES = [
        'input_',
        'btn_',
        'lbl_',
        'sel_',
        'chk_',
        'txt_',
        'tbl_',
        'lst_',
        'img_',
        'cal_',
        'car_',
        'upl_',
        'crd_',
        'cnt_',
        'tmr_',
    ];

    /**
     * Map of conventional component prefixes to their concrete UIElement classes for auto-instantiation.
     *
     * @var array<string, class-string<UIElement>>
     */
    protected const COMPONENT_PREFIX_MAP = [
        'input_' => Input::class,
        'btn_' => Button::class,
        'lbl_' => NewLabel::class,
        'sel_' => Select::class,
        'chk_' => Checkbox::class,
        'txt_' => Textarea::class,
        'tbl_' => Table::class,
        'lst_' => ItemList::class,
        'img_' => Image::class,
        'cal_' => Calendar::class,
        'car_' => Carousel::class,
        'upl_' => Uploader::class,
        'crd_' => Card::class,
        'cnt_' => Container::class,
        'tmr_' => Timer::class,
    ];

    /**
     * Explicit `component:` aliases supported in `.layout.yaml` child rules.
     *
     * @var array<string, class-string<UIElement>>
     */
    protected const COMPONENT_ALIAS_MAP = [
        'input' => Input::class,
        'button' => Button::class,
        'label' => Label::class,
        'new_label' => NewLabel::class,
        'select' => Select::class,
        'checkbox' => Checkbox::class,
        'textarea' => Textarea::class,
        'table' => Table::class,
        'item_list' => ItemList::class,
        'list' => ItemList::class,
        'image' => Image::class,
        'calendar' => Calendar::class,
        'carousel' => Carousel::class,
        'uploader' => Uploader::class,
        'card' => Card::class,
        'container' => Container::class,
        'split' => Split::class,
        'timer' => Timer::class,
    ];

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
     * @param  string|null  $baseDir  Base directory of a parent layout file for relative includes
     * @return array<string, mixed>
     */
    public static function loadFile(?string $path = null, ?string $callingContext = null, ?string $baseDir = null): array
    {
        return self::loadFileWithMeta($path, $callingContext, $baseDir)['definition'];
    }

    /**
     * Resolve and parse a YAML layout file, returning both its definition and resolved filesystem path.
     *
     * @return array{definition: array<string, mixed>, resolvedPath: string}
     */
    public static function loadFileWithMeta(?string $path = null, ?string $callingContext = null, ?string $baseDir = null): array
    {
        $resolvedPath = self::resolvePath($path, $callingContext, $baseDir);
        $mtime = @filemtime($resolvedPath) ?: 0;
        $cacheKey = "{$resolvedPath}:{$mtime}";

        if (isset(self::$parsedCache[$cacheKey])) {
            return [
                'definition' => self::$parsedCache[$cacheKey],
                'resolvedPath' => $resolvedPath,
            ];
        }

        $parsed = Yaml::parseFile($resolvedPath);
        if (! is_array($parsed)) {
            throw new InvalidArgumentException("Layout file [{$resolvedPath}] must contain a valid YAML mapping.");
        }

        /** @var array<string, mixed> $normalized */
        $normalized = $parsed;
        self::$parsedCache[$cacheKey] = $normalized;

        return [
            'definition' => $normalized,
            'resolvedPath' => $resolvedPath,
        ];
    }

    /**
     * Determine whether a sibling "<kebab-class-name>.layout.yaml" exists for the given Screen class.
     */
    public static function hasDefaultLayout(?string $callingContext): bool
    {
        $contextDir = self::resolveContextDirectory($callingContext);
        if ($callingContext === null || $contextDir === null) {
            return false;
        }

        $defaultFile = $contextDir.'/'.Str::kebab(class_basename($callingContext)).'.layout.yaml';

        return is_file($defaultFile);
    }

    /**
     * Resolve the filesystem path for a layout file reference.
     */
    public static function resolvePath(?string $path = null, ?string $callingContext = null, ?string $baseDir = null): string
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

        if ($baseDir !== null && $baseDir !== '') {
            foreach ($candidates as $candidate) {
                $relativeToBase = $baseDir.'/'.ltrim($candidate, '/');
                if (is_file($relativeToBase)) {
                    return (string) realpath($relativeToBase);
                }
            }
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
        $idx = 0;
        foreach ($children as $key => $entry) {
            $idx++;
            $normalized = self::normalizeChildEntry($key, $entry, $idx);
            $slot = $normalized['slot'];
            if (is_string($slot) && trim($slot) !== '' && ! is_numeric($slot)) {
                $slotNames[] = trim($slot);
            }
        }

        return $slotNames;
    }

    /**
     * Extract normalized child rules indexed by slot name or 1-based position.
     *
     * @param  array<string, mixed>  $definition
     * @return array<int|string, array<string, mixed>>
     */
    public static function extractChildRulesBySlot(array $definition): array
    {
        $grid = isset($definition['grid']) && is_array($definition['grid'])
            ? $definition['grid']
            : $definition;

        $children = $grid['children'] ?? null;
        if (! is_array($children)) {
            return [];
        }

        $rules = [];
        $idx = 0;
        foreach ($children as $key => $entry) {
            $idx++;
            $normalized = self::normalizeChildEntry($key, $entry, $idx);
            $slotKey = trim((string) $normalized['slot']);
            if ($slotKey !== '') {
                $rules[$slotKey] = $normalized['rule'];
            }
            $rules[(string) $idx] = $normalized['rule'];
        }

        return $rules;
    }

    /**
     * Determine whether a child rule declares a nested container layout (inline `grid` or external file reference).
     *
     * @param  array<string, mixed>  $rule
     */
    public static function isNestedContainerRule(array $rule): bool
    {
        if (isset($rule['grid']) && is_array($rule['grid'])) {
            return true;
        }

        return self::extractExternalLayoutReference($rule) !== null;
    }

    /**
     * Extract an external layout file reference from a child rule (`layout_file`, `include`, `file`, or non-enum `layout`).
     *
     * @param  array<string, mixed>  $rule
     */
    public static function extractExternalLayoutReference(array $rule): ?string
    {
        foreach (['layout_file', 'include', 'file'] as $key) {
            if (isset($rule[$key]) && is_string($rule[$key]) && trim($rule[$key]) !== '') {
                return trim($rule[$key]);
            }
        }

        if (isset($rule['layout']) && is_string($rule['layout']) && trim($rule['layout']) !== '') {
            $layoutVal = trim($rule['layout']);
            if (LayoutType::tryFrom(strtolower($layoutVal)) === null) {
                return $layoutVal;
            }
        }

        return null;
    }

    /**
     * Resolve a concrete UIElement class from an explicit `component:` rule or conventional name prefix.
     *
     * @param  array<string, mixed>  $rule
     * @return class-string<UIElement>|null
     */
    public static function resolveComponentClassForSlot(string $slotName, array $rule = []): ?string
    {
        if (isset($rule['component']) && is_string($rule['component']) && trim($rule['component']) !== '') {
            $raw = trim($rule['component']);
            $alias = Str::snake($raw);
            if (isset(self::COMPONENT_ALIAS_MAP[$alias])) {
                return self::COMPONENT_ALIAS_MAP[$alias];
            }

            if (class_exists($raw) && is_subclass_of($raw, UIElement::class)) {
                return $raw;
            }
        }

        $lowerSlot = strtolower(trim($slotName));
        foreach (self::COMPONENT_PREFIX_MAP as $prefix => $class) {
            if (str_starts_with($lowerSlot, $prefix)) {
                return $class;
            }
        }

        return null;
    }

    /**
     * Auto-instantiate a UIElement for a slot using its explicit `component:` or conventional name prefix.
     *
     * @param  array<string, mixed>  $rule
     */
    public static function instantiateElementForSlot(string $slotName, array $rule = []): ?UIElement
    {
        $class = self::resolveComponentClassForSlot($slotName, $rule);
        if ($class === null) {
            return null;
        }

        return new $class($slotName);
    }

    /**
     * Extract normalized timer entries from a root `timers:` definition.
     *
     * @param  array<string, mixed>  $definition
     * @return list<array{name: string, rule: array<string, mixed>}>
     */
    public static function extractTimerEntries(array $definition): array
    {
        $timers = $definition['timers'] ?? null;
        if (! is_array($timers)) {
            return [];
        }

        $entries = [];
        $idx = 0;
        foreach ($timers as $key => $entry) {
            $idx++;
            $normalized = self::normalizeChildEntry($key, $entry, $idx);
            $slot = $normalized['slot'];
            $name = is_string($slot) && trim($slot) !== ''
                ? trim($slot)
                : "tmr_{$idx}";

            $entries[] = [
                'name' => $name,
                'rule' => $normalized['rule'],
            ];
        }

        return $entries;
    }

    /**
     * Normalize a child entry from the YAML definition into [slot, rule].
     *
     * Supports:
     * - "input_name" (bare string)
     * - ["btn_submit" => ["span" => 1, ...]] (key-value pair)
     * - ["btn_submit" => null] (key without options)
     * - ["slot" => "btn_submit", "span" => 1, ...] (legacy explicit slot)
     *
     * @param  mixed  $key  Key from the children array (index or associative slot name)
     * @param  mixed  $entry  Raw entry from YAML definition
     * @param  int  $position  1-based position in children list
     * @return array{slot: string|int, rule: array<string, mixed>}
     */
    public static function normalizeChildEntry(mixed $key, mixed $entry, int $position): array
    {
        // 1. Bare string: - input_name
        if (is_string($entry) && trim($entry) !== '') {
            $slot = trim($entry);

            return [
                'slot' => $slot,
                'rule' => ['slot' => $slot],
            ];
        }

        // 2. Associative entry when children is a map: children: { input_name: { span: 2 } }
        if (is_string($key) && trim($key) !== '' && ! ctype_digit(trim($key))) {
            $slot = trim($key);
            $rule = is_array($entry) ? $entry : [];
            $rule['slot'] = $rule['slot'] ?? $slot;

            return [
                'slot' => $slot,
                'rule' => $rule,
            ];
        }

        if (! is_array($entry)) {
            return [
                'slot' => $position,
                'rule' => ['slot' => $position],
            ];
        }

        // 3. Explicit slot in array: - { slot: btn_submit, ... }
        if (isset($entry['slot'])) {
            /** @var array<string, mixed> $normalizedRule */
            $normalizedRule = [];
            foreach ($entry as $k => $v) {
                $normalizedRule[(string) $k] = $v;
            }

            return [
                'slot' => (is_string($entry['slot']) || is_int($entry['slot'])) ? $entry['slot'] : $position,
                'rule' => $normalizedRule,
            ];
        }

        // 4. Single-key mapping: - btn_submit: { span: 1, ... } or - btn_submit:
        if (count($entry) === 1) {
            $entryKey = array_key_first($entry);
            if (is_string($entryKey) && trim($entryKey) !== '') {
                $slot = trim($entryKey);
                $rule = is_array($entry[$entryKey]) ? $entry[$entryKey] : [];
                $rule['slot'] = $rule['slot'] ?? $slot;

                /** @var array<string, mixed> $normalizedRule */
                $normalizedRule = [];
                foreach ($rule as $k => $v) {
                    $normalizedRule[(string) $k] = $v;
                }

                return [
                    'slot' => $slot,
                    'rule' => $normalizedRule,
                ];
            }
        }

        /** @var array<string, mixed> $normalizedRule */
        $normalizedRule = [];
        foreach ($entry as $k => $v) {
            $normalizedRule[(string) $k] = $v;
        }

        return [
            'slot' => $position,
            'rule' => $normalizedRule,
        ];
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
     * @param  list<string>  $visitedPaths
     */
    public static function applyToContainer(
        Container $container,
        array $definition,
        ?string $callingContext = null,
        bool $autoTranslate = false,
        ?string $baseDir = null,
        array $visitedPaths = []
    ): void {
        self::applyContainerConfig($container, $definition);

        $effectiveAutoTranslate = $autoTranslate || self::isAutoTranslateDefinition($definition);

        if ($visitedPaths === [] && $container->getLayoutSourcePath() !== null) {
            $visitedPaths = [$container->getLayoutSourcePath()];
        }

        if (isset($definition['grid']) && is_array($definition['grid'])) {
            /** @var array<string, mixed> $grid */
            $grid = $definition['grid'];
            self::applyGridChildrenConfig(
                $container,
                $grid,
                $callingContext,
                $effectiveAutoTranslate,
                $baseDir ?? $container->getLayoutBaseDir(),
                $visitedPaths
            );
        } elseif ($effectiveAutoTranslate && $callingContext !== null) {
            self::autoHydrateContainerChildren($container, $callingContext);
        }

        if (isset($definition['timers']) && is_array($definition['timers'])) {
            self::applyTimersConfig($container, $definition, $callingContext);
        }
    }

    /**
     * Mount and configure non-visual Timer components declared under the root `timers:` section.
     *
     * @param  array<string, mixed>  $definition
     */
    protected static function applyTimersConfig(
        Container $container,
        array $definition,
        ?string $callingContext = null
    ): void {
        $entries = self::extractTimerEntries($definition);
        if ($entries === []) {
            return;
        }

        $existingByName = [];
        foreach ($container->getChildren() as $child) {
            if ($child instanceof Timer && $child->getName() !== null) {
                $existingByName[$child->getName()] = $child;
            }
        }

        foreach ($entries as $entry) {
            $name = $entry['name'];
            $rule = $entry['rule'];

            $timer = $existingByName[$name] ?? null;
            if ($timer === null) {
                $timer = new Timer($name);
                $container->add($timer);
                $existingByName[$name] = $timer;
            }

            self::applyElementCustomProperties($timer, $rule, $callingContext);
        }
    }

    /**
     * @param  array<string, mixed>  $props
     */
    protected static function applyBoxProperties(Container $container, array $props): void
    {
        if (($props['card'] ?? false) === true) {
            $container->card();
        }

        if (isset($props['appearance']) && is_string($props['appearance'])) {
            $container->appearance($props['appearance']);
        }

        if (($props['center_horizontal'] ?? false) === true || ($props['center'] ?? false) === true) {
            $container->centerHorizontal();
        }

        if (isset($props['min_width']) && (is_int($props['min_width']) || is_string($props['min_width']))) {
            $container->minWidth(Size::from($props['min_width']));
        }

        if (isset($props['max_width']) && (is_int($props['max_width']) || is_string($props['max_width']))) {
            $container->maxWidth(Size::from($props['max_width']));
        }

        if (isset($props['min_height']) && (is_int($props['min_height']) || is_string($props['min_height']))) {
            $container->minHeight(Size::from($props['min_height']));
        }

        if (isset($props['max_height']) && (is_int($props['max_height']) || is_string($props['max_height']))) {
            $container->maxHeight(Size::from($props['max_height']));
        }

        if (isset($props['width']) && (is_int($props['width']) || is_string($props['width']))) {
            $container->width(Size::from($props['width']));
        }

        if (isset($props['height']) && (is_int($props['height']) || is_string($props['height']))) {
            $container->height(Size::from($props['height']));
        }

        if (isset($props['padding']) && (is_int($props['padding']) || is_string($props['padding']))) {
            $container->padding(Spacing::from($props['padding']));
        }

        if (isset($props['gap']) && (is_int($props['gap']) || is_string($props['gap']))) {
            $container->gap(Spacing::from($props['gap']));
        }

        if (isset($props['row_gap']) && (is_int($props['row_gap']) || is_string($props['row_gap']))) {
            $container->rowGap(Spacing::from($props['row_gap']));
        }

        if (isset($props['column_gap']) && (is_int($props['column_gap']) || is_string($props['column_gap']))) {
            $container->columnGap(Spacing::from($props['column_gap']));
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
     * @param  list<string>  $visitedPaths
     */
    protected static function applyGridChildrenConfig(
        Container $container,
        array $grid,
        ?string $callingContext = null,
        bool $autoTranslate = false,
        ?string $baseDir = null,
        array $visitedPaths = []
    ): void {
        $translationTree = ($autoTranslate && $callingContext !== null)
            ? TranslationResolver::resolveScreenTranslationTree($callingContext)
            : [];

        $columnCount = self::resolveColumnCount($grid['cols'] ?? $grid['columns'] ?? 1);
        $defaultSpan = self::resolveDefaultColSpan($grid['default_span'] ?? 1, $columnCount);
        $defaultAlign = isset($grid['align']) && is_string($grid['align']) ? trim($grid['align']) : null;
        $defaultJustify = isset($grid['justify']) && is_string($grid['justify']) ? trim($grid['justify']) : null;

        $rulesByName = [];
        $rulesByPosition = [];
        $orderedSlotNames = [];

        if (isset($grid['children']) && is_array($grid['children'])) {
            $idx = 0;
            foreach ($grid['children'] as $key => $entry) {
                $idx++;
                $normalized = self::normalizeChildEntry($key, $entry, $idx);
                $slot = $normalized['slot'];
                $rule = $normalized['rule'];
                $position = $idx;

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

            if ($child instanceof Timer && $explicitSlot === null && ($childName === null || ! isset($rulesByName[$childName]))) {
                continue;
            }

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

            if ($child instanceof Container && self::isNestedContainerRule($rule)) {
                self::applyNestedContainerLayout($child, $rule, $callingContext, $autoTranslate, $baseDir, $visitedPaths);
            }

            self::applyPlacementToElement($child, $gridCol, $gridRow, $align, $justify, $rule, $callingContext);

            if ($translationTree !== []) {
                $resolvedSlot = $rule['slot'] ?? $explicitSlot ?? $childName;
                self::autoHydrateElementTranslations(
                    $child,
                    is_int($resolvedSlot) || is_string($resolvedSlot) ? $resolvedSlot : null,
                    $translationTree
                );
            }

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

    /**
     * Resolve and apply a nested layout (inline `grid` or external `.layout.yaml` reference) to a child Container.
     *
     * @param  array<string, mixed>  $rule
     * @param  list<string>  $visitedPaths
     */
    protected static function applyNestedContainerLayout(
        Container $child,
        array $rule,
        ?string $callingContext,
        bool $autoTranslate,
        ?string $baseDir,
        array $visitedPaths
    ): void {
        $externalRef = self::extractExternalLayoutReference($rule);
        if ($externalRef !== null) {
            $meta = self::loadFileWithMeta($externalRef, $callingContext, $baseDir);
            $resolvedPath = $meta['resolvedPath'];
            if (in_array($resolvedPath, $visitedPaths, true)) {
                throw new InvalidArgumentException("Circular layout reference detected for [{$resolvedPath}].");
            }

            $nestedDef = $meta['definition'];
            $childBaseDir = dirname($resolvedPath);
            $childVisited = [...$visitedPaths, $resolvedPath];
            $child->layoutSourcePath($resolvedPath);
        } else {
            /** @var array<string, mixed> $gridRule */
            $gridRule = $rule['grid'];
            $nestedDef = ['grid' => $gridRule];
            $childBaseDir = $baseDir;
            $childVisited = $visitedPaths;
        }

        // Propagate container box overrides from the parent child rule into the nested definition
        // so subsequent applyLayoutDefinition() passes preserve caller overrides.
        foreach (['appearance', 'card', 'center_horizontal', 'center', 'padding', 'gap', 'row_gap', 'column_gap', 'width', 'max_width', 'height', 'i18n'] as $boxKey) {
            if (array_key_exists($boxKey, $rule)) {
                $nestedDef[$boxKey] = $rule[$boxKey];
            }
        }

        $child->layoutBaseDir($childBaseDir);
        $child->layoutDefinition($nestedDef, $callingContext, $autoTranslate);
        self::applyToContainer($child, $nestedDef, $callingContext, $autoTranslate, $childBaseDir, $childVisited);
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    protected static function applyPlacementToElement(
        UIElement $element,
        string $gridColumn,
        ?string $gridRow,
        ?string $alignSelf,
        ?string $justifySelf,
        array $rule = [],
        ?string $callingContext = null
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

            self::applyElementCustomProperties($element, $rule, $callingContext);
        }
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    protected static function applyElementCustomProperties(
        UIElement $element,
        array $rule,
        ?string $callingContext = null
    ): void {
        $ignoredKeys = [
            'slot', 'span', 'at', 'row', 'row_height', 'align', 'justify',
            'grid', 'layout_file', 'include', 'file', 'i18n', 'component',
        ];
        $hasExternalLayoutRef = self::extractExternalLayoutReference($rule) !== null;

        foreach (self::orderCustomRuleProperties($rule) as $key => $value) {
            if (in_array($key, $ignoredKeys, true) || $value === null) {
                continue;
            }

            if ($key === 'layout' && $hasExternalLayoutRef) {
                continue;
            }

            // Spacing helpers for Container (padding, gap, row_gap, column_gap)
            if ($element instanceof Container && in_array($key, [
                'padding', 'padding_top', 'padding_right', 'padding_bottom', 'padding_left',
                'gap', 'row_gap', 'column_gap',
            ], true) && (is_string($value) || is_int($value) || $value instanceof Spacing)) {
                $methodName = Str::camel($key);
                if (method_exists($element, $methodName)) {
                    $element->{$methodName}(Spacing::from($value));
                }

                continue;
            }

            // Container boolean box helpers
            if ($element instanceof Container && $key === 'card' && $value === true) {
                $element->card();

                continue;
            }

            if ($element instanceof Container && in_array($key, ['center_horizontal', 'center'], true) && $value === true) {
                $element->centerHorizontal();

                continue;
            }

            // Dimension helpers
            if (in_array($key, ['width', 'height', 'min_width', 'max_width', 'min_height', 'max_height'], true)
                && ($element instanceof UIComponent || $element instanceof Container)) {
                $size = match (true) {
                    $value === 'full' => Size::full(),
                    is_string($value), is_int($value), $value instanceof Size => Size::from($value),
                    default => null,
                };

                if ($size !== null) {
                    $methodName = Str::camel($key);
                    if (method_exists($element, $methodName)) {
                        $element->{$methodName}($size);
                    }
                }

                continue;
            }

            // Boolean convenience methods (e.g. center: true)
            if ($key === 'center' && $value === true && method_exists($element, 'center')) {
                $element->center();

                continue;
            }

            if (is_string($value) && in_array($key, self::TRANSLATABLE_TEXT_PROPERTIES, true)) {
                $value = TranslationResolver::resolveText($value, $callingContext);
            }

            // Direct fluent methods (style, action, required, type, placeholder, label, text, helpText)
            $methodName = Str::camel($key);
            if (method_exists($element, $methodName)) {
                $element->{$methodName}($value);
            }
        }
    }

    /**
     * Order composite CRUD flags before granular modifiers so YAML key order never clobbers overrides.
     *
     * @param  array<string, mixed>  $rule
     * @return array<string, mixed>
     */
    protected static function orderCustomRuleProperties(array $rule): array
    {
        $priorityKeys = ['service', 'creatable', 'editable', 'deletable'];
        $ordered = [];

        foreach ($priorityKeys as $priorityKey) {
            if (array_key_exists($priorityKey, $rule)) {
                $ordered[$priorityKey] = $rule[$priorityKey];
            }
        }

        foreach ($rule as $key => $value) {
            if (! array_key_exists($key, $ordered)) {
                $ordered[$key] = $value;
            }
        }

        return $ordered;
    }

    /**
     * Determine whether a layout definition enables convention-based auto-translation.
     *
     * @param  array<string, mixed>  $definition
     */
    protected static function isAutoTranslateDefinition(array $definition): bool
    {
        $topFlag = $definition['i18n'] ?? null;
        $grid = isset($definition['grid']) && is_array($definition['grid']) ? $definition['grid'] : [];
        $gridFlag = $grid['i18n'] ?? null;

        return in_array($topFlag, ['auto', true], true) || in_array($gridFlag, ['auto', true], true);
    }

    /**
     * Auto-hydrate translations for non-grid direct children of a Container.
     */
    protected static function autoHydrateContainerChildren(Container $container, string $callingContext): void
    {
        $translationTree = TranslationResolver::resolveScreenTranslationTree($callingContext);
        if ($translationTree === []) {
            return;
        }

        foreach ($container->getChildren() as $child) {
            $slot = ($child instanceof UIComponent || $child instanceof Container) ? $child->getSlot() : null;
            self::autoHydrateElementTranslations($child, $slot ?? $child->getName(), $translationTree);
        }
    }

    /**
     * Populate untouched textual properties on a component from the Screen translation dictionary.
     *
     * @param  array<string, mixed>  $translationTree
     */
    protected static function autoHydrateElementTranslations(
        UIElement $element,
        int|string|null $slot,
        array $translationTree
    ): void {
        if (! ($element instanceof UIComponent) || ! is_string($slot) || trim($slot) === '' || ctype_digit(trim($slot))) {
            return;
        }

        $nameCandidates = self::extractNameCandidates(trim($slot));
        $schema = self::resolveComponentTranslationSchema($element);

        foreach ($schema['attributes'] as $attrKey) {
            if (! $element->isConfigDefault($attrKey)) {
                continue;
            }

            $methodName = Str::camel($attrKey);
            if (! method_exists($element, $methodName)) {
                continue;
            }

            $isPrimary = $attrKey === $schema['primary'];
            $translated = self::findTranslationValueForAttribute(
                $translationTree,
                $nameCandidates,
                $schema['groups'],
                $attrKey,
                $isPrimary
            );

            if ($translated !== null) {
                $element->{$methodName}($translated);
            }
        }
    }

    /**
     * @return array{primary: string|null, groups: list<string>, attributes: list<string>}
     */
    protected static function resolveComponentTranslationSchema(UIComponent $element): array
    {
        return match (true) {
            $element instanceof NewLabel, $element instanceof Label => [
                'primary' => 'text',
                'groups' => ['labels'],
                'attributes' => ['text', 'tooltip'],
            ],
            $element instanceof Button => [
                'primary' => 'label',
                'groups' => ['actions', 'buttons'],
                'attributes' => ['label', 'tooltip', 'title'],
            ],
            $element instanceof Input, $element instanceof Textarea => [
                'primary' => 'label',
                'groups' => ['inputs', 'fields'],
                'attributes' => ['label', 'placeholder', 'help_text', 'tooltip'],
            ],
            $element instanceof Select => [
                'primary' => 'label',
                'groups' => ['selects', 'inputs', 'fields'],
                'attributes' => ['label', 'placeholder', 'help_text', 'empty_message', 'tooltip'],
            ],
            $element instanceof Checkbox => [
                'primary' => 'label',
                'groups' => ['checkboxes', 'inputs', 'fields'],
                'attributes' => ['label', 'description', 'help_text', 'tooltip'],
            ],
            $element instanceof ItemList => [
                'primary' => 'title',
                'groups' => ['lists'],
                'attributes' => [
                    'title',
                    'label',
                    'help_text',
                    'empty_message',
                    'no_results_message',
                    'search_placeholder',
                    'create_placeholder',
                    'create_button_label',
                    'bulk_delete_label',
                ],
            ],
            $element instanceof Table => [
                'primary' => 'title',
                'groups' => ['tables'],
                'attributes' => ['title', 'empty_message'],
            ],
            $element instanceof Card => [
                'primary' => 'title',
                'groups' => ['cards'],
                'attributes' => ['title', 'description'],
            ],
            default => [
                'primary' => 'label',
                'groups' => [],
                'attributes' => ['label', 'title', 'text', 'placeholder', 'help_text'],
            ],
        };
    }

    /**
     * @return list<string>
     */
    protected static function extractNameCandidates(string $slotName): array
    {
        $candidates = [$slotName];

        foreach (self::COMPONENT_PREFIXES as $prefix) {
            if (str_starts_with($slotName, $prefix) && strlen($slotName) > strlen($prefix)) {
                $stripped = substr($slotName, strlen($prefix));
                if ($stripped !== '') {
                    $candidates[] = $stripped;
                }
                break;
            }
        }

        return array_values(array_unique($candidates));
    }

    /**
     * @param  array<string, mixed>  $tree
     * @param  list<string>  $nameCandidates
     * @param  list<string>  $groups
     */
    protected static function findTranslationValueForAttribute(
        array $tree,
        array $nameCandidates,
        array $groups,
        string $attribute,
        bool $isPrimary
    ): ?string {
        foreach ($nameCandidates as $name) {
            $value = Arr::get($tree, "{$name}.{$attribute}");
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        foreach ($groups as $group) {
            foreach ($nameCandidates as $name) {
                $value = Arr::get($tree, "{$group}.{$name}.{$attribute}");
                if (is_string($value) && $value !== '') {
                    return $value;
                }
            }
        }

        if (! $isPrimary) {
            return null;
        }

        foreach ($nameCandidates as $name) {
            $value = Arr::get($tree, $name);
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        foreach ($groups as $group) {
            foreach ($nameCandidates as $name) {
                $value = Arr::get($tree, "{$group}.{$name}");
                if (is_string($value) && $value !== '') {
                    return $value;
                }
            }
        }

        return null;
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
