<?php

namespace Idei\Usim\Layout;

use Closure;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\Split;
use Idei\Usim\Contracts\ComponentIdGeneratorInterface;
use Idei\Usim\Screen;
use Idei\Usim\Support\ContainerLayoutLoader;
use Idei\Usim\Support\UIStateManager;
use Idei\Usim\UIChangesCollector;
use Illuminate\Support\Facades\Log;
use RuntimeException;

abstract class AbstractLayout extends Screen implements LayoutInterface
{
    public static ?string $layout = null;

    protected static ?AbstractLayout $currentLayout = null;

    /** @var class-string<Screen>|null */
    protected static ?string $activeHostScreen = null;

    protected ?Container $mainMenuContainer = null;

    protected ?Container $contentContainer = null;

    /** @var array<string, Container> */
    protected array $slots = [];

    /** @var array<string, class-string<Screen>|string> */
    protected array $activeScreens = [];

    /** @var array<string, class-string<Screen>|string> */
    protected array $state_active_screens = [];

    /**
     * Persisted mapping of slot name (and aliases) -> container name so cached snapshots can restore any slot.
     *
     * @var array<string, string>
     */
    protected array $state_slot_map = [];

    /**
     * Default Screen classes/slugs declared for slots (e.g. from `.layout.yaml` `screen:` key).
     *
     * @var array<string, string>
     */
    protected array $state_slot_defaults = [];

    /**
     * Whether each slot persists when navigating the main content slot.
     *
     * @var array<string, bool>
     */
    protected array $state_slot_persistence = [];

    /**
     * Canonical primary slot names (excludes aliases) in declaration order.
     *
     * @var list<string>
     */
    protected array $state_primary_slots = [];

    /**
     * Get the currently active layout instance in the request/session context.
     */
    public static function current(): ?self
    {
        if (self::$currentLayout !== null) {
            return self::$currentLayout;
        }

        if (app()->bound(self::class)) {
            /** @var self $layout */
            $layout = app(self::class);

            return $layout;
        }

        return null;
    }

    /**
     * Set the currently active layout instance.
     */
    public static function setCurrent(?AbstractLayout $layout): void
    {
        self::$currentLayout = $layout;
        if ($layout !== null) {
            app()->instance(self::class, $layout);
            app()->instance($layout::class, $layout);
        } else {
            app()->forgetInstance(self::class);
        }
    }

    /**
     * @return class-string<Screen>|null
     */
    public static function getActiveHostScreen(): ?string
    {
        return self::$activeHostScreen;
    }

    /**
     * @param  class-string<Screen>|null  $screenClass
     */
    public static function setActiveHostScreen(?string $screenClass): void
    {
        self::$activeHostScreen = $screenClass;
    }

    /**
     * Register a container as a named slot.
     */
    public function registerSlot(string $name, Container $container): Container
    {
        $this->slots[$name] = $container;
        $containerName = $container->getName();
        if ($containerName !== null && $containerName !== '') {
            $this->state_slot_map[$name] = $containerName;
        }

        if (in_array($name, ['main', 'center', 'content'], true) && $this->contentContainer === null) {
            $this->contentContainer = $container;
        } elseif (in_array($name, ['menu', 'main_menu', 'top_menu', 'sidebar'], true) && $this->mainMenuContainer === null) {
            $this->mainMenuContainer = $container;
        }

        return $container;
    }

