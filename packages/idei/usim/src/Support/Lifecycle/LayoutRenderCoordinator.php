<?php

declare(strict_types=1);

namespace Idei\Usim\Support\Lifecycle;

use Idei\Usim\Components\Container;
use Idei\Usim\Contracts\ComponentIdGeneratorInterface;
use Idei\Usim\Contracts\ScreenLifecycleOrchestratorInterface;
use Idei\Usim\Layout\AbstractLayout;
use Idei\Usim\Layout\LayoutInterface;
use Idei\Usim\Screen;
use Idei\Usim\Support\UIStateManager;
use Idei\Usim\Support\UsimConfig;

/**
 * Coordinates page-level AbstractLayout wrapping during Screen::render():
 * - Pre-discovers external sub-slots declared by shell screens (e.g. StatusBar)
 * - Embeds main content, navigation menu, and auxiliary slots in canonical payload order
 * - Persists layout state and applies portal fallbacks for standalone screens
 */
final class LayoutRenderCoordinator
{
    /**
     * @var list<string>
     */
    private const array MAIN_AND_MENU_SLOTS = ['main', 'content', 'center', 'top_menu', 'menu', 'sidebar'];

    public function __construct(
        private readonly ComponentIdGeneratorInterface $idGenerator,
        private readonly UsimConfig $usimConfig,
    ) {}

    /**
     * Wrap and render the given screen inside its resolved AbstractLayout if applicable.
     *
     * @param  array<string, mixed>  $incomingStorage
     * @param  array<string, mixed>  $queryParams
     * @param  array<int|string, mixed>  $buildParams
     * @return bool True when the screen was rendered through a layout wrapper
     */
    public function renderWithLayoutIfApplicable(
        Screen $screen,
        array $incomingStorage,
        array $queryParams,
        int|string|null $parent,
        bool $shouldReset,
        array $buildParams,
        ScreenLifecycleOrchestratorInterface $orchestrator
    ): bool {
        $layoutClass = $this->resolveApplicableLayoutClass($screen, $parent);
        if ($layoutClass === null) {
            return false;
        }

        $this->renderWrappedLayout(
            screen: $screen,
            layoutClass: $layoutClass,
            incomingStorage: $incomingStorage,
            queryParams: $queryParams,
            shouldReset: $shouldReset,
            buildParams: $buildParams,
            orchestrator: $orchestrator,
        );

        return true;
    }

    /**
     * Apply portal fallbacks when a standalone screen renders directly without an AbstractLayout.
     */
    public function applyStandalonePortalFallbacksIfApplicable(Screen $screen, int|string|null $parent): void
    {
        if ($parent !== 'main' || $screen instanceof LayoutInterface) {
            return;
        }

        $standaloneContainer = $screen->getContainer();
        if ($standaloneContainer instanceof Container) {
            $this->applyStandalonePortalFallbacks($standaloneContainer);
        }
    }

    /**
     * Resolve portal fallbacks for standalone screens rendered without an AbstractLayout.
     */
    public function applyStandalonePortalFallbacks(Container $screenRoot): void
    {
        foreach ($screenRoot->collectPortalContainers() as $portal) {
            $meta = $portal->getPortalMeta();
            if ($meta === null) {
                continue;
            }

            if ($meta['fallback'] === 'ignore') {
                $portal->portalUnmounted(true)->portalTargetId(null);

                continue;
            }

            $portal->clearPortalTarget();
            if ($meta['fallback'] === 'inline_top') {
                $this->movePortalToContainerStart($screenRoot, $portal);
            }
        }
    }

    /**
     * @return class-string<AbstractLayout>|null
     */
    private function resolveApplicableLayoutClass(Screen $screen, int|string|null $parent): ?string
    {
        if ($parent !== 'main' || $screen->modalLayerIndex !== 0 || $screen instanceof LayoutInterface) {
            return null;
        }

        $layoutClass = $screen::getLayoutClass();

        return ($layoutClass !== null && class_exists($layoutClass)) ? $layoutClass : null;
    }

