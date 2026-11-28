<?php

declare(strict_types=1);

namespace Idei\Usim\Support\LayoutLoader;

use Idei\Usim\Components\Container;

/**
 * Coordinates declarative layout loading and recursive application across specialized appliers.
 */
final class LayoutOrchestrator implements ContainerLayoutApplicator
{
    public function __construct(
        public readonly LayoutFileLoader $fileLoader,
        public readonly LayoutDefinitionParser $definitionParser,
        public readonly ComponentSlotResolver $componentResolver,
        private readonly ElementPropertyApplier $propertyApplier,
        private readonly NestedLayoutApplier $nestedLayoutApplier,
        private readonly GridChildrenConfigApplier $gridChildrenApplier,
        private readonly FlexChildrenConfigApplier $flexChildrenApplier,
        private readonly SlotsConfigApplier $slotsApplier,
        private readonly TimersConfigApplier $timersApplier,
        private readonly TranslationAutoHydrator $translationHydrator,
    ) {}

    public static function createDefault(): self
    {
        $fileLoader = new LayoutFileLoader;
        $definitionParser = new LayoutDefinitionParser;
        $componentResolver = new ComponentSlotResolver;
        $trackNormalizer = new GridTrackNormalizer;
        $translationHydrator = new TranslationAutoHydrator;
        $propertyApplier = new ElementPropertyApplier($definitionParser, $trackNormalizer);
        $ruleMatcher = new ChildRuleMatcher($definitionParser);
        $nestedLayoutApplier = new NestedLayoutApplier($fileLoader, $definitionParser, $propertyApplier);

        return new self(
            fileLoader: $fileLoader,
            definitionParser: $definitionParser,
            componentResolver: $componentResolver,
            propertyApplier: $propertyApplier,
            nestedLayoutApplier: $nestedLayoutApplier,
            gridChildrenApplier: new GridChildrenConfigApplier(
                $ruleMatcher,
                $trackNormalizer,
                $nestedLayoutApplier,
                $propertyApplier,
                $translationHydrator,
            ),
            flexChildrenApplier: new FlexChildrenConfigApplier(
                $ruleMatcher,
                $nestedLayoutApplier,
                $propertyApplier,
                $translationHydrator,
            ),
            slotsApplier: new SlotsConfigApplier($definitionParser, $nestedLayoutApplier),
            timersApplier: new TimersConfigApplier($definitionParser, $propertyApplier),
            translationHydrator: $translationHydrator,
        );
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    public function applyContainerConfig(Container $container, array $definition): void
    {
        $this->propertyApplier->applyContainerConfig($container, $definition);
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    public function applyToContainer(
        Container $container,
        array $definition,
        LayoutApplicationContext $context,
    ): void {
        $mainDef = $this->definitionParser->resolveMainDefinition($definition);
        $this->propertyApplier->applyContainerConfig($container, $mainDef);

        $effectiveContext = $this->buildEffectiveContext($container, $definition, $mainDef, $context);
        $this->applyMainSection($container, $mainDef, $effectiveContext);
        $this->applyRootAuxiliarySections($container, $definition, $effectiveContext);
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<string, mixed>  $mainDef
     */
    private function buildEffectiveContext(
        Container $container,
        array $definition,
        array $mainDef,
        LayoutApplicationContext $context,
    ): LayoutApplicationContext {
        $effectiveAutoTranslate = $context->autoTranslate
            || $this->definitionParser->isAutoTranslateDefinition($definition)
            || $this->definitionParser->isAutoTranslateDefinition($mainDef);

        $visitedPaths = $context->visitedPaths;
        if ($visitedPaths === [] && $container->getLayoutSourcePath() !== null) {
            $visitedPaths = [$container->getLayoutSourcePath()];
        }

        return new LayoutApplicationContext(
            callingContext: $context->callingContext,
            autoTranslate: $effectiveAutoTranslate,
            baseDir: $context->baseDir ?? $container->getLayoutBaseDir(),
            visitedPaths: $visitedPaths,
        );
    }

    /**
     * @param  array<string, mixed>  $mainDef
     */
    private function applyMainSection(
        Container $container,
        array $mainDef,
        LayoutApplicationContext $context,
    ): void {
        if ($this->definitionParser->extractExternalLayoutReference($mainDef) !== null && ! isset($mainDef['grid'])) {
            $this->nestedLayoutApplier->applyNestedContainerLayout($container, $mainDef, $context, $this);

            return;
        }

        if (isset($mainDef['grid']) && is_array($mainDef['grid'])) {
            /** @var array<string, mixed> $grid */
            $grid = $mainDef['grid'];
            $this->gridChildrenApplier->apply($container, $grid, $context, $this);

            return;
        }

        if (isset($mainDef['children']) && is_array($mainDef['children'])) {
            $this->flexChildrenApplier->apply($container, $mainDef, $context, $this);

            return;
        }

        if ($context->autoTranslate && $context->callingContext !== null) {
            $this->translationHydrator->autoHydrateContainerChildren($container, $context->callingContext);
        }
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function applyRootAuxiliarySections(
        Container $container,
        array $definition,
        LayoutApplicationContext $context,
    ): void {
        if (isset($definition['slots']) && is_array($definition['slots'])) {
            $this->slotsApplier->apply($container, $definition, $context, $this);
        }

        if (isset($definition['timers']) && is_array($definition['timers'])) {
            $this->timersApplier->apply($container, $definition, $context->callingContext);
        }
    }
}
