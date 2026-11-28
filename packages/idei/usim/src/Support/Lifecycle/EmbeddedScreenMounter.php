<?php

declare(strict_types=1);

namespace Idei\Usim\Support\Lifecycle;

use Idei\Usim\Components\Container;
use Idei\Usim\Contracts\ComponentIdGeneratorInterface;
use Idei\Usim\Contracts\ScreenLifecycleOrchestratorInterface;
use Idei\Usim\Contracts\UIElement;
use Idei\Usim\Layout\AbstractLayout;
use Idei\Usim\Layout\LayoutInterface;
use Idei\Usim\Screen;
use Idei\Usim\UIChangesCollector;

/**
 * Mounts and embeds child/nested Screens into a parent Container slot,
 * handling context isolation, cache resets, layout portal projection,
 * snapshot persistence, and diff publication.
 */
final class EmbeddedScreenMounter
{
    public function __construct(
        private readonly ComponentIdGeneratorInterface $idGenerator,
        private readonly UIChangesCollector $changesCollector,
    ) {}

    /**
     * Build and attach a nested screen inside a parent container.
     *
     * @param  class-string<Screen>  $screenClass
     * @param  array<int|string, mixed>  $buildParams
     * @param  int|string|null  $slot  Optional target layout slot name or 1-based position
     */
    public function embedInto(
        string $screenClass,
        Container $parent,
        array $buildParams,
        int|string|null $slot,
        ScreenLifecycleOrchestratorInterface $orchestrator
    ): void {
        $parentId = $parent->getId();
        $removedElements = $slot !== null ? $parent->clearSlot($slot) : [];

        $instance = Screen::make($screenClass);
        $instance->setParent($parentId);

        $prevIncomingStorage = Screen::getCurrentIncomingStorage();
        $prevQueryParams = Screen::getCurrentQueryParams();

        $this->idGenerator->pushCurrentContext($instance->getContextIdentifier());
        try {
            $this->initializeEmbeddedInstance($instance, $parentId, $buildParams, $orchestrator);

            $container = $instance->getContainer();
            if ($container !== null) {
                $this->attachAndPublishContainer($instance, $container, $parent, $slot, $removedElements, $orchestrator);
            }

            $this->changesCollector->setStorage($instance->getStorageVariables());
        } finally {
            Screen::setCurrentIncomingStorage($prevIncomingStorage);
            Screen::setCurrentQueryParams($prevQueryParams);
            $this->idGenerator->popCurrentContext();
        }
    }

    /**
     * @param  array<int|string, mixed>  $buildParams
     */
    private function initializeEmbeddedInstance(
        Screen $instance,
        int $parentId,
        array $buildParams,
        ScreenLifecycleOrchestratorInterface $orchestrator
    ): void {
        $shouldReset = (bool) (Screen::getCurrentQueryParams()['reset'] ?? request()->query('reset', false));
        if ($shouldReset) {
            $instance->onResetScreen();
        }

        if (! empty($buildParams) && AbstractLayout::getActiveHostScreen() !== $instance::class) {
            $instance->clearCachedScreenSnapshot();
        }

        $normalizedBuildParams = Screen::normalizeParamsForUrl($buildParams);
        $effectiveQueryParams = array_merge(Screen::getCurrentQueryParams(), $normalizedBuildParams);

        $orchestrator->initializeEventContext(
            screen: $instance,
            incomingStorage: Screen::getCurrentIncomingStorage(),
            queryParams: $effectiveQueryParams,
            parent: $parentId,
            buildParams: $buildParams
        );
        $instance->callPostLoadUI($buildParams);
    }

    /**
     * @param  array<int, UIElement>  $removedElements
     */
    private function attachAndPublishContainer(
        Screen $instance,
        Container $container,
        Container $parent,
        int|string|null $slot,
        array $removedElements,
        ScreenLifecycleOrchestratorInterface $orchestrator
    ): void {
        if ($slot !== null) {
            $container->slot($slot);
        }

        // Attach to the host screen's container with root=false so it does not
        // collide with the host screen's root container.
        $container->root(false);
        $parent->add($container, slot: $slot);

        if ($parent->getLayoutDefinition() !== null) {
            $parent->applyLayoutDefinition();
        }

        ['layout' => $activeLayout, 'isMainLayoutHost' => $isMainLayoutHost, 'isInitialLayoutRender' => $isInitialLayoutRender]
            = $this->resolveMainLayoutHostContext($instance, $parent);

        if ($isMainLayoutHost && $activeLayout instanceof AbstractLayout) {
            $activeLayout->prepareScreenPortals($instance, $container, $isInitialLayoutRender);
        }

        $childOrder = $parent->getChildOrder($container->getId());
        $embeddedJson = $container->toJson($childOrder);

        // Store the child screen's own snapshot with root=true and parent=$parentId
        // so direct events on the child screen can reconstruct its tree (including its slot/grid config).
        $container->root(true);
        $orchestrator->cacheScreenSnapshot($instance, $container);
        $container->root(false);

        $this->recordRemovedElements($removedElements, $container->getId());
        $this->changesCollector->add($embeddedJson);

        if ($isMainLayoutHost && $activeLayout instanceof AbstractLayout) {
            $activeLayout->mountDeclaredSlotScreens($container, $isInitialLayoutRender);
        }
    }

    /**
     * @return array{layout: ?AbstractLayout, isMainLayoutHost: bool, isInitialLayoutRender: bool}
     */
    private function resolveMainLayoutHostContext(Screen $instance, Container $parent): array
    {
        $activeLayout = AbstractLayout::current();
        $isMainLayoutHost = $activeLayout instanceof AbstractLayout
            && ! ($instance instanceof LayoutInterface)
            && $activeLayout->isMainSlotContainer($parent);
        $isInitialLayoutRender = $isMainLayoutHost
            && AbstractLayout::getActiveHostScreen() === $instance::class;

        return [
            'layout' => $activeLayout,
            'isMainLayoutHost' => $isMainLayoutHost,
            'isInitialLayoutRender' => $isInitialLayoutRender,
        ];
    }

    /**
     * @param  array<int, UIElement>  $removedElements
     */
    private function recordRemovedElements(array $removedElements, int $attachedContainerId): void
    {
        foreach ($removedElements as $removed) {
            if ($removed->getId() !== $attachedContainerId) {
                $this->changesCollector->add([
                    $removed->getId() => [
                        'type' => $removed->getType(),
                        'parent' => null,
                    ],
                ]);
            }
        }
    }
}
