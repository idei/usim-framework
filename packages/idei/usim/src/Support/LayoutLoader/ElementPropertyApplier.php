<?php

declare(strict_types=1);

namespace Idei\Usim\Support\LayoutLoader;

use Idei\Usim\Components\Container;
use Idei\Usim\Components\UIComponent;
use Idei\Usim\Contracts\UIElement;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\Support\Translation\TranslationResolver;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;
use Illuminate\Support\Str;

/**
 * Applies container box settings, grid container tracks, layout slot metadata,
 * placement attributes, and custom fluent properties (with Level 1 i18n resolution).
 */
final class ElementPropertyApplier
{
    /**
     * Component property keys whose string values accept declarative i18n notation (`.key` or `t:key`).
     *
     * @var list<string>
     */
    private const TRANSLATABLE_TEXT_PROPERTIES = [
        'text',
        'label',
        'placeholder',
        'help_text',
        'title',
        'subtitle',
        'eyebrow',
        'badge',
        'footer_text',
        'description',
        'empty_message',
        'no_results_message',
        'min_chars_message',
        'search_placeholder',
        'load_more_label',
        'clear_filters_label',
        'create_placeholder',
        'create_button_label',
        'bulk_delete_label',
        'save_button_label',
        'cancel_button_label',
        'tooltip',
    ];

    /** @var array<string, string> */
    private const BOX_SIZE_METHODS = [
        'min_width' => 'minWidth',
        'max_width' => 'maxWidth',
        'min_height' => 'minHeight',
        'max_height' => 'maxHeight',
        'width' => 'width',
        'height' => 'height',
    ];

    /** @var array<string, string> */
    private const BOX_SPACING_METHODS = [
        'padding' => 'padding',
        'gap' => 'gap',
        'row_gap' => 'rowGap',
        'column_gap' => 'columnGap',
    ];

    /** @var list<string> */
    private const CONTAINER_SPACING_KEYS = [
        'padding',
        'padding_top',
        'padding_right',
        'padding_bottom',
        'padding_left',
        'gap',
        'row_gap',
        'column_gap',
    ];

    /** @var list<string> */
    private const DIMENSION_KEYS = [
        'width',
        'height',
        'min_width',
        'max_width',
        'min_height',
        'max_height',
    ];

    /** @var list<string> */
    private const IGNORED_CUSTOM_KEYS = [
        'slot', 'span', 'at', 'row', 'row_height', 'align', 'justify',
        'grid', 'layout_file', 'include', 'file', 'i18n', 'component',
        'first', 'second', 'layout_slot', 'slot_aliases', 'screen', 'persist_on_navigate', 'name',
        'slots', 'children', 'open', 'fallback', 'params',
        'portal_slot', 'portal_open', 'portal_size', 'portal_fallback',
        'portal_persist_on_navigate', 'portal_target_id', 'portal_unmounted',
    ];

    /** @var list<string> */
    private const PRIORITY_CUSTOM_KEYS = ['service', 'creatable', 'editable', 'deletable'];

    public function __construct(
        private readonly LayoutDefinitionParser $definitionParser,
        private readonly GridTrackNormalizer $trackNormalizer,
    ) {}

    /**
     * @param  array<string, mixed>  $definition
     */
    public function applyContainerConfig(Container $container, array $definition): void
    {
        $mainDef = $this->definitionParser->resolveMainDefinition($definition);

        $this->applyBoxProperties($container, $mainDef);
        $this->applyContainerFlexAlignment($container, $mainDef);

        if (isset($mainDef['grid']) && is_array($mainDef['grid'])) {
            /** @var array<string, mixed> $grid */
            $grid = $mainDef['grid'];
            $this->applyGridContainerConfig($container, $grid);
        }
    }

    /**
     * @param  array<string, mixed>  $props
     */
    public function applyBoxProperties(Container $container, array $props): void
    {
        if (($props['card'] ?? false) === true) {
            $container->card();
        }

        if (isset($props['appearance']) && is_string($props['appearance'])) {
            $container->appearance($props['appearance']);
        }

        if (isset($props['custom_class']) && is_string($props['custom_class']) && trim($props['custom_class']) !== '') {
            $container->customClass(trim($props['custom_class']));
        }

        if (($props['center_horizontal'] ?? false) === true || ($props['center'] ?? false) === true) {
            $container->centerHorizontal();
        }

        $this->applyBoxSizeProperties($container, $props);
        $this->applyBoxSpacingProperties($container, $props);
    }

