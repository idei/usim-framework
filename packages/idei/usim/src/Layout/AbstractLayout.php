<?php

namespace Idei\Usim\Layout;

use Closure;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\Split;
use Idei\Usim\Contracts\ComponentIdGeneratorInterface;
use Idei\Usim\Screen;
use Idei\Usim\Support\ContainerLayoutLoader;
use Idei\Usim\Support\UIIdGenerator;
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
     * External slots hosted inside embedded shell screens (e.g. StatusBar's status_bar_left/center/right).
     *
     * @var array<string, array{container_id: int, container_name: string, host_screen: string, persist_on_navigate: bool, canonical_name: string}>
     */
    protected array $state_external_slots = [];

    /**
     * Ownership metadata for auxiliary slots occupied by either a Screen portal or a sub-Screen.
     *
     * @var array<string, array{mode: string, owner: string, portal_container_id?: int, persist_on_navigate: bool}>
     */
    protected array $state_slot_owners = [];

    /**
     * Original Split sizes saved before a Screen applied a temporary `size:` override.
     * Keyed by Split component ID.
     *
     * @var array<int|string, array{split_size: string, split_size_target: string}>
     */
    protected array $state_split_default_sizes = [];

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
                'sidebar', 'activity_bar' => ['sidebar_container', 'activity_bar_container', 'sidebar', 'main_menu_container'],
                'left_panel', 'left', 'explorer' => ['left_panel_container', 'left_panel', 'explorer_container', 'left'],
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
            $this->activeScreens = array_merge($this->state_active_screens, $this->activeScreens);
            $this->state_active_screens = $this->activeScreens;
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
     * Recursively discover layout slots hosted inside an embedded shell screen (e.g. StatusBar's contextual sub-slots)
     * and persist their container IDs and host screen so future requests can target and clear them cleanly.
     *
     * @param  class-string<Screen>  $hostScreenClass
     */
    public function registerExternalSlotsFromTree(Container $node, string $hostScreenClass): void
    {
        $meta = $node->getLayoutSlotMeta();
        if ($meta !== null) {
            $primaryName = $meta['name'];
            $containerName = $node->getName() ?? $primaryName;
            $entry = [
                'container_id' => $node->getId(),
                'container_name' => $containerName,
                'host_screen' => $hostScreenClass,
                'persist_on_navigate' => $meta['persist_on_navigate'],
                'canonical_name' => $primaryName,
            ];

            $this->state_external_slots[$primaryName] = $entry;
            $this->state_slot_persistence[$primaryName] = $meta['persist_on_navigate'];

            foreach ($meta['aliases'] as $alias) {
                $this->state_external_slots[$alias] = $entry;
                $this->state_slot_persistence[$alias] = $meta['persist_on_navigate'];
            }
        }

        foreach ($node->getChildren() as $child) {
            if ($child instanceof Container) {
                $this->registerExternalSlotsFromTree($child, $hostScreenClass);
            }
        }
    }

    /**
     * Resolve a slot name or alias to its canonical primary slot name on this layout.
     */
    public function resolveCanonicalSlotName(string $slot): string
    {
        $normalized = trim($slot);
        if (isset($this->state_external_slots[$normalized])) {
            return $this->state_external_slots[$normalized]['canonical_name'];
        }

        $targetContainer = $this->getSlot($normalized);
        if ($targetContainer instanceof Container) {
            $targetId = $targetContainer->getId();
            foreach ($this->state_primary_slots as $primary) {
                $primaryContainer = $this->getSlot($primary);
                if ($primaryContainer instanceof Container && $primaryContainer->getId() === $targetId) {
                    return $primary;
                }
            }
        }

        return match ($normalized) {
            'left', 'explorer' => 'left_panel',
            'right', 'inspector' => 'right_panel',
            'content', 'center' => 'main',
            'statusbar', 'status_bar_items' => 'status_bar_center',
            default => $normalized,
        };
    }

    /**
     * Resolve a projection target slot (local layout slot or embedded shell sub-slot like status_bar_center).
     * When a Screen targets `status_bar`, it resolves to `status_bar_center` if registered by StatusBar
     * so permanent shell controls (`unit_menu`, `btn_reset`) are never destroyed.
     *
     * @return array{canonical: string, container_id: int, container: ?Container, external: bool, host_screen: ?string}|null
     */
    public function resolveProjectionSlot(string $slot): ?array
    {
        $normalized = trim($slot);
        if (in_array($normalized, ['status_bar', 'statusbar'], true)) {
            if (isset($this->state_external_slots['status_bar_center'])) {
                $ext = $this->state_external_slots['status_bar_center'];

                return [
                    'canonical' => $ext['canonical_name'],
                    'container_id' => $ext['container_id'],
                    'container' => null,
                    'external' => true,
                    'host_screen' => $ext['host_screen'],
                ];
            }
        }

        if (isset($this->state_external_slots[$normalized])) {
            $ext = $this->state_external_slots[$normalized];

            return [
                'canonical' => $ext['canonical_name'],
                'container_id' => $ext['container_id'],
                'container' => null,
                'external' => true,
                'host_screen' => $ext['host_screen'],
            ];
        }

        $local = $this->getSlot($normalized);
        if ($local instanceof Container) {
            return [
                'canonical' => $this->resolveCanonicalSlotName($normalized),
                'container_id' => $local->getId(),
                'container' => $local,
                'external' => false,
                'host_screen' => null,
            ];
        }

        return null;
    }

    /**
     * Determine whether the given Container is this layout's main content slot container.
     */
    public function isMainSlotContainer(Container $container): bool
    {
        $mainSlot = $this->getSlot('main') ?? $this->getSlot('content');

        return $mainSlot instanceof Container && $mainSlot->getId() === $container->getId();
    }

    /**
     * Get ownership metadata for a slot, if any.
     *
     * @return array{mode: string, owner: string, portal_container_id?: int, persist_on_navigate: bool}|null
     */
    public function getSlotOwner(string $slot): ?array
    {
        $resolved = $this->resolveProjectionSlot($slot);
        $canonical = $resolved !== null ? $resolved['canonical'] : $this->resolveCanonicalSlotName($slot);

        return $this->state_slot_owners[$canonical] ?? null;
    }

    /**
     * Record ownership metadata for a slot (portal or sub-screen).
     *
     * @param  array{mode: string, owner: string, portal_container_id?: int, persist_on_navigate: bool}  $ownerMeta
     */
    public function setSlotOwner(string $slot, array $ownerMeta): void
    {
        $resolved = $this->resolveProjectionSlot($slot);
        $canonical = $resolved !== null ? $resolved['canonical'] : $this->resolveCanonicalSlotName($slot);

        $this->state_slot_owners[$canonical] = $ownerMeta;
    }

    /**
     * Toggle visibility of StatusBar's built-in `lbl_status_message` when a Screen projects
     * into `status_bar_center` (alias `status_bar`) or clears it.
     */
    public function setStatusBarMessageVisible(bool $visible): void
    {
        $statusBarClass = $this->state_external_slots['status_bar_center']['host_screen']
            ?? $this->getActiveScreen('status_bar');

        if (! is_string($statusBarClass) || ! class_exists($statusBarClass) || ! is_subclass_of($statusBarClass, Screen::class)) {
            return;
        }

        $msgId = UIIdGenerator::generateFromName($statusBarClass, 'lbl_status_message');

        // Keep StatusBar's cached snapshot in sync so subsequent events on StatusBar don't drift
        $cached = UIStateManager::get($statusBarClass);
        if (is_array($cached) && isset($cached[$msgId]) && is_array($cached[$msgId])) {
            /** @var array<string, mixed> $msgNode */
            $msgNode = $cached[$msgId];
            if ($visible) {
                unset($msgNode['visible']);
            } else {
                $msgNode['visible'] = false;
            }
            $cached[$msgId] = $msgNode;
            UIStateManager::store($statusBarClass, $cached);
        }

        app(UIChangesCollector::class)->patchComponent($msgId, [
            'type' => 'newlabel',
            'visible' => $visible,
        ]);
    }

    /**
     * Prepare all declarative portal containers declared by a host Screen before its JSON is serialized.
     * Resolves target layout slots, applies fallbacks (`ignore` / `inline*`), configures Split open/size state,
     * updates ownership, and hides `lbl_status_message` if projecting into `status_bar_center`.
     */
    public function prepareScreenPortals(Screen $hostScreen, Container $screenRoot, bool $isInitialRender = false): void
    {
        $portals = $screenRoot->collectPortalContainers();
        if ($portals === []) {
            return;
        }

        $changedLayout = false;

        foreach ($portals as $portal) {
            $meta = $portal->getPortalMeta();
            if ($meta === null) {
                continue;
            }

            $target = $this->resolveProjectionSlot($meta['slot']);
            if ($target === null) {
                $this->applyPortalFallback($screenRoot, $portal, $meta['fallback']);

                continue;
            }

            $canonical = $target['canonical'];
            $portal->portalUnmounted(false)
                ->setPortalSlotName($canonical)
                ->portalTargetId($target['container_id']);

            if (! $isInitialRender) {
                app(UIChangesCollector::class)->add([
                    'clear_container' => $target['container_id'],
                ]);
            }

            if (! $target['external'] && $target['container'] instanceof Container) {
                $target['container']->clear();
            }

            unset(
                $this->activeScreens[$canonical],
                $this->state_active_screens[$canonical],
                $this->activeScreens[$meta['slot']],
                $this->state_active_screens[$meta['slot']]
            );

            $this->state_slot_owners[$canonical] = [
                'mode' => 'portal',
                'owner' => $hostScreen::class,
                'portal_container_id' => $portal->getId(),
                'persist_on_navigate' => $meta['persist_on_navigate'],
            ];

            if ($meta['size'] !== null) {
                $this->setSlotSize($canonical, $meta['size']);
            } else {
                $this->restoreSlotSize($canonical);
            }

            if ($meta['open']) {
                $this->expandSlot($canonical);
            } else {
                $this->collapseSlot($canonical);
            }

            if ($canonical === 'status_bar_center') {
                $this->setStatusBarMessageVisible(false);
            }

            $changedLayout = true;
        }

        if ($changedLayout) {
            $this->persistLayoutState();
        }
    }

    /**
     * Mount any declarative `screen:` slots declared in a host Screen's `.layout.yaml`.
     */
    public function mountDeclaredSlotScreens(Container $screenRoot, bool $isInitialRender = false): void
    {
        $declaredScreens = $screenRoot->getDeclaredSlotScreens();
        if ($declaredScreens === []) {
            return;
        }

        foreach ($declaredScreens as $entry) {
            $target = $this->resolveProjectionSlot($entry['slot']);
            if ($target === null) {
                if ($entry['fallback'] !== 'ignore') {
                    $targetClass = class_exists($entry['screen'])
                        ? $entry['screen']
                        : Screen::resolveScreenClassFromSlug($entry['screen']);
                    if ($targetClass !== null && class_exists($targetClass) && is_subclass_of($targetClass, Screen::class)) {
                        $hostMain = $this->findPrimaryWrapper($screenRoot);
                        Screen::embedInto($targetClass, $hostMain, $entry['params']);
                        if ($entry['fallback'] === 'inline_top') {
                            $children = $hostMain->getChildren();
                            $lastChild = end($children);
                            if ($lastChild !== false) {
                                $hostMain->moveChildToStart($lastChild->getId());
                            }
                        }
                    }
                }

                continue;
            }

            $canonical = $target['canonical'];

            if ($entry['size'] !== null) {
                $this->setSlotSize($canonical, $entry['size']);
            } else {
                $this->restoreSlotSize($canonical);
            }

            if ($target['external']) {
                // Embedded shell slot (e.g. status_bar_center) — mount sub-screen with parent = target container ID
                $targetClass = class_exists($entry['screen'])
                    ? $entry['screen']
                    : Screen::resolveScreenClassFromSlug($entry['screen']);
                if ($targetClass === null || ! class_exists($targetClass) || ! is_subclass_of($targetClass, Screen::class)) {
                    continue;
                }

                if (! $isInitialRender) {
                    app(UIChangesCollector::class)->add(['clear_container' => $target['container_id']]);
                }

                $subScreen = Screen::make($targetClass);
                $subScreen->clearCachedScreenSnapshot();
                $subScreen->render(
                    incomingStorage: Screen::getCurrentIncomingStorage(),
                    queryParams: Screen::getCurrentQueryParams(),
                    parent: $target['container_id'],
                    shouldReset: true,
                    buildParams: $entry['params']
                );

                $this->setActiveScreen($canonical, $targetClass);
                $this->state_slot_owners[$canonical] = [
                    'mode' => 'screen',
                    'owner' => $targetClass,
                    'persist_on_navigate' => $entry['persist_on_navigate'] || $targetClass::shouldPersistOnNavigate(),
                ];

                if ($canonical === 'status_bar_center') {
                    $this->setStatusBarMessageVisible(false);
                }

                $this->persistLayoutState();

                continue;
            }

            $this->showInto(
                $entry['screen'],
                slot: $canonical,
                params: $entry['params'],
                updateBrowserUrl: false,
                force: true
            );

            if (isset($this->state_slot_owners[$canonical]) && $entry['persist_on_navigate']) {
                $this->state_slot_owners[$canonical]['persist_on_navigate'] = true;
            }

            if (! $entry['open']) {
                $this->collapseSlot($canonical);
            }

            $this->persistLayoutState();
        }
    }

    /**
     * Apply fallback strategy (`ignore` or `inline*`) to a portal container when its target slot is unavailable.
     */
    public function applyPortalFallback(Container $screenRoot, Container $portal, string $fallback): void
    {
        if ($fallback === 'ignore') {
            $portal->portalUnmounted(true)->portalTargetId(null);

            return;
        }

        $portal->clearPortalTarget();

        if ($fallback === 'inline_top') {
            $parentId = $portal->getParent();
            $parentContainer = is_int($parentId) ? $screenRoot->findById($parentId) : null;
            if ($parentContainer instanceof Container) {
                $parentContainer->moveChildToStart($portal->getId());
            } else {
                $screenRoot->moveChildToStart($portal->getId());
            }
        }
    }

    /**
     * Find the primary content wrapper container inside a Screen's root container.
     */
    protected function findPrimaryWrapper(Container $screenRoot): Container
    {
        foreach ($screenRoot->getChildren() as $child) {
            if ($child instanceof Container && $child->getPortalSlot() === null) {
                return $child;
            }
        }

        return $screenRoot;
    }

    /**
     * Clear all non-persistent auxiliary slots (both sub-Screens and Screen portals) when navigating `main`.
     */
    protected function clearNonPersistentAuxiliarySlots(): void
    {
        $reservedMainAndShell = ['main', 'content', 'center', 'top_menu', 'menu', 'sidebar', 'activity_bar', 'status_bar'];
        $candidateSlots = array_values(array_unique(array_merge(
            array_keys($this->state_active_screens),
            array_keys($this->state_slot_owners)
        )));

        foreach ($candidateSlots as $slotName) {
            if (in_array($slotName, $reservedMainAndShell, true)) {
                continue;
            }

            $owner = $this->state_slot_owners[$slotName] ?? null;
            if ($owner !== null) {
                if (($owner['persist_on_navigate'] ?? false) === true) {
                    continue;
                }
                if ($owner['mode'] === 'screen') {
                    $ownerClass = $owner['owner'];
                    if (class_exists($ownerClass) && is_subclass_of($ownerClass, Screen::class) && $ownerClass::shouldPersistOnNavigate()) {
                        continue;
                    }
                }
                $this->clearSlot($slotName, collapse: true);

                continue;
            }

            $persist = $this->state_slot_persistence[$slotName] ?? true;
            if ($persist === false && isset($this->state_active_screens[$slotName])) {
                $activeSlotScreen = $this->state_active_screens[$slotName];
                if (
                    class_exists($activeSlotScreen)
                    && is_subclass_of($activeSlotScreen, Screen::class)
                    && $activeSlotScreen::shouldPersistOnNavigate()
                ) {
                    continue;
                }
                $this->clearSlot($slotName, collapse: true);
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
        } elseif ($split->getCollapseTarget() === $side) {
            // Re-emit expanded state for collapsible auxiliary slots in case the user collapsed it client-side
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
     * Determine whether the Split pane hosting a slot is currently expanded.
     */
    public function isSlotExpanded(string $slot): bool
    {
        $splitInfo = $this->findOwningSplitForSlot($slot);
        if ($splitInfo === null) {
            return $this->resolveProjectionSlot($slot) !== null;
        }

        return $splitInfo['split']->getCollapsedPanel() !== $splitInfo['side'];
    }

    /**
     * Temporarily override the Split size of a slot (or restore default when $size is null).
     */
    public function setSlotSize(string $slot, ?string $size): bool
    {
        if ($size === null || trim($size) === '') {
            return $this->restoreSlotSize($slot);
        }

        $splitInfo = $this->findOwningSplitForSlot($slot);
        if ($splitInfo === null) {
            return false;
        }

        $split = $splitInfo['split'];
        $side = $splitInfo['side'];
        $splitId = $split->getId();

        if (! isset($this->state_split_default_sizes[$splitId])) {
            $this->state_split_default_sizes[$splitId] = [
                'split_size' => $split->getSplitSize(),
                'split_size_target' => $split->getSplitSizeTarget(),
            ];
        }

        $normalizedSize = trim($size);
        $split->splitSize($normalizedSize)->splitSizeTarget($side);
        $this->emitSplitSizeDiff($split);

        return true;
    }

    /**
     * Restore the default Split size of a slot if it was temporarily overridden.
     */
    public function restoreSlotSize(string $slot): bool
    {
        $splitInfo = $this->findOwningSplitForSlot($slot);
        if ($splitInfo === null) {
            return false;
        }

        $split = $splitInfo['split'];
        $splitId = $split->getId();

        if (! isset($this->state_split_default_sizes[$splitId])) {
            return false;
        }

        $saved = $this->state_split_default_sizes[$splitId];
        unset($this->state_split_default_sizes[$splitId]);

        $split->splitSize($saved['split_size'])->splitSizeTarget($saved['split_size_target']);
        $this->emitSplitSizeDiff($split);

        return true;
    }

    /**
     * Clear the contents of a slot and optionally collapse its parent Split pane.
     */
    public function clearSlot(string $slot, bool $collapse = true): bool
    {
        $resolved = $this->resolveProjectionSlot($slot);
        if ($resolved === null) {
            return false;
        }

        $canonical = $resolved['canonical'];
        $containerId = $resolved['container_id'];

        if (! $resolved['external'] && $resolved['container'] instanceof Container) {
            $resolved['container']->clear();
        }

        unset(
            $this->activeScreens[$slot],
            $this->state_active_screens[$slot],
            $this->activeScreens[$canonical],
            $this->state_active_screens[$canonical],
            $this->state_slot_owners[$slot],
            $this->state_slot_owners[$canonical]
        );

        app(UIChangesCollector::class)->add([
            'clear_container' => $containerId,
        ]);

        $this->restoreSlotSize($canonical);

        if ($collapse) {
            $this->collapseSlot($canonical);
        }

        if ($canonical === 'status_bar_center') {
            $this->setStatusBarMessageVisible(true);
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
        $canonicalSlot = $this->resolveCanonicalSlotName($effectiveSlot);

        // When navigating main content, clear and collapse non-persistent auxiliary slots (portals & sub-screens)
        if ($isMainNavigation) {
            $this->clearNonPersistentAuxiliarySlots();
        } else {
            // If another Screen takes over an auxiliary slot previously owned by a Screen portal with a custom size,
            // restore the layout's default Split size before expanding.
            $previousOwner = $this->state_slot_owners[$canonicalSlot] ?? null;
            if ($previousOwner !== null && $previousOwner['mode'] === 'portal') {
                $this->restoreSlotSize($canonicalSlot);
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

        if (! $isMainNavigation) {
            $this->state_slot_owners[$canonicalSlot] = [
                'mode' => 'screen',
                'owner' => $targetClass,
                'persist_on_navigate' => $targetClass::shouldPersistOnNavigate(),
            ];
        }

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
     * Emit incremental Split collapse state change to the frontend without clobbering full initial payloads.
     */
    protected function emitSplitCollapseDiff(Split $split): void
    {
        app(UIChangesCollector::class)->patchComponent($split->getId(), [
            'collapsed_panel' => $split->getCollapsedPanel(),
        ]);
    }

    /**
     * Emit incremental Split size override / restore change to the frontend.
     */
    protected function emitSplitSizeDiff(Split $split): void
    {
        app(UIChangesCollector::class)->patchComponent($split->getId(), [
            'split_size' => $split->getSplitSize(),
            'split_size_target' => $split->getSplitSizeTarget(),
        ]);
    }

    /**
     * Persist updated layout container tree and state_* variables to UIStateManager.
     */
    public function persistLayoutState(): void
    {
        if (isset($this->container)) {
            UIStateManager::store(static::class, $this->container->toJson());
        }
        UIStateManager::storeScreenState(static::class, $this->getStateVariables());
    }
}
