<?php

declare(strict_types=1);

namespace Idei\Usim\Support\LayoutLoader;

use Idei\Usim\Enums\LayoutType;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Pure parser and normalizer for declarative `.layout.yaml` definition arrays.
 */
final class LayoutDefinitionParser
{
    /** @var list<string> */
    private const MAIN_SLOT_ALIASES = ['main', 'center', 'content'];

    /** @var list<string> */
    private const MAIN_SLOT_SKIP_KEYS = ['open', 'size', 'fallback', 'persist_on_navigate', 'screen', 'params'];

    /** @var list<string> */
    private const SLOT_CONTROL_KEYS = ['open', 'size', 'fallback', 'persist_on_navigate', 'screen', 'params', 'name'];

    /** @var list<string> */
    private const ALLOWED_FALLBACKS = ['ignore', 'inline', 'inline_top', 'inline_bottom'];

    /** @var list<string> */
    private const EXTERNAL_LAYOUT_KEYS = ['layout_file', 'include', 'file'];

    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    public function resolveMainDefinition(array $definition): array
    {
        $hasRootGrid = isset($definition['grid']) && is_array($definition['grid']);
        $slots = isset($definition['slots']) && is_array($definition['slots']) ? $definition['slots'] : [];

        $mainSlotKey = $this->findMainSlotKey($slots);

        if ($hasRootGrid && $mainSlotKey !== null) {
            throw new InvalidArgumentException(
                "Layout definition cannot declare both root 'grid' and 'slots.{$mainSlotKey}'. Choose one."
            );
        }

        if ($mainSlotKey === null) {
            return $definition;
        }

        $mainSlotRule = is_array($slots[$mainSlotKey]) ? $slots[$mainSlotKey] : [];

        return $this->mergeMainSlotRule($definition, $mainSlotRule);
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return list<array{
     *     slot: string,
     *     container_name: string,
     *     open: bool,
     *     size: ?string,
     *     fallback: string,
     *     persist_on_navigate: bool,
     *     screen: ?string,
     *     params: array<int|string, mixed>,
     *     rule: array<string, mixed>
     * }>
     */
    public function extractSlotDefinitions(array $definition): array
    {
        $this->resolveMainDefinition($definition);

        $slots = $definition['slots'] ?? null;
        if (! is_array($slots)) {
            return [];
        }

        $entries = [];
        foreach ($slots as $rawSlotName => $rawRule) {
            if (! is_string($rawSlotName) || trim($rawSlotName) === '') {
                continue;
            }

            $slotName = trim($rawSlotName);
            if (in_array($slotName, self::MAIN_SLOT_ALIASES, true)) {
                continue;
            }

            $rule = is_array($rawRule) ? $rawRule : [];
            $entries[] = $this->buildSlotEntry($slotName, $rule);
        }

        return $entries;
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return list<string>
     */
    public function extractChildSlotNames(array $definition): array
    {
        $children = $this->extractRawChildren($definition);
        if ($children === []) {
            return [];
        }

        $slotNames = [];
        $idx = 0;
        foreach ($children as $key => $entry) {
            $idx++;
            $normalized = $this->normalizeChildEntry($key, $entry, $idx);
            $slot = $normalized['slot'];
            if (is_string($slot) && trim($slot) !== '' && ! is_numeric($slot)) {
                $slotNames[] = trim($slot);
            }
        }

        return $slotNames;
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<int|string, array<string, mixed>>
     */
    public function extractChildRulesBySlot(array $definition): array
    {
        $children = $this->extractRawChildren($definition);
        if ($children === []) {
            return [];
        }

        $rules = [];
        $idx = 0;
        foreach ($children as $key => $entry) {
            $idx++;
            $normalized = $this->normalizeChildEntry($key, $entry, $idx);
            $slotKey = trim((string) $normalized['slot']);
            if ($slotKey !== '') {
                $rules[$slotKey] = $normalized['rule'];
            }
            $rules[(string) $idx] = $normalized['rule'];
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    public function isNestedContainerRule(array $rule): bool
    {
        if (isset($rule['grid']) && is_array($rule['grid'])) {
            return true;
        }

        return $this->extractExternalLayoutReference($rule) !== null;
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    public function extractExternalLayoutReference(array $rule): ?string
    {
        foreach (self::EXTERNAL_LAYOUT_KEYS as $key) {
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
     * @param  array<string, mixed>  $definition
     * @return list<array{name: string, rule: array<string, mixed>}>
     */
    public function extractTimerEntries(array $definition): array
    {
        $timers = $definition['timers'] ?? null;
        if (! is_array($timers)) {
            return [];
        }

        $entries = [];
        $idx = 0;
        foreach ($timers as $key => $entry) {
            $idx++;
            $normalized = $this->normalizeChildEntry($key, $entry, $idx);
            $slot = $normalized['slot'];
            $name = is_string($slot) && trim($slot) !== '' ? trim($slot) : "tmr_{$idx}";

            $entries[] = [
                'name' => $name,
                'rule' => $normalized['rule'],
            ];
        }

        return $entries;
    }

    /**
     * @return array{slot: string|int, rule: array<string, mixed>}
     */
    public function normalizeChildEntry(mixed $key, mixed $entry, int $position): array
    {
        if (is_string($entry) && trim($entry) !== '') {
            $slot = trim($entry);

            return ['slot' => $slot, 'rule' => ['slot' => $slot]];
        }

        $assoc = $this->normalizeAssociativeKeyEntry($key, $entry);
        if ($assoc !== null) {
            return $assoc;
        }

        if (! is_array($entry)) {
            return ['slot' => $position, 'rule' => ['slot' => $position]];
        }

        if (isset($entry['slot'])) {
            return [
                'slot' => (is_string($entry['slot']) || is_int($entry['slot'])) ? $entry['slot'] : $position,
                'rule' => $this->normalizeStringKeyedArray($entry),
            ];
        }

        return $this->normalizeSingleKeyEntry($entry)
            ?? ['slot' => $position, 'rule' => $this->normalizeStringKeyedArray($entry)];
    }

    /**
     * @return array{slot: string, rule: array<string, mixed>}|null
     */
    private function normalizeAssociativeKeyEntry(mixed $key, mixed $entry): ?array
    {
        if (! is_string($key) || trim($key) === '' || ctype_digit(trim($key))) {
            return null;
        }

        $slot = trim($key);
        $rule = is_array($entry) ? $this->normalizeStringKeyedArray($entry) : [];
        $rule['slot'] = $rule['slot'] ?? $slot;

        return ['slot' => $slot, 'rule' => $rule];
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    public function isAutoTranslateDefinition(array $definition): bool
    {
        $topFlag = $definition['i18n'] ?? null;
        $grid = isset($definition['grid']) && is_array($definition['grid']) ? $definition['grid'] : [];
        $gridFlag = $grid['i18n'] ?? null;

        return in_array($topFlag, ['auto', true], true) || in_array($gridFlag, ['auto', true], true);
    }

    /**
     * @param  array<int|string, mixed>  $slots
     */
    private function findMainSlotKey(array $slots): ?string
    {
        $mainSlotKey = null;
        foreach (self::MAIN_SLOT_ALIASES as $candidate) {
            if (! array_key_exists($candidate, $slots)) {
                continue;
            }

            if ($mainSlotKey !== null) {
                throw new InvalidArgumentException(
                    "Layout definition cannot declare multiple main slot aliases ('{$mainSlotKey}' and '{$candidate}')."
                );
            }

            $mainSlotKey = $candidate;
        }

        return $mainSlotKey;
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<int|string, mixed>  $mainSlotRule
     * @return array<string, mixed>
     */
    private function mergeMainSlotRule(array $definition, array $mainSlotRule): array
    {
        $merged = $definition;
        unset($merged['slots']);

        foreach ($mainSlotRule as $key => $value) {
            if (in_array($key, self::MAIN_SLOT_SKIP_KEYS, true)) {
                continue;
            }
            $merged[(string) $key] = $value;
        }

        return $merged;
    }

    /**
     * @param  array<int|string, mixed>  $rule
     * @return array{
     *     slot: string,
     *     container_name: string,
     *     open: bool,
     *     size: ?string,
     *     fallback: string,
     *     persist_on_navigate: bool,
     *     screen: ?string,
     *     params: array<int|string, mixed>,
     *     rule: array<string, mixed>
     * }
     */
    private function buildSlotEntry(string $slotName, array $rule): array
    {
        $fallback = $this->resolveSlotFallback($slotName, $rule);
        $size = $this->normalizeSlotSize($rule['size'] ?? null);
        $open = array_key_exists('open', $rule) ? (bool) $rule['open'] : true;
        $persistOnNavigate = array_key_exists('persist_on_navigate', $rule) ? (bool) $rule['persist_on_navigate'] : false;
        $screen = $this->extractNonEmptyString($rule, 'screen');

        /** @var array<int|string, mixed> $params */
        $params = isset($rule['params']) && is_array($rule['params']) ? $rule['params'] : [];
        $containerName = $this->extractNonEmptyString($rule, 'name') ?? '__portal_'.Str::snake($slotName);
        $cleanRule = $this->stripSlotControlKeys($rule);

        if ($screen === null && ! isset($cleanRule['grid']) && $this->extractExternalLayoutReference($cleanRule) === null) {
            $cleanRule = $this->applyInlineSlotDefaults($slotName, $cleanRule);
        }

        return [
            'slot' => $slotName,
            'container_name' => $containerName,
            'open' => $open,
            'size' => $size,
            'fallback' => $fallback,
            'persist_on_navigate' => $persistOnNavigate,
            'screen' => $screen,
            'params' => $params,
            'rule' => $cleanRule,
        ];
    }

    /**
     * @param  array<int|string, mixed>  $rule
     */
    private function resolveSlotFallback(string $slotName, array $rule): string
    {
        $rawFallback = isset($rule['fallback']) && is_string($rule['fallback'])
            ? strtolower(trim($rule['fallback']))
            : 'ignore';

        if (! in_array($rawFallback, self::ALLOWED_FALLBACKS, true)) {
            throw new InvalidArgumentException(
                "Invalid fallback mode '{$rawFallback}' for slot '{$slotName}'. Allowed: ignore, inline, inline_top, inline_bottom."
            );
        }

        return $rawFallback;
    }

    private function normalizeSlotSize(mixed $rawSize): ?string
    {
        if (is_int($rawSize) || is_float($rawSize)) {
            return "{$rawSize}px";
        }

        if (is_string($rawSize) && trim($rawSize) !== '') {
            $trimmed = trim($rawSize);

            return is_numeric($trimmed) ? "{$trimmed}px" : $trimmed;
        }

        return null;
    }

    /**
     * @param  array<int|string, mixed>  $rule
     * @return array<string, mixed>
     */
    private function stripSlotControlKeys(array $rule): array
    {
        $cleanRule = [];
        foreach ($rule as $k => $v) {
            $kStr = (string) $k;
            if (! in_array($kStr, self::SLOT_CONTROL_KEYS, true)) {
                $cleanRule[$kStr] = $v;
            }
        }

        return $cleanRule;
    }

    /**
     * @param  array<string, mixed>  $cleanRule
     * @return array<string, mixed>
     */
    private function applyInlineSlotDefaults(string $slotName, array $cleanRule): array
    {
        $isStatusBarSlot = str_starts_with($slotName, 'status_bar') || $slotName === 'statusbar';
        $defaults = $isStatusBarSlot
            ? [
                'appearance' => 'plain',
                'padding' => 'none',
                'layout' => LayoutType::HORIZONTAL->value,
                'align_items' => 'center',
                'gap' => 's',
            ]
            : [
                'appearance' => 'plain',
                'width' => '100%',
                'layout' => LayoutType::VERTICAL->value,
                'gap' => 's',
            ];

        foreach ($defaults as $key => $defaultValue) {
            $cleanRule[$key] ??= $defaultValue;
        }

        return $cleanRule;
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<int|string, mixed>
     */
    private function extractRawChildren(array $definition): array
    {
        $resolved = $this->resolveMainDefinition($definition);
        $grid = isset($resolved['grid']) && is_array($resolved['grid']) ? $resolved['grid'] : $resolved;
        $children = $grid['children'] ?? null;

        return is_array($children) ? $children : [];
    }

    /**
     * @param  array<int|string, mixed>  $entry
     * @return array{slot: string, rule: array<string, mixed>}|null
     */
    private function normalizeSingleKeyEntry(array $entry): ?array
    {
        if (count($entry) !== 1) {
            return null;
        }

        $entryKey = array_key_first($entry);
        if (! is_string($entryKey) || trim($entryKey) === '') {
            return null;
        }

        $slot = trim($entryKey);
        $rule = is_array($entry[$entryKey]) ? $this->normalizeStringKeyedArray($entry[$entryKey]) : [];
        $rule['slot'] = $rule['slot'] ?? $slot;

        return ['slot' => $slot, 'rule' => $rule];
    }

    /**
     * @param  array<int|string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeStringKeyedArray(array $data): array
    {
        $normalized = [];
        foreach ($data as $k => $v) {
            $normalized[(string) $k] = $v;
        }

        return $normalized;
    }

    /**
     * @param  array<int|string, mixed>  $source
     */
    private function extractNonEmptyString(array $source, string $key): ?string
    {
        if (! isset($source[$key]) || ! is_string($source[$key])) {
            return null;
        }

        $trimmed = trim($source[$key]);

        return $trimmed !== '' ? $trimmed : null;
    }
}
