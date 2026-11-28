<?php

declare(strict_types=1);

namespace Idei\Usim\Support\LayoutLoader;

use Idei\Usim\Components\Container;
use Idei\Usim\Components\Timer;
use Idei\Usim\Components\UIComponent;
use Idei\Usim\Contracts\UIElement;

/**
 * Indexes declarative child rules (by slot name and 1-based position) and matches
 * them against container children for both Flex and Grid layouts.
 */
final class ChildRuleMatcher
{
    public function __construct(
        private readonly LayoutDefinitionParser $definitionParser,
    ) {}

    /**
     * Index `children:` entries into name/position lookup tables and ordered slot names.
     *
     * @param  array<string, mixed>  $definition
     * @return array{
     *     byName: array<string, array<string, mixed>>,
     *     byPosition: array<int, array<string, mixed>>,
     *     orderedSlotNames: list<string>
     * }
     */
    public function indexChildrenRules(array $definition): array
    {
        $byName = [];
        $byPosition = [];
        $orderedSlotNames = [];

        if (! isset($definition['children']) || ! is_array($definition['children'])) {
            return [
                'byName' => $byName,
                'byPosition' => $byPosition,
                'orderedSlotNames' => $orderedSlotNames,
            ];
        }

        $idx = 0;
        foreach ($definition['children'] as $key => $entry) {
            $idx++;
            $normalized = $this->definitionParser->normalizeChildEntry($key, $entry, $idx);
            $this->registerIndexedChild($normalized['slot'], $normalized['rule'], $idx, $byName, $byPosition, $orderedSlotNames);
        }

        return [
            'byName' => $byName,
            'byPosition' => $byPosition,
            'orderedSlotNames' => $orderedSlotNames,
        ];
    }

    /**
     * Index both `children:` and legacy `items:` declarations for grid layouts.
     *
     * @param  array<string, mixed>  $grid
     * @return array{
     *     byName: array<string, array<string, mixed>>,
     *     byPosition: array<int, array<string, mixed>>,
     *     orderedSlotNames: list<string>
     * }
     */
    public function indexGridRules(array $grid): array
    {
        $indexed = $this->indexChildrenRules($grid);

        if (! isset($grid['items']) || ! is_array($grid['items'])) {
            return $indexed;
        }

        foreach ($grid['items'] as $slotKey => $entry) {
            if (! is_array($entry)) {
                continue;
            }

            /** @var array<string, mixed> $rule */
            $rule = $entry;
            $this->registerGridItemRule($slotKey, $rule, $indexed['byName'], $indexed['byPosition']);
        }

        return $indexed;
    }

    public function getExplicitSlot(UIElement $child): int|string|null
    {
        if ($child instanceof UIComponent || $child instanceof Container) {
            return $child->getSlot();
        }

        return null;
    }

    /**
     * @param  array<string, array<string, mixed>>  $rulesByName
     */
    public function shouldSkipChild(UIElement $child, int|string|null $explicitSlot, array $rulesByName): bool
    {
        if ($child instanceof Container && $child->getPortalSlot() !== null) {
            return true;
        }

        if (! ($child instanceof Timer)) {
            return false;
        }

        $childName = $child->getName();

        return $explicitSlot === null && ($childName === null || ! isset($rulesByName[$childName]));
    }

    /**
     * @param  array<string, array<string, mixed>>  $rulesByName
     * @param  array<int, array<string, mixed>>  $rulesByPosition
     * @return array<string, mixed>
     */
    public function resolveRuleForChild(
        int|string|null $explicitSlot,
        ?string $childName,
        int $position,
        array $rulesByName,
        array $rulesByPosition,
    ): array {
        if ($explicitSlot !== null) {
            return $this->resolveRuleByExplicitSlot($explicitSlot, $position, $rulesByName, $rulesByPosition);
        }

        if ($childName !== null && isset($rulesByName[$childName])) {
            return $rulesByName[$childName];
        }

        return $rulesByPosition[$position] ?? [];
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    public function resolveTranslationSlot(array $rule, int|string|null $explicitSlot, ?string $childName): int|string|null
    {
        $resolvedSlot = $rule['slot'] ?? $explicitSlot ?? $childName;

        return (is_int($resolvedSlot) || is_string($resolvedSlot)) ? $resolvedSlot : null;
    }

    /**
     * @param  array<string, mixed>  $rule
     * @param  array<string, array<string, mixed>>  $byName
     * @param  array<int, array<string, mixed>>  $byPosition
     * @param  list<string>  $orderedSlotNames
     */
    private function registerIndexedChild(
        int|string $slot,
        array $rule,
        int $position,
        array &$byName,
        array &$byPosition,
        array &$orderedSlotNames,
    ): void {
        if (is_int($slot) || ctype_digit(trim($slot))) {
            $slotPos = (int) $slot;
            $byPosition[$slotPos] = $rule;
            $orderedSlotNames[] = (string) $slotPos;

            return;
        }

        $slotName = trim($slot);
        if ($slotName !== '') {
            $byName[$slotName] = $rule;
            $byPosition[$position] = $rule;
            $orderedSlotNames[] = $slotName;
        }
    }

    /**
     * @param  array<string, mixed>  $rule
     * @param  array<string, array<string, mixed>>  $byName
     * @param  array<int, array<string, mixed>>  $byPosition
     */
    private function registerGridItemRule(
        int|string $slotKey,
        array $rule,
        array &$byName,
        array &$byPosition,
    ): void {
        if (is_int($slotKey) || ctype_digit(trim($slotKey))) {
            $byPosition[(int) $slotKey] = $rule;

            return;
        }

        $trimmed = trim($slotKey);
        if ($trimmed !== '') {
            $byName[$trimmed] = $rule;
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $rulesByName
     * @param  array<int, array<string, mixed>>  $rulesByPosition
     * @return array<string, mixed>
     */
    private function resolveRuleByExplicitSlot(
        int|string $explicitSlot,
        int $position,
        array $rulesByName,
        array $rulesByPosition,
    ): array {
        if (is_int($explicitSlot) || ctype_digit(trim($explicitSlot))) {
            return $rulesByPosition[(int) $explicitSlot] ?? [];
        }

        $trimmed = trim($explicitSlot);
        if ($trimmed === '') {
            return [];
        }

        return $rulesByName[$trimmed] ?? ($rulesByPosition[$position] ?? []);
    }
}