    /**
     * @param  class-string<AbstractLayout>  $layoutClass
     * @param  array<string, mixed>  $incomingStorage
     * @param  array<string, mixed>  $queryParams
     * @param  array<int|string, mixed>  $buildParams
     */
    private function renderWrappedLayout(
        Screen $screen,
        string $layoutClass,
        array $incomingStorage,
        array $queryParams,
        bool $shouldReset,
        array $buildParams,
        ScreenLifecycleOrchestratorInterface $orchestrator
    ): void {
        AbstractLayout::setActiveHostScreen($screen::class);
        try {
            /** @var AbstractLayout $layout */
            $layout = Screen::make($layoutClass);
            AbstractLayout::setCurrent($layout);
            $screen->setLayoutInstance($layout);
            $layout->setActiveScreen('main', $screen::class);

            $layout->render(
                incomingStorage: $incomingStorage,
                queryParams: $queryParams,
                parent: 'main',
                shouldReset: $shouldReset
            );
            $layout->setActiveScreen('main', $screen::class);

            // 0. Pre-discover external sub-slots declared by shell screens (e.g. StatusBar)
            $this->preDiscoverShellExternalSlots($layout, $orchestrator);

            // 1. Re-embed main content slot (triggers Screen portal projections & declared slot screens)
            $mainSlot = $this->embedMainSlot($layout, $screen, $buildParams, $orchestrator);

            // 2. Re-embed navigation menu slot (supports top_menu, sidebar, or menu)
            $menuSlot = $this->embedMenuSlot($layout, $screen, $orchestrator);

            // 3. Re-embed any other registered slot with an active or default screen (e.g. status_bar, right_panel)
            $embeddedContainerIds = $this->collectContainerIds($mainSlot, $menuSlot);
            $embeddedContainerIds = $this->embedPrimaryAuxiliarySlots($layout, $embeddedContainerIds, $orchestrator);
            $this->embedRemainingActiveSlots($layout, $embeddedContainerIds, $orchestrator);

            $layout->persistLayoutState();
            UIStateManager::setClientActiveLayout($layoutClass);
        } finally {
            AbstractLayout::setActiveHostScreen(null);
        }
    }

    private function preDiscoverShellExternalSlots(
        AbstractLayout $layout,
        ScreenLifecycleOrchestratorInterface $orchestrator
    ): void {
        foreach ($layout->getPrimarySlots() as $slotName) {
            if ($this->isMainOrMenuSlot($slotName)) {
                continue;
            }

            $targetClass = $this->resolvePrimarySlotScreenClass($layout, $slotName);
            if ($targetClass !== null) {
                $this->discoverExternalSlotsForShell($layout, $targetClass, $orchestrator);
            }
        }
    }

    /**
     * @param  class-string<Screen>  $targetClass
     */
    private function discoverExternalSlotsForShell(
        AbstractLayout $layout,
        string $targetClass,
        ScreenLifecycleOrchestratorInterface $orchestrator
    ): void {
        $shellScreen = Screen::make($targetClass);
        $this->idGenerator->pushCurrentContext($shellScreen->getContextIdentifier());
        try {
            $shellTree = $orchestrator->reconstructScreenTree($shellScreen);
            $layout->registerExternalSlotsFromTree($shellTree, $targetClass);
        } finally {
            $this->idGenerator->popCurrentContext();
        }
    }

    /**
     * @param  array<int|string, mixed>  $buildParams
     */
    private function embedMainSlot(
        AbstractLayout $layout,
        Screen $screen,
        array $buildParams,
        ScreenLifecycleOrchestratorInterface $orchestrator
    ): ?Container {
        $slot = $layout->getSlot('content') ?? $layout->getSlot('main');
        if ($slot instanceof Container) {
            $slot->clear();
            $orchestrator->embedInto($screen::class, $slot, $buildParams);
        }

        return $slot;
    }

    private function embedMenuSlot(
        AbstractLayout $layout,
        Screen $screen,
        ScreenLifecycleOrchestratorInterface $orchestrator
    ): ?Container {
        $sidebarSlot = $layout->getSlot('sidebar');
        $menuSlot = $sidebarSlot ?? $layout->getSlot('top_menu') ?? $layout->getSlot('menu');
        $isSidebarSlot = $sidebarSlot !== null;

        $menuClass = $this->resolveMenuScreenClass($layout, $screen, $isSidebarSlot);
        if ($menuSlot instanceof Container && $menuClass !== null) {
            $menuSlot->clear();
            $orchestrator->embedInto($menuClass, $menuSlot);
            $layout->setActiveScreen($isSidebarSlot ? 'sidebar' : 'top_menu', $menuClass);
            $layout->registerExternalSlotsFromTree($menuSlot, $menuClass);
        }

        return $menuSlot;
    }

    /**
     * @return class-string<Screen>|null
     */
    private function resolveMenuScreenClass(AbstractLayout $layout, Screen $screen, bool $isSidebarSlot): ?string
    {
        $explicitHostMenu = $screen::$menuScreen !== null ? $screen::getMenuScreen() : null;
        $explicitLayoutMenu = $layout::$menuScreen !== null ? $layout::getMenuScreen() : null;

        $candidate = $explicitHostMenu
            ?? $explicitLayoutMenu
            ?? $layout->getActiveScreen('sidebar')
            ?? $layout->getActiveScreen('top_menu')
            ?? $layout->getActiveScreen('menu')
            ?? $this->resolveConfiguredMenuDefault($layout, $isSidebarSlot);

        return $this->resolveScreenClass($candidate);
    }

    private function resolveConfiguredMenuDefault(AbstractLayout $layout, bool $isSidebarSlot): string
    {
        if ($isSidebarSlot) {
            return $layout->getSlotDefaultScreen('sidebar')
                ?? Screen::resolveShellScreenClass('sidebar')
                ?? $this->usimConfig->defaultMenuScreen;
        }

        return $layout->getSlotDefaultScreen('top_menu')
            ?? $layout->getSlotDefaultScreen('menu')
            ?? $this->usimConfig->defaultMenuScreen;
    }