    /**
     * Get a registered slot container by name.
     */
    public function getSlot(string $name = 'content'): ?Container
    {
        if (isset($this->slots[$name])) {
            return $this->slots[$name];
        }

        // Resolve via persisted slot map first (supports arbitrary custom slots like sidebar, right_panel, status_bar)
        if (isset($this->container, $this->state_slot_map[$name])) {
            $found = $this->container->findByName($this->state_slot_map[$name]);
            if ($found instanceof Container) {
                $this->slots[$name] = $found;

                return $found;
            }
        }

        $fallback = match ($name) {
            'menu', 'main_menu', 'top_menu', 'sidebar' => $this->mainMenuContainer,
            'main', 'center', 'content' => $this->contentContainer,
            default => null,
        };

        if ($fallback instanceof Container) {
            return $fallback;
        }

        // When the layout was reconstructed from snapshot cache, resolve by container name
        if (isset($this->container)) {
            $candidateNames = match ($name) {
                'menu', 'main_menu', 'top_menu' => ['main_menu_container', 'menu_container', 'top_menu', 'sidebar_container', 'sidebar'],
                'sidebar' => ['sidebar_container', 'sidebar', 'main_menu_container'],
                'main', 'center', 'content' => ['content_container', 'main_container', 'main', 'content'],
                'right_panel', 'right', 'inspector' => ['right_panel_container', 'right_panel', 'inspector_container', 'right'],
                'status_bar', 'statusbar' => ['status_bar_container', 'status_bar', 'statusbar'],
                default => [$name, "{$name}_container", "{$name}_slot"],
            };

            foreach ($candidateNames as $candidate) {
                $found = $this->container->findByName($candidate);
                if ($found instanceof Container) {
                    $this->slots[$name] = $found;
                    if (in_array($name, ['main', 'center', 'content'], true)) {
                        $this->contentContainer = $found;
                    } elseif (in_array($name, ['menu', 'main_menu', 'top_menu', 'sidebar'], true)) {
                        $this->mainMenuContainer = $found;
                    }

                    return $found;
                }
            }
        }

        return null;
    }

    /**
     * Get all registered slots.
     *
     * @return array<string, Container>
     */
    public function getSlots(): array
    {
        return $this->slots;
    }

    /**
     * Get canonical primary slot names registered on this layout.
     *
     * @return list<string>
     */
    public function getPrimarySlots(): array
    {
        if (! empty($this->state_primary_slots)) {
            return $this->state_primary_slots;
        }

        return array_keys($this->slots);
    }

    public function getSlotDefaultScreen(string $slot): ?string
    {
        return $this->state_slot_defaults[$slot] ?? null;
    }

    protected function postLoadUI(): void
    {
        if (! empty($this->state_active_screens)) {
            $this->activeScreens = array_merge($this->activeScreens, $this->state_active_screens);
        }

        // Restore all slot references when loaded from cached snapshot
        if (isset($this->container)) {
            foreach ($this->state_slot_map as $slotName => $containerName) {
                if (isset($this->slots[$slotName])) {
                    continue;
                }
                $found = $this->container->findByName($containerName);
                if ($found instanceof Container) {
                    $this->slots[$slotName] = $found;
                    if (in_array($slotName, ['main', 'center', 'content'], true) && $this->contentContainer === null) {
                        $this->contentContainer = $found;
                    } elseif (in_array($slotName, ['menu', 'main_menu', 'top_menu', 'sidebar'], true) && $this->mainMenuContainer === null) {
                        $this->mainMenuContainer = $found;
                    }
                }
            }

            if ($this->contentContainer === null) {
                $content = $this->container->findByName('content_container');
                if ($content instanceof Container) {
                    $this->contentContainer = $content;
                    $this->registerSlot('main', $content);
                    $this->registerSlot('content', $content);
                }
            }
            if ($this->mainMenuContainer === null) {
                $menu = $this->container->findByName('main_menu_container')
                    ?? $this->container->findByName('sidebar_container');
                if ($menu instanceof Container) {
                    $this->mainMenuContainer = $menu;
                    $this->registerSlot($menu->getName() === 'sidebar_container' ? 'sidebar' : 'top_menu', $menu);
                    $this->registerSlot('menu', $menu);
                }
            }
        }
    }

    public function callBuildBaseUI(Container $container, mixed ...$params): void
    {
        $idGenerator = app(ComponentIdGeneratorInterface::class);
        $idGenerator->pushCurrentContext(static::class);
        try {
            $this->autoWireComponentProperties();

            if (method_exists($this, 'buildBaseUI')) {
                parent::callBuildBaseUI($container, ...$params);
            } elseif (method_exists($this, 'startingUI')) {
                parent::callBuildBaseUI($container, ...$params);
            } elseif (static::$layoutFile !== null || ContainerLayoutLoader::hasDefaultLayout(static::class)) {
                $container->layoutFile(static::$layoutFile, static::class);
                $this->reconcileComponentProperties($container);
            }

            $this->registerSlotsFromTree($container);
        } finally {
            $idGenerator->popCurrentContext();
        }
    }

