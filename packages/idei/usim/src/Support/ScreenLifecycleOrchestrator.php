<?php

declare(strict_types=1);

namespace Idei\Usim\Support;

use Idei\Usim\Components\Container;
use Idei\Usim\Contracts\ComponentIdGeneratorInterface;
use Idei\Usim\Contracts\ScreenLifecycleOrchestratorInterface;
use Idei\Usim\Contracts\UIDifferInterface;
use Idei\Usim\Contracts\UIStateRepositoryInterface;
use Idei\Usim\Screen;
use Idei\Usim\Support\Lifecycle\ComponentTreeStateSynchronizer;
use Idei\Usim\Support\Lifecycle\EmbeddedScreenMounter;
use Idei\Usim\Support\Lifecycle\LayoutRenderCoordinator;
use Idei\Usim\Support\Lifecycle\ScreenHandlerDispatcher;
use Idei\Usim\Support\Lifecycle\ScreenSnapshotManager;
use Idei\Usim\UIChangesCollector;
use Throwable;

/**
 * Orchestrates the full lifecycle of a Screen:
 * - Request / render initialization and layout wrapping
 * - Event action and read-only query dispatching
 * - Component tree reconstruction and snapshot caching
 * - Component dirty tracking and diff calculation
 * - Nested screen embedding
 */
class ScreenLifecycleOrchestrator implements ScreenLifecycleOrchestratorInterface
{
    protected UsimConfig $usimConfig;

    protected MethodParameterResolver $parameterResolver;

    private readonly LayoutRenderCoordinator $layoutRenderer;

    private readonly ScreenSnapshotManager $snapshotManager;

    private readonly ComponentTreeStateSynchronizer $stateSynchronizer;

    private readonly ScreenHandlerDispatcher $handlerDispatcher;

    private readonly EmbeddedScreenMounter $embeddedScreenMounter;

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
        $this->layoutRenderer = new LayoutRenderCoordinator($this->idGenerator, $this->usimConfig);
        $this->snapshotManager = new ScreenSnapshotManager($this->stateRepository, $this->idGenerator);
        $this->stateSynchronizer = new ComponentTreeStateSynchronizer($this->differ);
        $this->handlerDispatcher = new ScreenHandlerDispatcher($this->parameterResolver);
        $this->embeddedScreenMounter = new EmbeddedScreenMounter($this->idGenerator, $this->changesCollector);
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

            if ($this->layoutRenderer->renderWithLayoutIfApplicable(
                screen: $screen,
                incomingStorage: $incomingStorage,
                queryParams: $queryParams,
                parent: $parent,
                shouldReset: $shouldReset,
                buildParams: $buildParams,
                orchestrator: $this,
            )) {
                return;
            }

            $this->initializeEventContext(
                screen: $screen,
                incomingStorage: $incomingStorage,
                queryParams: $queryParams,
                parent: $parent,
                buildParams: $buildParams
            );

            $this->layoutRenderer->applyStandalonePortalFallbacksIfApplicable($screen, $parent);

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

            $this->handlerDispatcher->dispatchAction(
                screen: $screen,
                method: $method,
                parameters: $parameters,
                queryParams: $queryParams,
                incomingStorage: $incomingStorage,
                triggerComponentId: $triggerComponentId
            );

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
                $this->stateSynchronizer->clearDirtyState($container);
            }

            $result = $this->handlerDispatcher->dispatchQuery(
                screen: $screen,
                method: $method,
                parameters: $parameters,
                queryParams: $queryParams,
                incomingStorage: $incomingStorage,
                triggerComponentId: $triggerComponentId
            );

            if ($container !== null) {
                $this->handlerDispatcher->assertNoReadOnlyMutations(
                    $screen,
                    $method,
                    $this->stateSynchronizer->collectDirtyChanges($container)
                );
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

            return $this->handlerDispatcher->supportsComponentHandler($container, $componentId, $action, $queryOnly);
        } catch (Throwable) {
            return false;
        } finally {
            $this->idGenerator->popCurrentContext();
        }
    }

    /**
     * Initialize event context.
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
            $this->stateSynchronizer->hydrateClientState($container, $eventParameters, $triggerComponentId);
        }

        $this->stateSynchronizer->clearDirtyState($container);
        $screen->setOldUI($container->toJson());

        // Inject component references into protected properties
        $this->stateSynchronizer->injectComponentReferences($screen, $container);
    }

    /**
     * Finalize event context.
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
        return $this->snapshotManager->reconstructTree($screen, ...$params);
    }

    /**
     * Get stored UI state from cache, regenerating it if missing or invalid.
     *
     * @param  mixed  ...$params  Optional parameters passed to buildBaseUI
     * @return array<int|string, array<string, mixed>> UI structure in JSON format
     */
    public function getCachedScreenSnapshot(Screen $screen, mixed ...$params): array
    {
        return $this->snapshotManager->getCachedSnapshot($screen, ...$params);
    }

    /**
     * Store UI state snapshot and internal state in repository cache.
     */
    public function cacheScreenSnapshot(Screen $screen, Container $container): void
    {
        $this->snapshotManager->cacheSnapshot($screen, $container);
    }

    /**
     * Clear the cached screen snapshot and state variables.
     */
    public function clearCachedScreenSnapshot(Screen $screen): bool
    {
        return $this->snapshotManager->clearCachedSnapshot($screen);
    }

    /**
     * Build diff response in indexed format.
     *
     * @return array<int|string, array<string, mixed>> Indexed diff response
     */
    public function buildDiffResponse(Screen $screen, bool $reload = false): array
    {
        return $this->stateSynchronizer->buildDiffResponse($screen, $reload);
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
        $this->embeddedScreenMounter->embedInto($screenClass, $parent, $buildParams, $slot, $this);
    }
}