    /**
     * @param  array<int, bool>  $embeddedContainerIds
     * @return array<int, bool>
     */
    private function embedPrimaryAuxiliarySlots(
        AbstractLayout $layout,
        array $embeddedContainerIds,
        ScreenLifecycleOrchestratorInterface $orchestrator
    ): array {
        foreach ($layout->getPrimarySlots() as $slotName) {
            if ($this->isMainOrMenuSlot($slotName)) {
                continue;
            }

            $otherSlot = $this->resolveUnembeddedSlotContainer($layout, $slotName, $embeddedContainerIds);
            $targetClass = $otherSlot !== null ? $this->resolvePrimarySlotScreenClass($layout, $slotName) : null;
            if ($otherSlot === null || $targetClass === null) {
                continue;
            }

            $this->embedSlotScreen($layout, $slotName, $otherSlot, $targetClass, $orchestrator, updateActive: true);
            $embeddedContainerIds[$otherSlot->getId()] = true;

            // Re-apply status bar message hiding if main Screen projected into status_bar_center
            if ($slotName === 'status_bar' && $layout->getSlotOwner('status_bar_center') !== null) {
                $layout->setStatusBarMessageVisible(false);
            }
        }

        return $embeddedContainerIds;
    }

    /**
     * @param  array<int, bool>  $embeddedContainerIds
     */
    private function embedRemainingActiveSlots(
        AbstractLayout $layout,
        array $embeddedContainerIds,
        ScreenLifecycleOrchestratorInterface $orchestrator
    ): void {
        foreach ($layout->getActiveScreens() as $slotName => $activeClass) {
            if ($this->isMainOrMenuSlot($slotName)) {
                continue;
            }

            $otherSlot = $this->resolveUnembeddedSlotContainer($layout, $slotName, $embeddedContainerIds);
            $targetClass = $otherSlot !== null ? $this->resolveScreenClass($activeClass) : null;
            if ($otherSlot === null || $targetClass === null) {
                continue;
            }

            $this->embedSlotScreen($layout, $slotName, $otherSlot, $targetClass, $orchestrator, updateActive: false);
            $embeddedContainerIds[$otherSlot->getId()] = true;
        }
    }

    /**
     * @param  class-string<Screen>  $targetClass
     */
    private function embedSlotScreen(
        AbstractLayout $layout,
        string $slotName,
        Container $slotContainer,
        string $targetClass,
        ScreenLifecycleOrchestratorInterface $orchestrator,
        bool $updateActive
    ): void {
        $slotContainer->clear();
        $orchestrator->embedInto($targetClass, $slotContainer);

        if ($updateActive) {
            $layout->setActiveScreen($slotName, $targetClass);
        }

        $layout->registerExternalSlotsFromTree($slotContainer, $targetClass);
    }

    /**
     * @param  array<int, bool>  $embeddedContainerIds
     */
    private function resolveUnembeddedSlotContainer(
        AbstractLayout $layout,
        string $slotName,
        array $embeddedContainerIds
    ): ?Container {
        $slot = $layout->getSlot($slotName);
        if (! $slot instanceof Container || isset($embeddedContainerIds[$slot->getId()])) {
            return null;
        }

        return $slot;
    }

    /**
     * @return class-string<Screen>|null
     */
    private function resolvePrimarySlotScreenClass(AbstractLayout $layout, string $slotName): ?string
    {
        $activeClass = $layout->getActiveScreen($slotName)
            ?? $layout->getSlotDefaultScreen($slotName);

        if ($activeClass === null && $slotName === 'status_bar') {
            $activeClass = Screen::resolveShellScreenClass('status_bar');
        }

        return $this->resolveScreenClass($activeClass);
    }

    /**
     * @return class-string<Screen>|null
     */
    private function resolveScreenClass(mixed $candidate): ?string
    {
        if (! is_string($candidate) || $candidate === '') {
            return null;
        }

        $targetClass = is_subclass_of($candidate, Screen::class)
            ? $candidate
            : Screen::resolveScreenClassFromSlug($candidate);

        if ($targetClass !== null && class_exists($targetClass) && is_subclass_of($targetClass, Screen::class)) {
            return $targetClass;
        }

        return null;
    }

    private function isMainOrMenuSlot(string $slotName): bool
    {
        return in_array($slotName, self::MAIN_AND_MENU_SLOTS, true);
    }

    /**
     * @return array<int, bool>
     */
    private function collectContainerIds(?Container ...$containers): array
    {
        $ids = [];
        foreach ($containers as $container) {
            if ($container instanceof Container) {
                $ids[$container->getId()] = true;
            }
        }

        return $ids;
    }

    private function movePortalToContainerStart(Container $screenRoot, Container $portal): void
    {
        $parentId = $portal->getParent();
        $parentContainer = is_int($parentId) ? $screenRoot->findById($parentId) : null;

        if ($parentContainer instanceof Container) {
            $parentContainer->moveChildToStart($portal->getId());
        } else {
            $screenRoot->moveChildToStart($portal->getId());
        }
    }
}