    /**
     * Recursively discover containers marked with `layoutSlot()` (from `.layout.yaml` or PHP)
     * and register their primary name, aliases, default screens, and navigation persistence rules.
     */
    protected function registerSlotsFromTree(Container $node): void
    {
        $meta = $node->getLayoutSlotMeta();
        if ($meta !== null) {
            $primaryName = $meta['name'];
            $this->registerSlot($primaryName, $node);

            if (! in_array($primaryName, $this->state_primary_slots, true)) {
                $this->state_primary_slots[] = $primaryName;
            }

            $this->state_slot_persistence[$primaryName] = $meta['persist_on_navigate'];

            if ($meta['screen'] !== null) {
                $this->state_slot_defaults[$primaryName] = $meta['screen'];
                if (! isset($this->activeScreens[$primaryName])) {
                    $this->setActiveScreen($primaryName, $meta['screen']);
                }
            }

            foreach ($meta['aliases'] as $alias) {
                $this->registerSlot($alias, $node);
                $this->state_slot_persistence[$alias] = $meta['persist_on_navigate'];
                if ($meta['screen'] !== null && ! isset($this->state_slot_defaults[$alias])) {
                    $this->state_slot_defaults[$alias] = $meta['screen'];
                }
            }
        }

        foreach ($node->getChildren() as $child) {
            if ($child instanceof Container) {
                $this->registerSlotsFromTree($child);
            }
        }
    }

    /**
     * Backward-compatible bridge to build layout structure with a content builder callback.
     *
     * @param  Closure(Container): void  $contentBuilder
     * @param  class-string<Screen>|null  $menuScreen
     */
    public function build(Container $root, Closure $contentBuilder, ?string $menuScreen = null): void
    {
        $this->callBuildBaseUI($root);
        $slot = $this->getSlot('main') ?? $this->getSlot('content') ?? $root;
        $contentBuilder($slot);
    }

    /**
     * Get the active screen class or slug currently occupying a slot.
     *
     * @return class-string<Screen>|string|null
     */
    public function getActiveScreen(string $slot = 'main'): ?string
    {
        if (isset($this->activeScreens[$slot])) {
            return $this->activeScreens[$slot];
        }

        if (isset($this->state_active_screens[$slot])) {
            return $this->state_active_screens[$slot];
        }

        if (in_array($slot, ['main', 'content', 'center'], true)) {
            return UIStateManager::getClientCurrentScreenClass();
        }

        return null;
    }

    /**
     * Get all active screens mapped to slots.
     *
     * @return array<string, class-string<Screen>|string>
     */
    public function getActiveScreens(): array
    {
        return array_merge($this->state_active_screens, $this->activeScreens);
    }

    /**
     * Set the active screen class or slug occupying a slot.
     *
     * @param  class-string<Screen>|string  $screenClass
     */
    public function setActiveScreen(string $slot, string $screenClass): self
    {
        $this->activeScreens[$slot] = $screenClass;
        $this->state_active_screens[$slot] = $screenClass;

        return $this;
    }

    /**
     * Get the main menu container / slot.
     */
    public function getMainMenuContainer(): ?Container
    {
        return $this->mainMenuContainer;
    }

    /**
     * Get the content container / slot.
     */
    public function getContentContainer(): ?Container
    {
        return $this->contentContainer;
    }

    /**
     * Expand the Split pane hosting the given slot, if applicable.
     */
    public function expandSlot(string $slot): bool
    {
        $splitInfo = $this->findOwningSplitForSlot($slot);
        if ($splitInfo === null) {
            return false;
        }

        $split = $splitInfo['split'];
        $side = $splitInfo['side'];

        if ($split->getCollapsedPanel() === $side) {
            $split->expand();
            $this->emitSplitCollapseDiff($split);
        }

        return true;
    }

