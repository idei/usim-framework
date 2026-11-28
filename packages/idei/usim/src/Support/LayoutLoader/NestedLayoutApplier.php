<?php

declare(strict_types=1);

namespace Idei\Usim\Support\LayoutLoader;

use Idei\Usim\Components\Container;
use Idei\Usim\Components\Split;
use Idei\Usim\Contracts\UIElement;
use Idei\Usim\ValueObjects\Size;
use InvalidArgumentException;

/**
 * Applies nested container layouts (inline `grid` or external `.layout.yaml` includes
 * with cycle detection) and declarative `Split` pane configurations.
 */
final class NestedLayoutApplier
{
    /** @var list<string> */
    private const PROPAGATED_BOX_KEYS = [
        'appearance', 'card', 'center_horizontal', 'center',
        'padding', 'gap', 'row_gap', 'column_gap',
        'width', 'min_width', 'max_width', 'height', 'min_height', 'max_height',
        'i18n',
    ];

    public function __construct(
        private readonly LayoutFileLoader $fileLoader,
        private readonly LayoutDefinitionParser $definitionParser,
        private readonly ElementPropertyApplier $propertyApplier,
    ) {}

    /**
     * Apply nested container, inline flex children (when allowed), Split panes, and layout slot metadata for a child.
     *
     * @param  array<string, mixed>  $rule
     */
    public function applyChildStructuralLayouts(
        UIElement $child,
        array $rule,
        LayoutApplicationContext $context,
        ContainerLayoutApplicator $applicator,
        bool $allowInlineChildren = false,
    ): void {
        if ($child instanceof Container) {
            $this->applyChildContainerRule($child, $rule, $context, $applicator, $allowInlineChildren);
        }

        if ($child instanceof Split && (isset($rule['first']) || isset($rule['second']))) {
            $this->applySplitPanesLayout($child, $rule, $context, $applicator);
        }

        if ($child instanceof Container) {
            $this->propertyApplier->applyLayoutSlotMetadata($child, $rule);
        }
    }

    /**
     * Resolve and apply a nested layout (inline `grid` or external `.layout.yaml` reference) to a child Container.
     *
     * @param  array<string, mixed>  $rule
     */
    public function applyNestedContainerLayout(
        Container $child,
        array $rule,
        LayoutApplicationContext $context,
        ContainerLayoutApplicator $applicator,
    ): void {
        $externalRef = $this->definitionParser->extractExternalLayoutReference($rule);
        if ($externalRef !== null) {
            [$nestedDef, $childContext] = $this->resolveExternalNestedLayout($child, $externalRef, $context);
        } else {
            /** @var array<string, mixed> $gridRule */
            $gridRule = $rule['grid'];
            $nestedDef = ['grid' => $gridRule];
            $childContext = $context;
        }

        $nestedDef = $this->propagateBoxOverrides($rule, $nestedDef);

        $child->layoutBaseDir($childContext->baseDir);
        $child->layoutDefinition($nestedDef, $childContext->callingContext, $childContext->autoTranslate);
        $applicator->applyToContainer($child, $nestedDef, $childContext);
    }

