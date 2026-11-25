<?php

namespace Idei\Usim\Support;

use Idei\Usim\Components\Button;
use Idei\Usim\Components\Calendar;
use Idei\Usim\Components\Card;
use Idei\Usim\Components\CardGrid;
use Idei\Usim\Components\Carousel;
use Idei\Usim\Components\Checkbox;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\Form;
use Idei\Usim\Components\Image;
use Idei\Usim\Components\Input;
use Idei\Usim\Components\ItemCard;
use Idei\Usim\Components\ItemList;
use Idei\Usim\Components\Label;
use Idei\Usim\Components\MenuDropdown;
use Idei\Usim\Components\NavTree;
use Idei\Usim\Components\NewLabel;
use Idei\Usim\Components\RichText;
use Idei\Usim\Components\Select;
use Idei\Usim\Components\Split;
use Idei\Usim\Components\Table;
use Idei\Usim\Components\TableCell;
use Idei\Usim\Components\TableHeaderCell;
use Idei\Usim\Components\TableHeaderRow;
use Idei\Usim\Components\TableRow;
use Idei\Usim\Components\Textarea;
use Idei\Usim\Components\Timer;
use Idei\Usim\Components\UIComponent;
use Idei\Usim\Components\Uploader;
use Idei\Usim\Contracts\ComponentIdGeneratorInterface;
use Idei\Usim\Contracts\ScreenLifecycleOrchestratorInterface;
use Idei\Usim\Contracts\UIDifferInterface;
use Idei\Usim\Contracts\UIElement;
use Idei\Usim\Contracts\UIStateRepositoryInterface;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\Exceptions\UIRequestValidationException;
use Idei\Usim\Exceptions\UsimReadOnlyLifecycleException;
use Idei\Usim\Layout\AbstractLayout;
use Idei\Usim\Layout\LayoutInterface;
use Idei\Usim\Screen;
use Idei\Usim\UI;
use Idei\Usim\UIChangesCollector;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionProperty;
use RuntimeException;

/**
 * Orchestrates the full lifecycle of a Screen:
 * - Request / render initialization
 * - Event action execution
 * - Component tree reconstruction and caching
 * - Component dirty tracking and diff calculation
 * - Layout wrapping and nested screen embedding
 */
class ScreenLifecycleOrchestrator implements ScreenLifecycleOrchestratorInterface
{
    protected UsimConfig $usimConfig;
    protected MethodParameterResolver $parameterResolver;

    public function __construct(
        protected UIStateRepositoryInterface $stateRepository,
        protected UIDifferInterface $differ,
        protected ComponentIdGeneratorInterface $idGenerator,
        protected UIChangesCollector $changesCollector,
        ?UsimConfig $usimConfig = null,
        ?MethodParameterResolver $parameterResolver = null,
    ) {
        $this->usimConfig = $usimConfig ?? app(UsimConfig::class);
        $this->parameterResolver = $parameterResolver ?? app(MethodParameterResolver::class);
    }