    /**
     * Collapse the Split pane hosting the given slot, if applicable.
     */
    public function collapseSlot(string $slot): bool
    {
        $splitInfo = $this->findOwningSplitForSlot($slot);
        if ($splitInfo === null) {
            return false;
        }

        $split = $splitInfo['split'];
        $side = $splitInfo['side'];

        if ($split->getCollapsedPanel() !== $side) {
            if ($side === 'second') {
                $split->collapseSecond();
            } else {
                $split->collapseFirst();
            }
            $this->emitSplitCollapseDiff($split);
        }

        return true;
    }

    /**
     * Clear the contents of a slot and optionally collapse its parent Split pane.
     */
    public function clearSlot(string $slot, bool $collapse = true): bool
    {
        $targetContainer = $this->getSlot($slot);
        if (! $targetContainer instanceof Container) {
            return false;
        }

        $targetContainer->clear();
        unset($this->activeScreens[$slot], $this->state_active_screens[$slot]);

        app(UIChangesCollector::class)->add([
            'clear_container' => $targetContainer->getId(),
        ]);

        if ($collapse) {
            $this->collapseSlot($slot);
        }

        $this->persistLayoutState();

        return true;
    }

    /**
     * Mount and display a target screen inside a layout slot.
     *
     * @param  class-string<Screen>|string  $screenClass  Target screen class or slug
     * @param  string|null  $slot  Target slot name (e.g. 'main', 'top_menu', 'sidebar', 'right_panel')
     * @param  array<int|string, mixed>  $params  Parameters passed to the screen
     * @param  bool  $updateBrowserUrl  Whether to push HTML5 browser history state
     * @param  bool  $force  Whether to force reload the slot and clear cached screen snapshot even if target matches current
     */
    public function showInto(string $screenClass, ?string $slot = null, array $params = [], bool $updateBrowserUrl = true, bool $force = false): bool
    {
        // Support polymorphic argument order: showInto($slot, $screenClass) or showInto($screenClass, $slot)
        if ($slot !== null && (class_exists($slot) || Screen::resolveScreenClassFromSlug($slot) !== null)) {
            $temp = $screenClass;
            $screenClass = $slot;
            $slot = $temp;
        }

        $effectiveSlot = $slot ?? 'main';
        $targetClass = class_exists($screenClass) ? $screenClass : Screen::resolveScreenClassFromSlug($screenClass);
        if ($targetClass === null || ! class_exists($targetClass) || ! is_subclass_of($targetClass, Screen::class)) {
            throw new RuntimeException("Target screen [{$screenClass}] is not a valid Screen instance.");
        }

        $access = $targetClass::checkAccess();
        if (! $access['allowed']) {
            $redirectUrl = $access['params']['url'] ?? null;
            if (($access['action'] ?? null) === 'redirect' && is_string($redirectUrl) && $redirectUrl !== '') {
                app(UIChangesCollector::class)->add(['redirect' => $redirectUrl]);
            } else {
                $code = $access['params']['code'] ?? 403;
                $message = $access['params']['message'] ?? 'Unauthorized';
                app(UIChangesCollector::class)->add([
                    'abort' => [
                        'status_code' => is_int($code) ? $code : 403,
                        'message' => is_string($message) ? $message : 'Unauthorized',
                    ],
                ]);
            }

            return false;
        }

        $targetContainer = $this->getSlot($effectiveSlot);
        if (! $targetContainer instanceof Container) {
            Log::warning("Slot [{$effectiveSlot}] not found on layout [".static::class.'].');

            return false;
        }

        $isMainNavigation = in_array($effectiveSlot, ['main', 'content', 'center'], true);

        // When navigating main content, clear and collapse non-persistent auxiliary slots (e.g. right_panel)
        if ($isMainNavigation) {
            foreach ($this->state_slot_persistence as $slotName => $persist) {
                if ($persist === false && isset($this->state_active_screens[$slotName])) {
                    $this->clearSlot($slotName, collapse: true);
                }
            }
        }

        // If target slot is currently collapsed in a Split (e.g. right_panel at 0px), expand it automatically
        $this->expandSlot($effectiveSlot);

        if ($force || ! empty($params)) {
            Screen::make($targetClass)->clearCachedScreenSnapshot();
        }

        // Clear existing content in the slot container
        $targetContainer->clear();
        Screen::embedInto($targetClass, $targetContainer, $params);
        $this->setActiveScreen($effectiveSlot, $targetClass);

        $routeSlug = Screen::resolveScreenSlug($targetClass);
        if ($updateBrowserUrl && $isMainNavigation) {
            $routePath = $targetClass::getRoutePath();
            $queryParams = Screen::normalizeParamsForUrl($params);
            $navigateUrl = ! empty($queryParams)
                ? $routePath.'?'.http_build_query($queryParams)
                : $routePath;

            UIStateManager::setClientCurrentScreen($routePath, $targetClass);
            app(UIChangesCollector::class)->add([
                'navigate' => [
                    'url' => $navigateUrl,
                    'route' => $routeSlug,
                    'title' => $targetClass::getMenuLabel(),
                    'slot' => $targetContainer->getName() ?? $effectiveSlot,
                    'slot_container_id' => $targetContainer->getId(),
                ],
                'clear_container' => $targetContainer->getId(),
            ]);
        } else {
            app(UIChangesCollector::class)->add([
                'clear_container' => $targetContainer->getId(),
            ]);
        }

        $this->persistLayoutState();

        return true;
    }

