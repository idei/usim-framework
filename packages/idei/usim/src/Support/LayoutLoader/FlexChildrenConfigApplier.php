<?php

declare(strict_types=1);

namespace Idei\Usim\Support\LayoutLoader;

use Idei\Usim\Components\Container;
use Idei\Usim\Contracts\UIElement;

/**
 * Applies children rules for non-grid (flex / horizontal / vertical) containers declaring `children:`.
 */
final class FlexChildrenConfigApplier
{
    public function __construct(
        private readonly ChildRuleMatcher $ruleMatcher,
        private readonly NestedLayoutApplier $nestedLayoutApplier,
        private readonly ElementPropertyApplier $propertyApplier,
        private readonly TranslationAutoHydrator $translationHydrator,
    ) {}

    /**
     * @param  array<string, mixed>  $definition
     */
    public function apply(
        Container $container,
        array $definition,
        LayoutApplicationContext $context,
        ContainerLayoutApplicator $applicator,
    ): void {
        $translationTree = $this->translationHydrator->resolveTranslationTree($context);
        $indexed = $this->ruleMatcher->indexChildrenRules($definition);

        if ($indexed['orderedSlotNames'] !== []) {
            $container->reorderChildrenByNames($indexed['orderedSlotNames']);
        }

        foreach ($container->getChildren() as $idx => $child) {
            $this->applyToChild(
                $child,
                $idx + 1,
                $indexed['byName'],
                $indexed['byPosition'],
                $translationTree,
                $context,
                $applicator,
            );
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $rulesByName
     * @param  array<int, array<string, mixed>>  $rulesByPosition
     * @param  array<string, mixed>  $translationTree
     */
    private function applyToChild(
        UIElement $child,
        int $position,
        array $rulesByName,
        array $rulesByPosition,
        array $translationTree,
        LayoutApplicationContext $context,
        ContainerLayoutApplicator $applicator,
    ): void {
        $explicitSlot = $this->ruleMatcher->getExplicitSlot($child);
        if ($this->ruleMatcher->shouldSkipChild($child, $explicitSlot, $rulesByName)) {
            return;
        }

        $childName = $child->getName();
        $rule = $this->ruleMatcher->resolveRuleForChild($explicitSlot, $childName, $position, $rulesByName, $rulesByPosition);

        $this->nestedLayoutApplier->applyChildStructuralLayouts($child, $rule, $context, $applicator, allowInlineChildren: true);
        $this->propertyApplier->applyElementCustomProperties($child, $rule, $context->callingContext);

        if ($translationTree !== []) {
            $resolvedSlot = $this->ruleMatcher->resolveTranslationSlot($rule, $explicitSlot, $childName);
            $this->translationHydrator->autoHydrateElementTranslations($child, $resolvedSlot, $translationTree);
        }
    }
}