    /**
     * Render the screen for an initial page view or full reload.
     *
     * @param  array<string, mixed>  $incomingStorage  Storage data from frontend
     * @param  array<string, mixed>  $queryParams  Query parameters from frontend
     * @param  int|string|null  $parent  Target parent container (default: 'main')
     * @param  bool  $shouldReset  Whether to reset screen cache before rendering
     * @param  array<int|string, mixed>  $buildParams  Parameters passed to buildBaseUI
     */
    public function render(
        Screen $screen,
        array $incomingStorage = [],
        array $queryParams = [],
        int|string|null $parent = 'main',
        bool $shouldReset = false,
        array $buildParams = []
    ): void {
        $this->idGenerator->pushCurrentContext($screen->getContextIdentifier());
        try {
            $this->changesCollector->setStorage($incomingStorage);

            if ($shouldReset) {
                $screen->onResetScreen();
            }

            // Check if this screen should be wrapped by a Layout Screen
            if ($parent === 'main' && $screen->modalLayerIndex === 0 && ! ($screen instanceof LayoutInterface)) {
                $layoutClass = $screen::getLayoutClass();
                if ($layoutClass !== null && class_exists($layoutClass)) {
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

                        // 0. Pre-discover external sub-slots declared by shell screens (e.g. StatusBar's
                        // status_bar_left/center/right) so main Screen portal projections resolve them immediately
                        // while preserving canonical payload ordering (layout -> main -> menu -> status_bar).
                        foreach ($layout->getPrimarySlots() as $slotName) {
                            if (in_array($slotName, ['main', 'content', 'center', 'top_menu', 'menu', 'sidebar'], true)) {
                                continue;
                            }

                            $activeClass = $layout->getActiveScreen($slotName)
                                ?? $layout->getSlotDefaultScreen($slotName);

                            if ($activeClass === null && $slotName === 'status_bar') {
                                $statusBarConfig = config('usim.default_status_bar_screen', $this->usimConfig->defaultStatusBarScreen);
                                $activeClass = \is_string($statusBarConfig) && $statusBarConfig !== '' ? $statusBarConfig : null;
                            }

                            if (! \is_string($activeClass) || $activeClass === '') {
                                continue;
                            }

                            $targetClass = is_subclass_of($activeClass, Screen::class)
                                ? $activeClass
                                : Screen::resolveScreenClassFromSlug($activeClass);

                            if ($targetClass !== null && class_exists($targetClass) && is_subclass_of($targetClass, Screen::class)) {
                                $shellScreen = Screen::make($targetClass);
                                $this->idGenerator->pushCurrentContext($shellScreen->getContextIdentifier());
                                try {
                                    $shellTree = $this->reconstructScreenTree($shellScreen);
                                    $layout->registerExternalSlotsFromTree($shellTree, $targetClass);
                                } finally {
                                    $this->idGenerator->popCurrentContext();
                                }
                            }
                        }

                        // 1. Re-embed main content slot (triggers Screen portal projections & declared slot screens)
                        $slot = $layout->getSlot('content') ?? $layout->getSlot('main');
                        if ($slot instanceof Container) {
                            $slot->clear();
                            $this->embedInto($screen::class, $slot, $buildParams);
                        }

                        // 2. Re-embed navigation menu slot (supports top_menu, sidebar, or menu)
                        $menuSlot = $layout->getSlot('sidebar')
                            ?? $layout->getSlot('top_menu')
                            ?? $layout->getSlot('menu');
                        $isSidebarSlot = $layout->getSlot('sidebar') !== null;

                        $configuredMenuDefault = $isSidebarSlot
                            ? ($layout->getSlotDefaultScreen('sidebar')
                                ?? config('usim.default_sidebar_menu_screen', $this->usimConfig->defaultSidebarMenuScreen)
                                ?? $this->usimConfig->defaultMenuScreen)
                            : ($layout->getSlotDefaultScreen('top_menu')
                                ?? $layout->getSlotDefaultScreen('menu')
                                ?? $this->usimConfig->defaultMenuScreen);

                        $explicitHostMenu = $screen::$menuScreen !== null ? $screen::getMenuScreen() : null;
                        $explicitLayoutMenu = $layout::$menuScreen !== null ? $layout::getMenuScreen() : null;

                        $menuClass = $explicitHostMenu
                            ?? $explicitLayoutMenu
                            ?? $layout->getActiveScreen('sidebar')
                            ?? $layout->getActiveScreen('top_menu')
                            ?? $layout->getActiveScreen('menu')
                            ?? $configuredMenuDefault;

                        if (\is_string($menuClass) && ! is_subclass_of($menuClass, Screen::class)) {
                            $menuClass = Screen::resolveScreenClassFromSlug($menuClass);
                        }

                        if ($menuSlot instanceof Container && \is_string($menuClass) && class_exists($menuClass) && is_subclass_of($menuClass, Screen::class)) {
                            $menuSlot->clear();
                            $this->embedInto($menuClass, $menuSlot);
                            $layout->setActiveScreen($isSidebarSlot ? 'sidebar' : 'top_menu', $menuClass);
                            $layout->registerExternalSlotsFromTree($menuSlot, $menuClass);
                        }

                        // 3. Re-embed any other registered slot with an active or default screen (e.g. status_bar, right_panel)
                        $embeddedContainerIds = [];
                        if ($slot instanceof Container) {
                            $embeddedContainerIds[$slot->getId()] = true;
                        }
                        if ($menuSlot instanceof Container) {
                            $embeddedContainerIds[$menuSlot->getId()] = true;
                        }

                        foreach ($layout->getPrimarySlots() as $slotName) {
                            if (in_array($slotName, ['main', 'content', 'center', 'top_menu', 'menu', 'sidebar'], true)) {
                                continue;
                            }

                            $otherSlot = $layout->getSlot($slotName);
                            if (! $otherSlot instanceof Container || isset($embeddedContainerIds[$otherSlot->getId()])) {
                                continue;
                            }

                            $activeClass = $layout->getActiveScreen($slotName)
                                ?? $layout->getSlotDefaultScreen($slotName);

                            if ($activeClass === null && $slotName === 'status_bar') {
                                $statusBarConfig = config('usim.default_status_bar_screen', $this->usimConfig->defaultStatusBarScreen);
                                $activeClass = \is_string($statusBarConfig) && $statusBarConfig !== '' ? $statusBarConfig : null;
                            }

                            if (! \is_string($activeClass) || $activeClass === '') {
                                continue;
                            }

                            $targetClass = is_subclass_of($activeClass, Screen::class)
                                ? $activeClass
                                : Screen::resolveScreenClassFromSlug($activeClass);

                            if ($targetClass !== null && class_exists($targetClass) && is_subclass_of($targetClass, Screen::class)) {
                                $otherSlot->clear();
                                $this->embedInto($targetClass, $otherSlot);
                                $layout->setActiveScreen($slotName, $targetClass);
                                $layout->registerExternalSlotsFromTree($otherSlot, $targetClass);
                                $embeddedContainerIds[$otherSlot->getId()] = true;

                                // Re-apply status bar message hiding if main Screen projected into status_bar_center
                                if ($slotName === 'status_bar' && $layout->getSlotOwner('status_bar_center') !== null) {
                                    $layout->setStatusBarMessageVisible(false);
                                }
                            }
                        }

                        foreach ($layout->getActiveScreens() as $slotName => $activeClass) {
                            if (in_array($slotName, ['main', 'content', 'center', 'top_menu', 'menu', 'sidebar'], true)) {
                                continue;
                            }
                            $otherSlot = $layout->getSlot($slotName);
                            if (! $otherSlot instanceof Container || isset($embeddedContainerIds[$otherSlot->getId()])) {
                                continue;
                            }

                            $targetClass = is_subclass_of($activeClass, Screen::class)
                                ? $activeClass
                                : Screen::resolveScreenClassFromSlug($activeClass);

                            if ($targetClass !== null && class_exists($targetClass) && is_subclass_of($targetClass, Screen::class)) {
                                $otherSlot->clear();
                                $this->embedInto($targetClass, $otherSlot);
                                $layout->registerExternalSlotsFromTree($otherSlot, $targetClass);
                                $embeddedContainerIds[$otherSlot->getId()] = true;
                            }
                        }

                        $layout->persistLayoutState();
                        UIStateManager::setClientActiveLayout($layoutClass);

                        return;
                    } finally {
                        AbstractLayout::setActiveHostScreen(null);
                    }
                }
            }


            $this->initializeEventContext(
                screen: $screen,
                incomingStorage: $incomingStorage,
                queryParams: $queryParams,
                parent: $parent,
                buildParams: $buildParams
            );

            // Standalone screen ($layout = null or direct render without AbstractLayout): apply portal fallbacks
            if ($parent === 'main' && ! ($screen instanceof LayoutInterface)) {
                $standaloneContainer = $screen->getContainer();
                if ($standaloneContainer instanceof Container) {
                    $this->applyStandalonePortalFallbacks($standaloneContainer);
                }
            }

            $this->finalizeEventContext(screen: $screen, reload: true, params: $buildParams);

            $agentContext = $screen->getAgentContext();
            if (! empty($agentContext)) {
                $this->changesCollector->add(['agent_context' => $agentContext]);
            }
        } finally {
            $this->idGenerator->popCurrentContext();
        }
    }

    /**
     * Execute an action handler within the managed Screen event lifecycle.
     *
     * @param  string  $method  name of the action handler method (e.g., 'onSave', 'onResetScreen')
     * @param  array<string, mixed>  $parameters  Parameters passed to the action handler
     * @param  array<string, mixed>  $incomingStorage  Storage data from frontend
     * @param  array<string, mixed>  $queryParams  Query parameters from frontend
     * @param  int|string|null  $parent  Target parent container
     * @param  int|null  $triggerComponentId  ID of the component that triggered the event
     */
    public function handleAction(
        Screen $screen,
        string $method,
        array $parameters = [],
        array $incomingStorage = [],
        array $queryParams = [],
        int|string|null $parent = null,
        ?int $triggerComponentId = null
    ): void {
        $this->idGenerator->pushCurrentContext($screen->getContextIdentifier());
        try {
            $this->changesCollector->setStorage($incomingStorage);

            if ($method === 'onResetScreen') {
                $screen->onResetScreen();
                $this->initializeEventContext(
                    screen: $screen,
                    incomingStorage: $incomingStorage,
                    queryParams: $queryParams,
                    parent: $parent,
                    eventParameters: $parameters,
                    triggerComponentId: $triggerComponentId
                );
                $this->finalizeEventContext(screen: $screen, reload: false);

                return;
            }

            $this->initializeEventContext(
                screen: $screen,
                incomingStorage: $incomingStorage,
                queryParams: $queryParams,
                parent: $parent,
                eventParameters: $parameters,
                triggerComponentId: $triggerComponentId
            );

            if (method_exists($screen, $method)) {
                $reflectionMethod = new ReflectionMethod($screen, $method);

                try {
                    $arguments = $this->parameterResolver->resolve(
                        reflectionMethod: $reflectionMethod,
                        screen: $screen,
                        parameters: $parameters,
                        queryParams: $queryParams,
                        incomingStorage: $incomingStorage,
                        triggerComponentId: $triggerComponentId
                    );

                    $reflectionMethod->invokeArgs($screen, $arguments);
                } catch (UIRequestValidationException $validationException) {
                    $uiRequest = $validationException->uiRequest;
                    if ($uiRequest && $uiRequest->shouldAutoBindErrors()) {
                        $uiRequest->bindErrorsToScreen($screen, $validationException->validator->errors());

                        $toastMsg = $uiRequest->errorToastMessage();
                        if ($toastMsg !== null) {
                            $screen->toast($toastMsg, 'error');
                        }

                        $uiRequest->failedValidation($validationException->validator);
                    } else {
                        throw $validationException;
                    }
                }
            } elseif (($binding = $this->resolveScreenComponentBinding($screen, $triggerComponentId, $method, queryOnly: false)) !== null) {
                $binding['component']->handleServiceAction($binding['operation'], $parameters, $screen);
            }

            $this->finalizeEventContext(screen: $screen, reload: false);
        } finally {
            $this->idGenerator->popCurrentContext();
        }
    }

    /**
     * Execute a query handler within a read-only Screen lifecycle.
     *
     * @param  array<string, mixed>  $parameters  Parameters passed to the query handler
     * @param  array<string, mixed>  $incomingStorage  Storage data from frontend
     * @param  array<string, mixed>  $queryParams  Query parameters from frontend
     * @param  int|string|null  $parent  Target parent container
     * @param  int|null  $triggerComponentId  ID of the component that triggered the query
     */
    public function handleQuery(
        Screen $screen,
        string $method,
        array $parameters = [],
        array $incomingStorage = [],
        array $queryParams = [],
        int|string|null $parent = null,
        ?int $triggerComponentId = null
    ): mixed {
        $this->idGenerator->pushCurrentContext($screen->getContextIdentifier());
        try {
            $this->changesCollector->setStorage($incomingStorage);

            $this->initializeEventContext(
                screen: $screen,
                incomingStorage: $incomingStorage,
                queryParams: $queryParams,
                parent: $parent,
                eventParameters: $parameters,
                triggerComponentId: $triggerComponentId
            );

            $container = $screen->getContainer();
            if ($container !== null) {
                $this->clearContainerDirtyState($container);
            }

            if (method_exists($screen, $method)) {
                $reflectionMethod = new ReflectionMethod($screen, $method);
                $arguments = $this->parameterResolver->resolve(
                    reflectionMethod: $reflectionMethod,
                    screen: $screen,
                    parameters: $parameters,
                    queryParams: $queryParams,
                    incomingStorage: $incomingStorage,
                    triggerComponentId: $triggerComponentId
                );

                $result = $reflectionMethod->invokeArgs($screen, $arguments);
            } elseif (($binding = $this->resolveScreenComponentBinding($screen, $triggerComponentId, $method, queryOnly: true)) !== null) {
                $boundComponent = $binding['component'];
                if ($boundComponent instanceof ItemList) {
                    $rawSearch = $parameters['search'] ?? $queryParams['search'] ?? '';
                    $search = is_scalar($rawSearch) ? (string) $rawSearch : '';

                    $result = $boundComponent->queryServiceItems($search, $parameters);
                } else {
                    throw new \BadMethodCallException("Query method [{$method}] does not exist on screen [".get_class($screen).'].');
                }
            } else {
                throw new \BadMethodCallException("Query method [{$method}] does not exist on screen [" . get_class($screen) . '].');
            }

            if ($container !== null) {
                $dirtyChanges = $this->collectDirtyComponentChanges($container);
                if (! empty($dirtyChanges)) {
                    throw new UsimReadOnlyLifecycleException(
                        'Component tree mutations are not permitted inside GET query methods [' . get_class($screen) . "::{$method}()]. " .
                            'GET methods are reserved exclusively for delivering data/media payloads to components. ' .
                            'If you need to mutate the UI, use a POST event action [on*()] instead.'
                    );
                }
            }

            return $result;
        } finally {
            $this->idGenerator->popCurrentContext();
        }
    }

    public function supportsComponentHandler(
        Screen $screen,
        ?int $componentId,
        string $action,
        bool $queryOnly = false
    ): bool {
        $this->idGenerator->pushCurrentContext($screen->getContextIdentifier());
        try {
            $container = $screen->getContainer() ?? $this->reconstructScreenTree($screen);

            return $this->resolveComponentServiceBinding($container, $componentId, $action, $queryOnly) !== null;
        } catch (\Throwable) {
            return false;
        } finally {
            $this->idGenerator->popCurrentContext();
        }
    }

    /**
     * Resolve a service-bound component (ItemList or CardGrid) from an already-initialized screen container.
     *
     * @return array{component: ItemList|CardGrid, operation: string}|null
     */
    protected function resolveScreenComponentBinding(
        Screen $screen,
        ?int $componentId,
        string $action,
        bool $queryOnly
    ): ?array {
        $container = $screen->getContainer();

        return $container !== null
            ? $this->resolveComponentServiceBinding($container, $componentId, $action, $queryOnly)
            : null;
    }

    /**
     * Resolve a service-bound component (ItemList or CardGrid) and operation.
     *
     * @return array{component: ItemList|CardGrid, operation: string}|null
     */
    protected function resolveComponentServiceBinding(
        Container $container,
        ?int $componentId,
        string $action,
        bool $queryOnly
    ): ?array {
        $itemListBinding = $this->resolveItemListServiceBinding($container, $componentId, $action, $queryOnly);
        if ($itemListBinding !== null) {
            return $itemListBinding;
        }

        if (! $queryOnly) {
            return $this->resolveCardGridServiceBinding($container, $componentId, $action);
        }

        return null;
    }

    /**
     * Resolve a CardGrid component and event-driven query operation (`POST /api/ui-event`).
     *
     * @return array{component: CardGrid, operation: string}|null
     */
    protected function resolveCardGridServiceBinding(
        Container $container,
        ?int $componentId,
        string $action
    ): ?array {
        $parsed = CardGrid::parseServiceAction($action);

        $component = $componentId !== null ? $container->findById($componentId) : null;
        if (! ($component instanceof CardGrid) && $parsed !== null) {
            $target = $parsed['target'];
            $component = $container->findByName($target)
                ?? (ctype_digit($target) ? $container->findById((int) $target) : null);
        }

        if (! ($component instanceof CardGrid)) {
            return null;
        }

        $operation = $parsed['operation'] ?? null;
        if ($operation === null && $component->get('query_action') === $action) {
            $operation = 'query';
        }

        if ($operation !== 'query' || ! $component->supportsServiceOperation($operation)) {
            return null;
        }

        return [
            'component' => $component,
            'operation' => $operation,
        ];
    }

    /**
     * Resolve an ItemList component and operation for a service-wired query or mutation action.
     *
     * @return array{component: ItemList, operation: string}|null
     */
    protected function resolveItemListServiceBinding(
        Container $container,
        ?int $componentId,
        string $action,
        bool $queryOnly
    ): ?array {
        $parsed = ItemList::parseServiceAction($action);

        $component = $componentId !== null ? $container->findById($componentId) : null;
        if (! ($component instanceof ItemList) && $parsed !== null) {
            $target = $parsed['target'];
            $component = $container->findByName($target)
                ?? (ctype_digit($target) ? $container->findById((int) $target) : null);
        }

        if (! ($component instanceof ItemList)) {
            return null;
        }

        $operation = $parsed['operation'] ?? null;

        if ($operation === null) {
            if ($queryOnly) {
                if ($component->get('action') === $action) {
                    $operation = 'list';
                }
            } else {
                $eventMap = [
                    'on_create' => 'create',
                    'on_update' => 'update',
                    'on_delete' => 'delete',
                    'on_bulk_delete' => 'bulk_delete',
                ];

                foreach ($eventMap as $configKey => $candidateOp) {
                    $cfg = $component->get($configKey);
                    $configuredAction = is_array($cfg) ? ($cfg['action'] ?? null) : (is_string($cfg) ? $cfg : null);
                    if ($configuredAction === $action) {
                        $operation = $candidateOp;
                        break;
                    }
                }
            }
        }

        if ($operation === null) {
            return null;
        }

        if ($queryOnly && $operation !== 'list') {
            return null;
        }

        if (! $queryOnly && ! in_array($operation, ['create', 'update', 'delete', 'bulk_delete'], true)) {
            return null;
        }

        if (! $component->supportsServiceOperation($operation)) {
            return null;
        }

        return [
            'component' => $component,
            'operation' => $operation,
        ];
    }

    /**
     * Initialize event context
     *
     * Called before invoking event handler.
     * Loads UI container and captures state for diff calculation.
     * Also injects storage values and component references into protected properties.
     *
     * @param  array<string, mixed>  $incomingStorage  Storage data from frontend (decrypted)
     * @param  array<string, mixed>  $queryParams  Query parameters from frontend
     * @param  int|string|null  $parent  The parent screen/container ID (used for nested screens)
     * @param  array<string, mixed>  $eventParameters  Parameters sent with the UI event
     * @param  int|null  $triggerComponentId  ID of the component that triggered the event
     * @param  array<int|string, mixed>  $buildParams  Parameters passed to buildBaseUI if regenerating cache
     */
    public function initializeEventContext(
        Screen $screen,
        array $incomingStorage = [],
        array $queryParams = [],
        int|string|null $parent = null,
        array $eventParameters = [],
        ?int $triggerComponentId = null,
        array $buildParams = []
    ): void {
        $screen->setIncomingStorage($incomingStorage);
        $screen->setQueryParams($queryParams);
        Screen::setCurrentIncomingStorage($incomingStorage);
        Screen::setCurrentQueryParams($queryParams);

        // Inject storage values into protected properties (store_* variables)
        $screen->injectStorageValues($incomingStorage);

        // Restore internal screen state from UIStateRepository snapshot cache (state_* variables)
        $cachedState = $this->stateRepository->getScreenState($screen->getContextIdentifier());
        if (! empty($cachedState)) {
            $screen->injectStateVariables($cachedState);
        }

        $container = $this->reconstructScreenTree($screen, ...$buildParams);
        $screen->setContainer($container);

        if ($parent !== null && $parent !== '') {
            $screen->setParent($parent);
            $container->setParent($parent);
        } elseif ($container->getParent() !== null) {
            $screen->setParent($container->getParent());
        }

        if (! empty($eventParameters) || $triggerComponentId !== null) {
            $this->hydrateClientComponentState($container, $eventParameters, $triggerComponentId);
        }

        $this->clearContainerDirtyState($container);
        $screen->setOldUI($container->toJson());

        // Inject component references into protected properties
        $this->injectComponentReferences($screen, $container);
    }

    /**
     * Finalize event context
     *
     * Automatically detects changes by comparing UI state, stores updated UI,
     * and records formatted response in changes collector.
     *
     * @param  array<int|string, mixed>  $params
     */
    public function finalizeEventContext(Screen $screen, bool $reload = false, array $params = []): void
    {
        if ($reload) {
            $screen->callPostLoadUI($params);
        }

        $container = $screen->getContainer();
        if ($container === null) {
            return;
        }

        // Get current UI state
        $newUI = $container->toJson();
        $screen->setNewUI($newUI);

        // Persist the final container state for both event diffs and full reloads.
        $this->cacheScreenSnapshot($screen, $container);

        $diff = $screen->callBuildDiffResponse(reload: $reload);
        $storageVariables = $screen->getStorageVariables();
        $this->changesCollector->add($diff);
        $this->changesCollector->setStorage($storageVariables);
    }

    /**
     * Reconstruct the current screen component tree from cache.
     *
     * If no cached snapshot exists, the UI is generated first and then reconstructed.
     *
     * @param  mixed  ...$params  Optional parameters passed to buildBaseUI if regenerating cache
     */
    public function reconstructScreenTree(Screen $screen, mixed ...$params): Container
    {
        $jsonUI = $this->getCachedScreenSnapshot($screen, ...$params);

        return $this->reconstructContainerFromJson($jsonUI, $screen->getContextIdentifier());
    }

    /**
     * Get stored UI state from cache, regenerating it if missing or invalid.
     *
     * @param  mixed  ...$params  Optional parameters passed to buildBaseUI
     * @return array<int|string, array<string, mixed>> UI structure in JSON format
     */
    public function getCachedScreenSnapshot(Screen $screen, mixed ...$params): array
    {
        $contextKey = $screen->getContextIdentifier();

        // Check if UI exists in cache
        $cachedUI = $this->stateRepository->get($contextKey);

        if ($this->isTypedCachedScreenSnapshot($cachedUI) && $this->isValidCachedScreenSnapshot($cachedUI)) {
            $cachedState = $this->stateRepository->getScreenState($contextKey);
            if (! empty($cachedState)) {
                $screen->injectStateVariables($cachedState);
            }
            return $cachedUI;
        }

        if ($cachedUI !== null) {
            $this->stateRepository->clear($contextKey);
        }

        $currentClass = $screen::class;
        $currentClassSlug = strtolower(str_replace('\\', '_', $currentClass))
            . ($screen->modalLayerIndex > 0 ? "_{$screen->modalLayerIndex}" : '');
        $container = UI::container($currentClassSlug, $contextKey)
            ->parent($screen->parent)
            ->modalLayerIndex($screen->modalLayerIndex)
            ->layout(LayoutType::VERTICAL)
            ->justifyContent('center')
            ->alignItems('center');

        // Generate and cache UI directly
        $screen->callBuildBaseUI($container, ...$params);

        $ui = $container
            ->root(true)
            ->toJson();

        $this->cacheScreenSnapshot($screen, $container);

        return $ui;
    }

    /**
     * Store UI state snapshot and internal state in repository cache.
     */
    public function cacheScreenSnapshot(Screen $screen, Container $container): void
    {
        $contextKey = $screen->getContextIdentifier();
        $this->stateRepository->store($contextKey, $container->toJson());

        $state = $screen->getStateVariables();
        if (! empty($state)) {
            $this->stateRepository->storeScreenState($contextKey, $state);
        } else {
            $this->stateRepository->clearScreenState($contextKey);
        }
    }

    /**
     * Clear the cached screen snapshot and state variables.
     */
    public function clearCachedScreenSnapshot(Screen $screen): bool
    {
        $contextKey = $screen->getContextIdentifier();
        $this->stateRepository->clearScreenState($contextKey);

        return $this->stateRepository->clear($contextKey);
    }

    /**
     * Build diff response in indexed format.
     *
     * @return array<int|string, array<string, mixed>> Indexed diff response
     */
    public function buildDiffResponse(Screen $screen, bool $reload = false): array
    {
        $oldUI = $screen->getOldUI() ?? [];
        $newUI = $screen->getNewUI() ?? [];

        $diff = $reload ?
            $this->differ->compare([], $newUI) :
            $this->differ->compare($oldUI, $newUI);

        $container = $screen->getContainer();
        if (! $reload && $container !== null) {
            foreach ($this->collectDirtyComponentChanges($container) as $componentId => $dirtyProps) {
                if (! isset($newUI[$componentId])) {
                    continue;
                }
                if (($newUI[$componentId]['parent'] ?? null) === null) {
                    continue;
                }
                foreach ($dirtyProps as $propKey => $propValue) {
                    if (array_key_exists($propKey, $newUI[$componentId])) {
                        $diff[$componentId][$propKey] = $newUI[$componentId][$propKey];
                    } else {
                        $diff[$componentId][$propKey] = $propValue;
                    }
                }
            }
        }

        $result = [];
        foreach ($diff as $componentId => $changes) {
            // Always include 'type' from newUI so frontend knows how to handle the change
            if (isset($newUI[$componentId]['type'])) {
                $changes['type'] = $newUI[$componentId]['type'];
            }

            $result[$componentId] = $changes;
        }

        return $result;
    }

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
        array $buildParams = [],
        int|string|null $slot = null
    ): void {
        $parentId = $parent->getId();

        $removedElements = [];
        if ($slot !== null) {
            $removedElements = $parent->clearSlot($slot);
        }

        $instance = Screen::make($screenClass);
        $instance->setParent($parentId);

        $prevIncomingStorage = Screen::getCurrentIncomingStorage();
        $prevQueryParams = Screen::getCurrentQueryParams();

        $this->idGenerator->pushCurrentContext($instance->getContextIdentifier());
        try {
            $shouldReset = (bool) (Screen::getCurrentQueryParams()['reset'] ?? request()->query('reset', false));
            if ($shouldReset) {
                $instance->onResetScreen();
            }

            if (! empty($buildParams) && AbstractLayout::getActiveHostScreen() !== $instance::class) {
                $instance->clearCachedScreenSnapshot();
            }

            $normalizedBuildParams = Screen::normalizeParamsForUrl($buildParams);
            $effectiveQueryParams = array_merge(Screen::getCurrentQueryParams(), $normalizedBuildParams);

            $this->initializeEventContext(
                screen: $instance,
                incomingStorage: Screen::getCurrentIncomingStorage(),
                queryParams: $effectiveQueryParams,
                parent: $parentId,
                buildParams: $buildParams
            );
            $instance->callPostLoadUI($buildParams);

            $container = $instance->getContainer();
            if ($container !== null) {
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

                // If embedding a host Screen into an AbstractLayout's main content slot,
                // resolve declarative portal projections BEFORE serializing its JSON.
                $activeLayout = AbstractLayout::current();
                $isMainLayoutHost = $activeLayout instanceof AbstractLayout
                    && ! ($instance instanceof LayoutInterface)
                    && $activeLayout->isMainSlotContainer($parent);
                $isInitialLayoutRender = $isMainLayoutHost
                    && AbstractLayout::getActiveHostScreen() === $instance::class;

                if ($isMainLayoutHost) {
                    $activeLayout->prepareScreenPortals($instance, $container, $isInitialLayoutRender);
                }

                $childOrder = $parent->getChildOrder($container->getId());
                $embeddedJson = $container->toJson($childOrder);

                // Store the child screen's own snapshot with root=true and parent=$parentId
                // so direct events on the child screen can reconstruct its tree (including its slot/grid config).
                $container->root(true);
                $this->cacheScreenSnapshot($instance, $container);
                $container->root(false);

                foreach ($removedElements as $removed) {
                    if ($removed->getId() !== $container->getId()) {
                        $this->changesCollector->add([
                            $removed->getId() => [
                                'type' => $removed->getType(),
                                'parent' => null,
                            ],
                        ]);
                    }
                }

                $this->changesCollector->add($embeddedJson);

                if ($isMainLayoutHost) {
                    $activeLayout->mountDeclaredSlotScreens($container, $isInitialLayoutRender);
                }
            }

            $this->changesCollector->setStorage($instance->getStorageVariables());
        } finally {
            Screen::setCurrentIncomingStorage($prevIncomingStorage);
            Screen::setCurrentQueryParams($prevQueryParams);
            $this->idGenerator->popCurrentContext();
        }
    }

    /**
     * Resolve portal fallbacks for standalone screens rendered without an AbstractLayout.
     */
    protected function applyStandalonePortalFallbacks(Container $screenRoot): void
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
                $parentId = $portal->getParent();
                $parentContainer = is_int($parentId) ? $screenRoot->findById($parentId) : null;
                if ($parentContainer instanceof Container) {
                    $parentContainer->moveChildToStart($portal->getId());
                } else {
                    $screenRoot->moveChildToStart($portal->getId());
                }
            }
        }
    }

    /**
     * Hydrate live component instances with the values currently held in the client DOM
     * before capturing the $oldUI snapshot.
     *
     * @param  array<string, mixed>  $eventParameters
     */
    protected function hydrateClientComponentState(Container $container, array $eventParameters, ?int $triggerComponentId = null): void
    {
        if ($triggerComponentId !== null) {
            $triggerElement = $container->findById($triggerComponentId);
            if ($triggerElement instanceof UIComponent) {
                $type = $triggerElement->getType();
                if (in_array($type, ['input', 'textarea', 'rich_text', 'select'], true) && array_key_exists('value', $eventParameters)) {
                    $val = $eventParameters['value'];
                    if (is_scalar($val) || $val === null) {
                        $triggerElement->syncClientConfig('value', $val ?? '');
                    }
                } elseif ($type === 'checkbox' && array_key_exists('checked', $eventParameters)) {
                    $triggerElement->syncClientConfig('checked', (bool) $eventParameters['checked']);
                } elseif (in_array($type, ['item_list', 'itemlist'], true) && array_key_exists('selected', $eventParameters)) {
                    $selectedVal = $eventParameters['selected'];
                    $normalizedSelected = is_array($selectedVal)
                        ? array_values(array_filter($selectedVal, static fn (mixed $v): bool => is_int($v) || is_string($v)))
                        : ((is_int($selectedVal) || is_string($selectedVal)) && $selectedVal !== '' ? [$selectedVal] : []);
                    $triggerElement->syncClientConfig('selected_items', $normalizedSelected);
                }
            }
        }

        $reservedKeys = ['value', 'checked', 'name', '_caller_screen_id', '_caller_service_id'];

        foreach ($eventParameters as $paramKey => $paramValue) {
            if ($paramKey === '' || in_array($paramKey, $reservedKeys, true)) {
                continue;
            }

            $element = $container->findByName($paramKey);
            if (! ($element instanceof UIComponent)) {
                continue;
            }

            $type = $element->getType();
            if (in_array($type, ['input', 'textarea', 'rich_text', 'select'], true)) {
                if (is_scalar($paramValue) || $paramValue === null) {
                    $element->syncClientConfig('value', $paramValue ?? '');
                }
            } elseif ($type === 'checkbox') {
                if (is_bool($paramValue)) {
                    $element->syncClientConfig('checked', $paramValue);
                } elseif (is_array($paramValue)) {
                    $element->syncClientConfig('selected_values', $paramValue);
                }
            } elseif (in_array($type, ['item_list', 'itemlist'], true)) {
                $normalizedSelected = is_array($paramValue)
                    ? array_values(array_filter($paramValue, static fn (mixed $v): bool => is_int($v) || is_string($v)))
                    : ((is_int($paramValue) || is_string($paramValue)) && $paramValue !== '' ? [$paramValue] : []);
                $element->syncClientConfig('selected_items', $normalizedSelected);
            }
        }
    }

    /**
     * Reset dirty tracking across all leaf components in a container tree.
     */
    protected function clearContainerDirtyState(Container $container): void
    {
        foreach ($container->getChildren() as $child) {
            if ($child instanceof CardGrid) {
                $child->clearDirtyKeys();
                foreach ($child->getCards() as $card) {
                    $card->clearDirtyKeys();
                }
            } elseif ($child instanceof UIComponent) {
                $child->clearDirtyKeys();
            } elseif ($child instanceof Container) {
                $this->clearContainerDirtyState($child);
            }
        }
    }

    /**
     * Inject component references into protected properties.
     *
     * Uses reflection to find protected properties with UI component type hints.
     * If a property name matches a component name in the container,
     * the component is injected into that property.
     */
    protected function injectComponentReferences(Screen $screen, Container $container): void
    {
        $reflection = new ReflectionClass($screen);

        foreach ($reflection->getProperties(ReflectionProperty::IS_PROTECTED) as $property) {
            // Skip properties declared in Screen base class
            if ($property->getDeclaringClass()->getName() === Screen::class) {
                continue;
            }

            $propertyType = $property->getType();
            if (! $propertyType) {
                continue;
            }

            if ($propertyType instanceof ReflectionNamedType && $propertyType->isBuiltin()) {
                continue;
            }

            if (! ($propertyType instanceof ReflectionNamedType)) {
                continue;
            }

            $typeName = $propertyType->getName();

            // Only process UI component types implementing UIElement or from the package namespace.
            if (str_starts_with($typeName, 'Idei\\Usim\\Components\\') || is_subclass_of($typeName, UIElement::class)) {
                $componentName = $property->getName();
                $component = $container->findByName($componentName)
                    ?? $container->findByName(Str::snake($componentName));

                if ($component) {
                    $property->setValue($screen, $component);
                } elseif (! $propertyType->allowsNull()) {
                    $className = $screen::class;
                    throw new RuntimeException(
                        "Component '{$componentName}' not found in {$className}. " .
                            "Make sure the component exists or make the property nullable: protected ?{$typeName} \${$componentName};"
                    );
                }
            }
        }
    }

    /**
     * Collect properties explicitly mutated via setConfig() during the current event lifecycle.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function collectDirtyComponentChanges(Container $container): array
    {
        $dirtyChanges = [];
        $ignoredKeys = ['type', 'parent', '_order', '_storage_path', '_image_placeholder', 'static_items'];

        foreach ($container->getChildren() as $child) {
            if ($child instanceof CardGrid) {
                $elementsToInspect = [$child, ...$child->getCards()];
                foreach ($elementsToInspect as $el) {
                    $dirtyKeys = $el->getDirtyKeys();
                    if (! empty($dirtyKeys)) {
                        $id = $el->getId();
                        foreach ($dirtyKeys as $key) {
                            if (in_array($key, $ignoredKeys, true)) {
                                continue;
                            }
                            $dirtyChanges[$id][$key] = $el->get($key);
                        }
                    }
                }
            } elseif ($child instanceof UIComponent) {
                $dirtyKeys = $child->getDirtyKeys();
                if (! empty($dirtyKeys)) {
                    $id = $child->getId();
                    foreach ($dirtyKeys as $key) {
                        if (in_array($key, $ignoredKeys, true)) {
                            continue;
                        }
                        $dirtyChanges[$id][$key] = $child->get($key);
                    }
                }
            } elseif ($child instanceof Container) {
                foreach ($this->collectDirtyComponentChanges($child) as $id => $props) {
                    $dirtyChanges[$id] = array_merge($dirtyChanges[$id] ?? [], $props);
                }
            }
        }

        return $dirtyChanges;
    }

    /**
     * Reconstruct UI container from JSON array.
     *
     * @param  array<int|string, array<string, mixed>>  $jsonUI  JSON representation of UI
     * @return Container Reconstructed container
     */
    protected function reconstructContainerFromJson(array $jsonUI, string $contextKey): Container
    {
        /** @var array<int|string, UIElement> $components */
        $components = [];
        $rootContainer = null;

        // First pass: instantiate all components
        foreach ($jsonUI as $id => $component) {
            $type = $component['type'] ?? null;
            if (! \is_string($type) || $type === '') {
                throw new RuntimeException('Unknown component type.');
            }

            $className = $this->mapTypeToClass($type);
            if (! $className) {
                throw new RuntimeException("Unknown component type '{$type}'.");
            }

            $numericId = (int) $id;

            // Reserve IDs from cached snapshots so future auto-generated IDs
            // in this request do not collide with already deserialized components.
            if (is_numeric($id)) {
                $this->idGenerator->reserveContextId($contextKey, $numericId);
            }

            $components[$id] = $className::deserialize($numericId, $component);
        }

        // Second pass: set up parent-child relationships
        foreach ($components as $id => $component) {
            $parentId = $jsonUI[$id]['parent'] ?? null;

            if ($component instanceof Container && $component->isRoot()) {
                $rootContainer = $component;
            }

            // Detached components (parent=null) are valid during incremental remove operations.
            if ($parentId === null || $parentId === '') {
                continue;
            }

            if (! \is_int($parentId) && ! \is_string($parentId)) {
                continue;
            }

            if (! $parentId || ! isset($components[$parentId])) {
                continue;
            }

            $components[$parentId]->connectChild($component);
        }

        // Third pass: post-connection initialization
        foreach ($components as $component) {
            $component->postConnect();
        }

        if (! $rootContainer) {
            throw new RuntimeException('No root container found in UI JSON.');
        }

        return $rootContainer;
    }

    /**
     * Map component type string to corresponding PHP class.
     *
     * @return class-string<UIElement>|null
     */
    protected function mapTypeToClass(string $type): ?string
    {
        return match ($type) {
            'label' => Label::class,
            'newlabel' => NewLabel::class,
            'button' => Button::class,
            'input' => Input::class,
            'select' => Select::class,
            'checkbox' => Checkbox::class,
            'card' => Card::class,
            'image' => Image::class,
            'table' => Table::class,
            'container' => Container::class,
            'tablerow' => TableRow::class,
            'tablecell' => TableCell::class,
            'tableheadercell' => TableHeaderCell::class,
            'form' => Form::class,
            'tableheaderrow' => TableHeaderRow::class,
            'menudropdown' => MenuDropdown::class,
            'navtree', 'nav_tree' => NavTree::class,
            'uploader' => Uploader::class,
            'calendar' => Calendar::class,
            'carousel' => Carousel::class,
            'textarea' => Textarea::class,
            'rich_text', 'richtext' => RichText::class,
            'split' => Split::class,
            'timer' => Timer::class,
            'item_list', 'itemlist' => ItemList::class,
            'item_card', 'itemcard' => ItemCard::class,
            'card_grid', 'cardgrid' => CardGrid::class,
            default => null,
        };
    }

    /**
     * @phpstan-assert-if-true array<int|string, array<string, mixed>> $ui
     */
    protected function isTypedCachedScreenSnapshot(mixed $ui): bool
    {
        if (! \is_array($ui)) {
            return false;
        }

        foreach ($ui as $component) {
            if (! \is_array($component)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Cached snapshots can become stale after structural screen changes.
     * Reject snapshots that reference component parents not present in the payload
     * or table internals that no longer form a consistent subtree.
     *
     * @param  array<int|string, array<string, mixed>>  $ui
     */
    protected function isValidCachedScreenSnapshot(array $ui): bool
    {
        foreach ($ui as $componentId => $component) {
            $parent = $component['parent'] ?? null;
            $isRoot = (bool) ($component['root'] ?? false);
            if (! $isRoot && \is_int($parent) && ! isset($ui[$parent]) && ! isset($ui[(string) $parent])) {
                return false;
            }

            if (($component['type'] ?? null) !== 'table') {
                continue;
            }

            $rowsContainerId = $component['rows_container'] ?? null;
            $headerRowId = $component['header_row'] ?? null;

            if (! \is_int($rowsContainerId) || ! isset($ui[$rowsContainerId])) {
                return false;
            }

            if (($ui[$rowsContainerId]['parent'] ?? null) !== $componentId) {
                return false;
            }

            if (! \is_int($headerRowId) || ! isset($ui[$headerRowId])) {
                return false;
            }

            if (($ui[$headerRowId]['parent'] ?? null) !== $componentId) {
                return false;
            }
        }

        return true;
    }
}