    /**
     * Show a target screen inside its default slot (or specified slot).
     *
     * @param  class-string<Screen>|string  $screenClass
     * @param  array<int|string, mixed>  $params
     */
    public function show(string $screenClass, ?string $slot = null, array $params = []): bool
    {
        $targetClass = class_exists($screenClass) ? $screenClass : Screen::resolveScreenClassFromSlug($screenClass);
        if ($targetClass !== null && class_exists($targetClass) && is_subclass_of($targetClass, Screen::class)) {
            $effectiveSlot = $slot ?? $targetClass::getDefaultSlot();
        } else {
            $effectiveSlot = $slot ?? 'main';
        }

        return $this->showInto($screenClass, $effectiveSlot, $params);
    }

    /**
     * Find the Split container that directly hosts a slot container and which pane ('first' or 'second') it occupies.
     *
     * @return array{split: Split, side: 'first'|'second'}|null
     */
    protected function findOwningSplitForSlot(string $slot): ?array
    {
        if (! isset($this->container)) {
            return null;
        }

        $slotContainer = $this->getSlot($slot);
        if (! $slotContainer instanceof Container) {
            return null;
        }

        return $this->searchSplitOwningContainer($this->container, $slotContainer->getId());
    }

    /**
     * @return array{split: Split, side: 'first'|'second'}|null
     */
    protected function searchSplitOwningContainer(Container $node, int $targetId): ?array
    {
        if ($node instanceof Split) {
            $paneContainers = array_values(array_filter(
                $node->getChildren(),
                static fn ($child): bool => $child instanceof Container
            ));

            foreach ($paneContainers as $index => $pane) {
                $side = $index === 1 ? 'second' : 'first';
                if ($pane->getId() === $targetId) {
                    return ['split' => $node, 'side' => $side];
                }
            }
        }

        foreach ($node->getChildren() as $child) {
            if ($child instanceof Container) {
                $found = $this->searchSplitOwningContainer($child, $targetId);
                if ($found !== null) {
                    return $found;
                }
            }
        }

        return null;
    }

    /**
     * Emit incremental Split collapse state change to the frontend and persist snapshot.
     */
    protected function emitSplitCollapseDiff(Split $split): void
    {
        app(UIChangesCollector::class)->add([
            $split->getId() => [
                'collapsed_panel' => $split->getCollapsedPanel(),
            ],
        ]);
    }

    /**
     * Persist updated layout container tree and state_* variables to UIStateManager.
     */
    protected function persistLayoutState(): void
    {
        if (isset($this->container)) {
            UIStateManager::store(static::class, $this->container->toJson());
        }
        UIStateManager::storeScreenState(static::class, $this->getStateVariables());
    }
}