    /**
     * Build and configure declarative `first` and `second` panes inside a Split component.
     *
     * @param  array<string, mixed>  $rule
     */
    public function applySplitPanesLayout(
        Split $split,
        array $rule,
        LayoutApplicationContext $context,
        ContainerLayoutApplicator $applicator,
    ): void {
        foreach (['first', 'second'] as $side) {
            if (! isset($rule[$side]) || ! is_array($rule[$side])) {
                continue;
            }

            /** @var array<string, mixed> $paneRule */
            $paneRule = $rule[$side];
            $this->applySingleSplitPane($split, $side, $paneRule, $context, $applicator);
        }
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    private function applyChildContainerRule(
        Container $child,
        array $rule,
        LayoutApplicationContext $context,
        ContainerLayoutApplicator $applicator,
        bool $allowInlineChildren,
    ): void {
        if ($this->definitionParser->isNestedContainerRule($rule)) {
            $this->applyNestedContainerLayout($child, $rule, $context, $applicator);

            return;
        }

        if ($allowInlineChildren && isset($rule['children']) && is_array($rule['children'])) {
            $child->layoutBaseDir($context->baseDir);
            $child->layoutDefinition($rule, $context->callingContext, $context->autoTranslate);
            $applicator->applyToContainer($child, $rule, $context);
        }
    }

    /**
     * @return array{0: array<string, mixed>, 1: LayoutApplicationContext}
     */
    private function resolveExternalNestedLayout(
        Container $child,
        string $externalRef,
        LayoutApplicationContext $context,
    ): array {
        $meta = $this->fileLoader->loadFileWithMeta($externalRef, $context->callingContext, $context->baseDir);
        $resolvedPath = $meta['resolvedPath'];

        if (in_array($resolvedPath, $context->visitedPaths, true)) {
            throw new InvalidArgumentException("Circular layout reference detected for [{$resolvedPath}].");
        }

        $child->layoutSourcePath($resolvedPath);

        $childContext = $context
            ->withBaseDir(dirname($resolvedPath))
            ->withVisitedPath($resolvedPath);

        return [$meta['definition'], $childContext];
    }

    /**
     * @param  array<string, mixed>  $rule
     * @param  array<string, mixed>  $nestedDef
     * @return array<string, mixed>
     */
    private function propagateBoxOverrides(array $rule, array $nestedDef): array
    {
        foreach (self::PROPAGATED_BOX_KEYS as $boxKey) {
            if (array_key_exists($boxKey, $rule)) {
                $nestedDef[$boxKey] = $rule[$boxKey];
            }
        }

        return $nestedDef;
    }

    /**
     * @param  array<string, mixed>  $paneRule
     */
    private function applySingleSplitPane(
        Split $split,
        string $side,
        array $paneRule,
        LayoutApplicationContext $context,
        ContainerLayoutApplicator $applicator,
    ): void {
        $paneElement = $this->resolveOrCreateSplitPane($split, $side, $paneRule, $context->callingContext);
        $paneElement->splitPane($side);

        $this->propertyApplier->applyBoxProperties($paneElement, $paneRule);
        $this->propertyApplier->applyElementCustomProperties($paneElement, $paneRule, $context->callingContext);
        $this->propertyApplier->applyLayoutSlotMetadata($paneElement, $paneRule);

        if ($this->definitionParser->isNestedContainerRule($paneRule)) {
            $this->applyNestedContainerLayout($paneElement, $paneRule, $context, $applicator);
        }

        if ($paneElement instanceof Split && (isset($paneRule['first']) || isset($paneRule['second']))) {
            $this->applySplitPanesLayout($paneElement, $paneRule, $context, $applicator);
        }
    }

    /**
     * @param  array<string, mixed>  $paneRule
     */
    private function resolveOrCreateSplitPane(
        Split $split,
        string $side,
        array $paneRule,
        ?string $callingContext,
    ): Container {
        $defaultName = ($split->getName() ?? ('split_'.$split->getId())).'_'.$side;
        $paneName = isset($paneRule['name']) && is_string($paneRule['name']) && trim($paneRule['name']) !== ''
            ? trim($paneRule['name'])
            : $defaultName;

        $existing = $split->findByName($paneName);
        if ($existing instanceof Container) {
            return $existing;
        }

        $isNestedSplit = isset($paneRule['component'])
            && is_string($paneRule['component'])
            && strtolower(trim($paneRule['component'])) === 'split';

        $paneElement = $isNestedSplit
            ? new Split($paneName, $callingContext)
            : new Container($paneName, $callingContext);

        $paneElement->plain()->width(Size::pct(100))->height(Size::pct(100));
        $split->add($paneElement);

        return $paneElement;
    }
}