    /**
     * @param  array<string, mixed>  $grid
     */
    public function applyGridContainerConfig(Container $container, array $grid): void
    {
        $container->layout(LayoutType::GRID);
        $this->applyBoxProperties($container, $grid);

        $cols = $grid['cols'] ?? $grid['columns'] ?? null;
        if ($cols !== null) {
            $templateCols = $this->trackNormalizer->normalizeTrackList($cols);
            if ($templateCols !== '') {
                $container->gridTemplateColumns($templateCols);
            }
        }

        $rows = $grid['rows'] ?? null;
        if ($rows !== null) {
            $templateRows = $this->trackNormalizer->normalizeTrackList($rows);
            if ($templateRows !== '') {
                $container->gridTemplateRows($templateRows);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    public function applyLayoutSlotMetadata(Container $container, array $rule): void
    {
        if (! array_key_exists('layout_slot', $rule)) {
            return;
        }

        $rawSlot = $rule['layout_slot'];
        if ($rawSlot === false || $rawSlot === null) {
            return;
        }

        $slotName = is_string($rawSlot) && trim($rawSlot) !== ''
            ? trim($rawSlot)
            : ($container->getName() ?? 'slot_'.$container->getId());

        $aliases = $this->extractSlotAliases($rule);
        $screen = $this->extractNonEmptyString($rule, 'screen');
        $persist = array_key_exists('persist_on_navigate', $rule) ? (bool) $rule['persist_on_navigate'] : true;

        $container->layoutSlot($slotName, $aliases, $screen, $persist);
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    public function applyPlacementToElement(
        UIElement $element,
        string $gridColumn,
        ?string $gridRow,
        ?string $alignSelf,
        ?string $justifySelf,
        array $rule = [],
        ?string $callingContext = null,
    ): void {
        if (! ($element instanceof UIComponent || $element instanceof Container)) {
            return;
        }

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

        $this->applyElementCustomProperties($element, $rule, $callingContext);
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    public function applyElementCustomProperties(
        UIElement $element,
        array $rule,
        ?string $callingContext = null,
    ): void {
        $hasExternalLayoutRef = $this->definitionParser->extractExternalLayoutReference($rule) !== null;

        foreach ($this->orderCustomRuleProperties($rule) as $key => $value) {
            $keyStr = (string) $key;
            if ($this->shouldSkipCustomProperty($keyStr, $value, $hasExternalLayoutRef)) {
                continue;
            }

            $this->applySingleCustomProperty($element, $keyStr, $value, $callingContext);
        }
    }

    /**
     * @param  array<string, mixed>  $mainDef
     */
    private function applyContainerFlexAlignment(Container $container, array $mainDef): void
    {
        if (isset($mainDef['layout']) && is_string($mainDef['layout'])) {
            $enumCase = LayoutType::tryFrom(strtolower(trim($mainDef['layout'])));
            if ($enumCase !== null) {
                $container->layout($enumCase);
            }
        }

        if (isset($mainDef['align_items']) && is_string($mainDef['align_items'])) {
            $container->alignItems(trim($mainDef['align_items']));
        }

        if (isset($mainDef['justify_content']) && is_string($mainDef['justify_content'])) {
            $container->justifyContent(trim($mainDef['justify_content']));
        }
    }

    /**
     * @param  array<string, mixed>  $props
     */
    private function applyBoxSizeProperties(Container $container, array $props): void
    {
        foreach (self::BOX_SIZE_METHODS as $key => $method) {
            $value = $props[$key] ?? null;
            if ((is_int($value) || is_string($value) || $value instanceof Size) && method_exists($container, $method)) {
                $container->{$method}(Size::from($value));
            }
        }
    }

    /**
     * @param  array<string, mixed>  $props
     */
    private function applyBoxSpacingProperties(Container $container, array $props): void
    {
        foreach (self::BOX_SPACING_METHODS as $key => $method) {
            $value = $props[$key] ?? null;
            if ((is_int($value) || is_string($value)) && method_exists($container, $method)) {
                $container->{$method}(Spacing::from($value));
            }
        }
    }

    private function shouldSkipCustomProperty(string $key, mixed $value, bool $hasExternalLayoutRef): bool
    {
        if (in_array($key, self::IGNORED_CUSTOM_KEYS, true) || $value === null) {
            return true;
        }

        return $key === 'layout' && $hasExternalLayoutRef;
    }

    private function applySingleCustomProperty(
        UIElement $element,
        string $key,
        mixed $value,
        ?string $callingContext,
    ): void {
        if ($this->tryApplyContainerSpecialProperty($element, $key, $value)) {
            return;
        }

        if ($this->tryApplyDimensionProperty($element, $key, $value)) {
            return;
        }

        if ($key === 'center' && $value === true && method_exists($element, 'center')) {
            $element->center();

            return;
        }

        $resolvedValue = $this->resolveCustomPropertyValue($key, $value, $callingContext);
        $methodName = Str::camel($key);
        if (method_exists($element, $methodName)) {
            $element->{$methodName}($resolvedValue);
        }
    }

    private function tryApplyContainerSpecialProperty(UIElement $element, string $key, mixed $value): bool
    {
        if (! ($element instanceof Container)) {
            return false;
        }

        if ($this->tryApplyContainerSpacingProperty($element, $key, $value)) {
            return true;
        }

        if ($key === 'card' && $value === true) {
            $element->card();

            return true;
        }

        if (in_array($key, ['center_horizontal', 'center'], true) && $value === true) {
            $element->centerHorizontal();

            return true;
        }

        return false;
    }

    private function tryApplyContainerSpacingProperty(Container $element, string $key, mixed $value): bool
    {
        if (! in_array($key, self::CONTAINER_SPACING_KEYS, true)) {
            return false;
        }

        if (! (is_string($value) || is_int($value) || $value instanceof Spacing)) {
            return false;
        }

        $methodName = Str::camel($key);
        if (method_exists($element, $methodName)) {
            $element->{$methodName}(Spacing::from($value));
        }

        return true;
    }

    private function tryApplyDimensionProperty(UIElement $element, string $key, mixed $value): bool
    {
        if (! in_array($key, self::DIMENSION_KEYS, true)
            || ! ($element instanceof UIComponent || $element instanceof Container)) {
            return false;
        }

        if (is_string($value) || is_int($value) || $value instanceof Size) {
            $methodName = Str::camel($key);
            if (method_exists($element, $methodName)) {
                $element->{$methodName}(Size::from($value));
            }
        }

        return true;
    }

    private function resolveCustomPropertyValue(string $key, mixed $value, ?string $callingContext): mixed
    {
        if (is_string($value) && in_array($key, self::TRANSLATABLE_TEXT_PROPERTIES, true)) {
            return TranslationResolver::resolveText($value, $callingContext);
        }

        if ($key === 'header_actions' && is_array($value)) {
            return $this->resolveHeaderActionsTranslations($value, $callingContext);
        }

        return $value;
    }

    /**
     * @param  array<int|string, mixed>  $actions
     * @return list<mixed>
     */
    private function resolveHeaderActionsTranslations(array $actions, ?string $callingContext): array
    {
        $resolvedActions = [];
        foreach ($actions as $entry) {
            if (is_array($entry)) {
                $entry = $this->translateHeaderActionEntry($entry, $callingContext);
            }
            $resolvedActions[] = $entry;
        }

        return $resolvedActions;
    }

    /**
     * @param  array<int|string, mixed>  $entry
     * @return array<int|string, mixed>
     */
    private function translateHeaderActionEntry(array $entry, ?string $callingContext): array
    {
        foreach (['label', 'tooltip'] as $field) {
            if (isset($entry[$field]) && is_string($entry[$field])) {
                $entry[$field] = TranslationResolver::resolveText($entry[$field], $callingContext);
            }
        }

        return $entry;
    }

    /**
     * @param  array<string, mixed>  $rule
     * @return array<string, mixed>
     */
    private function orderCustomRuleProperties(array $rule): array
    {
        $ordered = [];

        foreach (self::PRIORITY_CUSTOM_KEYS as $priorityKey) {
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
     * @param  array<string, mixed>  $rule
     * @return list<string>
     */
    private function extractSlotAliases(array $rule): array
    {
        if (! isset($rule['slot_aliases']) || ! is_array($rule['slot_aliases'])) {
            return [];
        }

        $aliases = [];
        foreach ($rule['slot_aliases'] as $alias) {
            if (is_string($alias) && trim($alias) !== '') {
                $aliases[] = trim($alias);
            }
        }

        return $aliases;
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    private function extractNonEmptyString(array $rule, string $key): ?string
    {
        if (! isset($rule[$key]) || ! is_string($rule[$key])) {
            return null;
        }

        $trimmed = trim($rule[$key]);

        return $trimmed !== '' ? $trimmed : null;
    }
}
