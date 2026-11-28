<?php

declare(strict_types=1);

namespace Idei\Usim\Support\LayoutLoader;

use Idei\Usim\Components\Container;
use Idei\Usim\Contracts\UIElement;

/**
 * Applies CSS Grid child placement, slot ordering, auto-naming, spans, alignments,
 * nested layouts, translations, and auto-computed row tracks.
 */
final class GridChildrenConfigApplier
{
    public function __construct(
        private readonly ChildRuleMatcher $ruleMatcher,
        private readonly GridTrackNormalizer $trackNormalizer,
        private readonly NestedLayoutApplier $nestedLayoutApplier,
        private readonly ElementPropertyApplier $propertyApplier,
        private readonly TranslationAutoHydrator $translationHydrator,
    ) {}

    /**
     * @param  array<string, mixed>  $grid
     */
    public function apply(
        Container $container,
        array $grid,
        LayoutApplicationContext $context,
        ContainerLayoutApplicator $applicator,
    ): void {
        $translationTree = $this->translationHydrator->resolveTranslationTree($context);
        $columnCount = $this->trackNormalizer->resolveColumnCount($grid['cols'] ?? $grid['columns'] ?? 1);
        $defaults = [
            'columnCount' => $columnCount,
            'span' => $this->trackNormalizer->resolveDefaultColSpan($grid['default_span'] ?? 1, $columnCount),
            'align' => $this->extractTrimmedString($grid, 'align'),
            'justify' => $this->extractTrimmedString($grid, 'justify'),
        ];

        $indexed = $this->ruleMatcher->indexGridRules($grid);
        $currentChildren = $this->reorderChildren($container, $indexed['orderedSlotNames']);
        $this->autoNameChildren($currentChildren, $indexed['byPosition']);

        $hasExplicitContainerRows = isset($grid['rows']);
        $rowAccumulator = $hasExplicitContainerRows
            ? null
            : new GridRowTrackAccumulator($columnCount, $this->trackNormalizer);

        foreach ($currentChildren as $idx => $child) {
            $this->applyToChild(
                $child,
                $idx + 1,
                $indexed['byName'],
                $indexed['byPosition'],
                $defaults,
                $translationTree,
                $rowAccumulator,
                $context,
                $applicator,
            );
        }

        $computedRows = $rowAccumulator?->buildTemplateRows();
        if ($computedRows !== null) {
            $container->gridTemplateRows($computedRows);
        }
    }

    /**
     * @param  list<string>  $orderedSlotNames
     * @return array<int, UIElement>
     */
    private function reorderChildren(Container $container, array $orderedSlotNames): array
    {
        $currentChildren = $container->getChildren();
        if ($orderedSlotNames === []) {
            $orderedSlotNames = $this->inferNumericSlotOrder($currentChildren);
        }

        if ($orderedSlotNames !== []) {
            $container->reorderChildrenByNames($orderedSlotNames);

            return $container->getChildren();
        }

        return $currentChildren;
    }

    /**
     * @param  array<int, UIElement>  $children
     * @return list<string>
     */
    private function inferNumericSlotOrder(array $children): array
    {
        if (count($children) <= 1) {
            return [];
        }

        foreach ($children as $child) {
            $childSlot = $this->ruleMatcher->getExplicitSlot($child);
            if (is_int($childSlot) || (is_string($childSlot) && ctype_digit(trim($childSlot)))) {
                return array_map('strval', range(1, count($children)));
            }
        }

        return [];
    }

    /**
     * @param  array<int, UIElement>  $children
     * @param  array<int, array<string, mixed>>  $rulesByPosition
     */
    private function autoNameChildren(array $children, array $rulesByPosition): void
    {
        foreach ($children as $idx => $child) {
            if ($child->getName() !== null) {
                continue;
            }

            $inferredName = $this->resolveAutoChildName($child, $idx + 1, $rulesByPosition);
            if ($inferredName !== null) {
                $child->setName($inferredName);
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $rulesByPosition
     */
    private function resolveAutoChildName(UIElement $child, int $position, array $rulesByPosition): ?string
    {
        $explicitSlot = $this->ruleMatcher->getExplicitSlot($child);
        if ($this->isNonNumericSlotName($explicitSlot)) {
            return trim($explicitSlot);
        }

        $slotCandidate = $rulesByPosition[$position]['slot'] ?? null;
        if ($this->isNonNumericSlotName($slotCandidate)) {
            return trim($slotCandidate);
        }

        return null;
    }

    /**
     * @phpstan-assert-if-true string $value
     */
    private function isNonNumericSlotName(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '' && ! ctype_digit(trim($value));
    }

    /**
     * @param  array<string, array<string, mixed>>  $rulesByName
     * @param  array<int, array<string, mixed>>  $rulesByPosition
     * @param  array{columnCount: int, span: int, align: ?string, justify: ?string}  $defaults
     * @param  array<string, mixed>  $translationTree
     */
    private function applyToChild(
        UIElement $child,
        int $position,
        array $rulesByName,
        array $rulesByPosition,
        array $defaults,
        array $translationTree,
        ?GridRowTrackAccumulator $rowAccumulator,
        LayoutApplicationContext $context,
        ContainerLayoutApplicator $applicator,
    ): void {
        $explicitSlot = $this->ruleMatcher->getExplicitSlot($child);
        if ($this->ruleMatcher->shouldSkipChild($child, $explicitSlot, $rulesByName)) {
            return;
        }

        $childName = $child->getName();
        $rule = $this->ruleMatcher->resolveRuleForChild($explicitSlot, $childName, $position, $rulesByName, $rulesByPosition);
        $placement = $this->trackNormalizer->resolveGridPlacement($rule, $defaults['span'], $defaults['columnCount']);

        $align = $this->extractTrimmedString($rule, 'align') ?? $defaults['align'];
        $justify = $this->extractTrimmedString($rule, 'justify') ?? $defaults['justify'];

        $this->nestedLayoutApplier->applyChildStructuralLayouts($child, $rule, $context, $applicator, allowInlineChildren: false);
        $this->propertyApplier->applyPlacementToElement(
            $child,
            $placement['gridColumn'],
            $placement['gridRow'],
            $align,
            $justify,
            $rule,
            $context->callingContext,
        );

        if ($translationTree !== []) {
            $resolvedSlot = $this->ruleMatcher->resolveTranslationSlot($rule, $explicitSlot, $childName);
            $this->translationHydrator->autoHydrateElementTranslations($child, $resolvedSlot, $translationTree);
        }

        if ($rowAccumulator !== null && $child->isVisible()) {
            $rowAccumulator->recordVisibleChild($placement['colSpan'], $rule);
        }
    }

    /**
     * @param  array<string, mixed>  $source
     */
    private function extractTrimmedString(array $source, string $key): ?string
    {
        return isset($source[$key]) && is_string($source[$key]) ? trim($source[$key]) : null;
    }
}
