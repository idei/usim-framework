<?php

declare(strict_types=1);

namespace Idei\Usim\Support\LayoutLoader;

use Idei\Usim\Components\Container;

/**
 * Mounts and configures declarative slot projections declared under the root `slots:` section.
 */
final class SlotsConfigApplier
{
    public function __construct(
        private readonly LayoutDefinitionParser $definitionParser,
        private readonly NestedLayoutApplier $nestedLayoutApplier,
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
        $entries = $this->definitionParser->extractSlotDefinitions($definition);
        if ($entries === []) {
            return;
        }

        $declaredScreens = [];
        foreach ($entries as $entry) {
            if ($entry['screen'] !== null) {
                $declaredScreens[] = $this->buildDeclaredScreenEntry($entry);

                continue;
            }

            $this->mountPortalSlot($container, $entry, $context, $applicator);
        }

        $container->setDeclaredSlotScreens($declaredScreens);
    }

    /**
     * @param  array{
     *     slot: string,
     *     container_name: string,
     *     open: bool,
     *     size: ?string,
     *     fallback: string,
     *     persist_on_navigate: bool,
     *     screen: string,
     *     params: array<int|string, mixed>,
     *     rule: array<string, mixed>
     * }  $entry
     * @return array{
     *     slot: string,
     *     screen: string,
     *     params: array<int|string, mixed>,
     *     open: bool,
     *     size: ?string,
     *     fallback: string,
     *     persist_on_navigate: bool
     * }
     */
    private function buildDeclaredScreenEntry(array $entry): array
    {
        return [
            'slot' => $entry['slot'],
            'screen' => $entry['screen'],
            'params' => $entry['params'],
            'open' => $entry['open'],
            'size' => $entry['size'],
            'fallback' => $entry['fallback'],
            'persist_on_navigate' => $entry['persist_on_navigate'],
        ];
    }

    /**
     * @param  array{
     *     slot: string,
     *     container_name: string,
     *     open: bool,
     *     size: ?string,
     *     fallback: string,
     *     persist_on_navigate: bool,
     *     screen: ?string,
     *     params: array<int|string, mixed>,
     *     rule: array<string, mixed>
     * }  $entry
     */
    private function mountPortalSlot(
        Container $container,
        array $entry,
        LayoutApplicationContext $context,
        ContainerLayoutApplicator $applicator,
    ): void {
        $portal = $this->findOrCreatePortalContainer($container, $entry['container_name'], $context->callingContext);
        $portal->portalSlot(
            slot: $entry['slot'],
            open: $entry['open'],
            size: $entry['size'],
            fallback: $entry['fallback'],
            persistOnNavigate: $entry['persist_on_navigate'],
        );

        $rule = $entry['rule'];
        if ($context->autoTranslate && ! array_key_exists('i18n', $rule)) {
            $rule['i18n'] = 'auto';
        }

        if ($this->definitionParser->extractExternalLayoutReference($rule) !== null) {
            $this->nestedLayoutApplier->applyNestedContainerLayout($portal, $rule, $context, $applicator);

            return;
        }

        $portal->layoutBaseDir($context->baseDir);
        $portal->layoutDefinition($rule, $context->callingContext, $context->autoTranslate);
        $applicator->applyToContainer($portal, $rule, $context);
    }

    private function findOrCreatePortalContainer(
        Container $container,
        string $portalName,
        ?string $callingContext,
    ): Container {
        foreach ($container->getChildren() as $existingChild) {
            if ($existingChild instanceof Container && $existingChild->getName() === $portalName) {
                return $existingChild;
            }
        }

        $portal = new Container($portalName, $callingContext);
        $container->add($portal);

        return $portal;
    }
}
