<?php

namespace Idei\Usim;

use Idei\Usim\Components\Container;
use Idei\Usim\Concerns\HandlesAuthorization;
use Idei\Usim\Concerns\HandlesModals;
use Idei\Usim\Concerns\HandlesNavigation;
use Idei\Usim\Concerns\HandlesScreenProperties;
use Idei\Usim\Contracts\ScreenLifecycleOrchestratorInterface;
use Idei\Usim\Contracts\UIElement;
use Idei\Usim\Enums\Visibility;
use Idei\Usim\Layout\AbstractLayout;
use Idei\Usim\Support\UIIdGenerator;
use Idei\Usim\Support\UIStateManager;
use Idei\Usim\Support\UsimConfig;
use Illuminate\Support\Str;
use ReflectionClass;
use RuntimeException;

/**
 * Abstract user Interface Service
 *
 * Base class for all user Interface services that handles:
 * - user Interface state storage and retrieval
 * - Automatic diff calculation
 * - Event lifecycle management
 * - response formatting
 *
 * Child classes only need to:
 * 1. Implement buildBaseUI() to define the component structure
 * 2. Implement event handlers that modify components (no return needed)
 *
 * The lifecycle is managed by UIEventController:
 * - initializeEventContext() - Called before event handler
 * - onEventHandler($params) - Your event handler
 * - finalizeEventContext() - Called after event handler, returns formatted response
 */
abstract class Screen
{
    use HandlesAuthorization;
    use HandlesModals;
    use HandlesNavigation;
    use HandlesScreenProperties;

    /**
     * Current container instance
     */
    protected Container $container;

    /**
     * State before modifications (for diff calculation)
     *
     * @var array<int|string, array<string, mixed>>|null
     */
    protected ?array $oldUI = null;

    /**
     * State after modifications (for diff calculation)
     *
     * @var array<int|string, array<string, mixed>>|null
     */
    protected ?array $newUI = null;

    /**
     * Query parameters from the request
     *
     * @var array<string, mixed>
     */
    protected array $queryParams = [];

    /**
     * Incoming storage from the request
     *
     * @var array<string, mixed>
     */
    protected array $incomingStorage = [];

    /**
     * Active incoming storage for nested screen embedding
     *
     * @var array<string, mixed>
     */
    protected static array $currentIncomingStorage = [];

    /**
     * Active query parameters for nested screen embedding
     *
     * @var array<string, mixed>
     */
    protected static array $currentQueryParams = [];

    /**
     * Parent context for this screen (used for nested screens)
     */
    public protected(set) int|string|null $parent = 'main';

    /**
     * Component ID of the caller screen if this screen was opened as a modal or child.
     */
    protected ?int $callerScreenId = null;

    /**
     * Class of the caller screen if this screen was opened as a modal or child.
     *
     * @var class-string<Screen>|null
     */
    protected ?string $callerScreenClass = null;

    /**
     * Registered callback action method to trigger on caller screen when this modal finishes.
     */
    protected ?string $callbackAction = null;

    /**
     * Stack / layer index when screen is displayed as a stacked modal.
     */
    public int $modalLayerIndex = 0;

    /**
     * Get unique context identifier for this screen instance.
     */
    public function getContextIdentifier(): string
    {
        if ($this->modalLayerIndex > 0) {
            return static::class.'@'.$this->modalLayerIndex;
        }

        return static::class;
    }

    /**
     * Screen visibility level. Used by the framework to determine access and menu display.
     */
    public static Visibility $visibility = Visibility::AUTHENTICATED;

    /**
     * Authentication guard required for this screen ('web', 'device', etc.).
     * If null, it is automatically resolved by getAuthGuard().
     */
    public static ?string $guard = null;

    /**
     * Layout class to frame this screen.
     * Can be class-string<\Idei\Usim\Layout\AbstractLayout>, 'default', or null for kiosk/standalone.
     *
     * @var class-string<AbstractLayout>|string|null
     */
    public static ?string $layout = 'default';

    /**
     * Default layout slot where this screen is mounted (e.g. 'main', 'center', 'right_panel').
     */
    public static string $defaultSlot = 'main';

    /**
     * Custom menu screen class to embed in the layout.
     * If null, the layout's default menu is used.
     *
     * @var class-string<Screen>|string|null
     */
    public static ?string $menuScreen = null;

    /**
     * Layout instance currently applied to this screen, if any.
     */
    protected ?AbstractLayout $layoutInstance = null;

    /**
     * Resolve the authentication guard for this screen.
     */
    public static function getAuthGuard(): string
    {
        if (static::$guard !== null) {
            return static::$guard;
        }

        // Screens under the Device namespace default to the 'device' guard
        if (str_contains(static::class, 'Screens\\Device\\')) {
            return 'device';
        }

        return 'web';
    }

    /**
     * Resolve the layout class for this screen.
     *
     * @return class-string<AbstractLayout>|null
     */
    public static function getLayoutClass(): ?string
    {
        if (static::$layout === null) {
            return null;
        }

        /** @var UsimConfig $usimConfig */
        $usimConfig = app(UsimConfig::class);
        $layoutClass = static::$layout === 'default' ? $usimConfig->defaultLayout : static::$layout;

        if (class_exists($layoutClass) && is_subclass_of($layoutClass, AbstractLayout::class)) {
            /** @var class-string<AbstractLayout> $layoutClass */
            return $layoutClass;
        }

        return null;
    }

    /**
     * Get the active layout instance associated with this screen or request context.
     */
    public function getLayout(): ?AbstractLayout
    {
        if ($this->layoutInstance !== null) {
            return $this->layoutInstance;
        }

        if (AbstractLayout::current() !== null) {
            return AbstractLayout::current();
        }

        if (app()->bound(AbstractLayout::class)) {
            /** @var AbstractLayout $layout */
            $layout = app(AbstractLayout::class);

            return $layout;
        }

        $layoutClass = null;
        $currentScreenClass = UIStateManager::getClientCurrentScreenClass();
        if ($currentScreenClass !== null && class_exists($currentScreenClass) && is_subclass_of($currentScreenClass, self::class)) {
            $layoutClass = $currentScreenClass::getLayoutClass();
        }

        if ($layoutClass === null) {
            /** @var UsimConfig $usimConfig */
            $usimConfig = app(UsimConfig::class);
            $defaultLayout = $usimConfig->defaultLayout;
            if (class_exists($defaultLayout) && is_subclass_of($defaultLayout, AbstractLayout::class)) {
                /** @var class-string<AbstractLayout> $defaultLayout */
                $layoutClass = $defaultLayout;
            }
        }

        if ($layoutClass !== null && class_exists($layoutClass) && is_subclass_of($layoutClass, AbstractLayout::class)) {
            /** @var AbstractLayout $layout */
            $layout = app($layoutClass);
            $layout->initializeEventContext();
            AbstractLayout::setCurrent($layout);

            return $layout;
        }

        return null;
    }

    /**
     * Resolve the menu screen class for this screen.
     *
     * @return class-string<Screen>|null
     */
    public static function getMenuScreen(): ?string
    {
        if (static::$menuScreen !== null) {
            if (class_exists(static::$menuScreen) && is_subclass_of(static::$menuScreen, self::class)) {
                return static::$menuScreen;
            }

            $resolved = static::resolveScreenClassFromSlug(static::$menuScreen);
            if ($resolved !== null && class_exists($resolved) && is_subclass_of($resolved, self::class)) {
                return $resolved;
            }
        }

        /** @var UsimConfig $usimConfig */
        $usimConfig = app(UsimConfig::class);
        $defaultMenu = $usimConfig->defaultMenuScreen;

        if (class_exists($defaultMenu) && is_subclass_of($defaultMenu, self::class)) {
            return $defaultMenu;
        }

        return null;
    }

    /**
     * Resolve a screen class from a route slug (e.g. 'admin/users-manager' -> 'App\UI\Screens\Admin\UsersManager').
     *
     * @return class-string<Screen>|null
     */
    public static function resolveScreenClassFromSlug(string $screenRoute): ?string
    {
        /** @var UsimConfig $usimConfig */
        $usimConfig = app(UsimConfig::class);

        return $usimConfig->resolveScreenClass($screenRoute);
    }

    /**
     * Resolve a screen slug from a class-string (e.g. 'App\UI\Screens\Admin\UsersManager' -> 'admin/users-manager').
     */
    public static function resolveScreenSlug(string $screenClass): string
    {
        /** @var UsimConfig $usimConfig */
        $usimConfig = app(UsimConfig::class);

        return $usimConfig->resolveScreenSlug($screenClass);
    }

    /**
     * Determine if this screen should display a layout.
     */
    public static function hasLayout(): bool
    {
        return static::getLayoutClass() !== null;
    }

    /**
     * Get the default layout slot for this screen.
     */
    public static function getDefaultSlot(): string
    {
        return static::$defaultSlot;
    }

    /**
     * Get the layout instance applied to this screen, if any.
     */
    public function getLayoutInstance(): ?AbstractLayout
    {
        return $this->layoutInstance;
    }

    protected function uiChanges(): UIChangesCollector
    {
        return app(UIChangesCollector::class);
    }

    /**
     * Resolve and validate a Screen instance from the service container.
     *
     * @param  class-string<Screen>|string|null  $screenClass
     *
     * @throws RuntimeException If the class does not exist or is not an instantiable Screen.
     */
    public static function make(?string $screenClass = null): Screen
    {
        $targetClass = $screenClass ?? static::class;

        if (! class_exists($targetClass) || ! is_subclass_of($targetClass, self::class)) {
            throw new RuntimeException("Resolved screen [{$targetClass}] is not a valid Screen instance.");
        }

        $reflection = new ReflectionClass($targetClass);
        if ($reflection->isAbstract()) {
            throw new RuntimeException("Cannot instantiate abstract screen [{$targetClass}].");
        }

        /** @var static $screen */
        $screen = app($targetClass);

        return $screen;
    }

    // Modal management and authorization methods are provided by HandlesModals and HandlesAuthorization traits.

    /**
     * Get the menu label for this screen.
     * Defaults to the class name (spaced and capitalized).
     * Override this in child classes to customize.
     */
    public static function getMenuLabel(): string
    {
        return t('screen.'.static::getScreenSlug().'.menu_title');
    }

    /**
     * Get the menu icon for this screen.
     * Override this in child classes to customize.
     */
    public static function getMenuIcon(): ?string
    {
        return t('screen.'.static::getScreenSlug().'.icon');
    }

    /**
     * Get the route path for this screen.
     * Auto-generates based on namespace location relative to Screen root.
     * E.g. App\UI\Screens\Admin\UsersManager -> /admin/dashboard
     */
    public static function getRoutePath(): string
    {
        $class = static::class;
        $prefixConfig = config('usim.screens_namespace', 'App\\UI\\Screens');
        $prefix = \is_string($prefixConfig) ? $prefixConfig : 'App\\UI\\Screens';

        if (str_starts_with($class, $prefix)) {
            $relative = substr($class, strlen($prefix));
            $segments = explode('\\', trim($relative, '\\'));
            $urlSegments = array_map(fn ($s) => Str::kebab($s), $segments);

            return '/'.implode('/', $urlSegments);
        }

        return '/';
    }

    /**
     * Build base user Interface structure
     *
     * Override this method in your service to define the base user Interface.
     * This will be called automatically if the cache expires.
     *
     * @param  mixed  ...$params  Optional parameters for user Interface construction
     */
    abstract protected function buildBaseUI(Container $container, ...$params): void;

    protected function postLoadUI(): void {}

    /**
     * Get the lifecycle orchestrator instance for this screen.
     */
    public static function getLifecycleOrchestrator(): ScreenLifecycleOrchestratorInterface
    {
        return app(ScreenLifecycleOrchestratorInterface::class);
    }

    public function getContainer(): ?Container
    {
        return $this->container ?? null;
    }

    public function setContainer(Container $container): static
    {
        $this->container = $container;

        return $this;
    }

    public function hasContainer(): bool
    {
        return isset($this->container);
    }

    /**
     * @return array<int|string, array<string, mixed>>|null
     */
    public function getOldUI(): ?array
    {
        return $this->oldUI;
    }

    /**
     * @param  array<int|string, array<string, mixed>>|null  $oldUI
     */
    public function setOldUI(?array $oldUI): static
    {
        $this->oldUI = $oldUI;

        return $this;
    }

    /**
     * @return array<int|string, array<string, mixed>>|null
     */
    public function getNewUI(): ?array
    {
        return $this->newUI;
    }

    /**
     * @param  array<int|string, array<string, mixed>>|null  $newUI
     */
    public function setNewUI(?array $newUI): static
    {
        $this->newUI = $newUI;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getIncomingStorage(): array
    {
        return $this->incomingStorage;
    }

    /**
     * @param  array<string, mixed>  $incomingStorage
     */
    public function setIncomingStorage(array $incomingStorage): static
    {
        $this->incomingStorage = $incomingStorage;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    /**
     * @param  array<string, mixed>  $queryParams
     */
    public function setQueryParams(array $queryParams): static
    {
        $this->queryParams = $queryParams;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public static function getCurrentIncomingStorage(): array
    {
        return self::$currentIncomingStorage;
    }

    /**
     * @param  array<string, mixed>  $storage
     */
    public static function setCurrentIncomingStorage(array $storage): void
    {
        self::$currentIncomingStorage = $storage;
    }

    /**
     * @return array<string, mixed>
     */
    public static function getCurrentQueryParams(): array
    {
        return self::$currentQueryParams;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public static function setCurrentQueryParams(array $params): void
    {
        self::$currentQueryParams = $params;
    }

    public function setParent(int|string|null $parent): static
    {
        $this->parent = $parent;

        return $this;
    }

    public function setLayoutInstance(?AbstractLayout $layoutInstance): static
    {
        $this->layoutInstance = $layoutInstance;

        return $this;
    }

    public function callBuildBaseUI(Container $container, mixed ...$params): void
    {
        $this->buildBaseUI($container, ...$params);
    }

    public function callPostLoadUI(): void
    {
        $this->postLoadUI();
    }

    /**
     * @return array<int|string, array<string, mixed>>
     */
    public function callBuildDiffResponse(bool $reload = false): array
    {
        return $this->buildDiffResponse($reload);
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
        array $incomingStorage = [],
        array $queryParams = [],
        int|string|null $parent = 'main',
        bool $shouldReset = false,
        array $buildParams = []
    ): void {
        static::getLifecycleOrchestrator()->render(
            screen: $this,
            incomingStorage: $incomingStorage,
            queryParams: $queryParams,
            parent: $parent,
            shouldReset: $shouldReset,
            buildParams: $buildParams
        );
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
        string $method,
        array $parameters = [],
        array $incomingStorage = [],
        array $queryParams = [],
        int|string|null $parent = null,
        ?int $triggerComponentId = null
    ): void {
        static::getLifecycleOrchestrator()->handleAction(
            screen: $this,
            method: $method,
            parameters: $parameters,
            incomingStorage: $incomingStorage,
            queryParams: $queryParams,
            parent: $parent,
            triggerComponentId: $triggerComponentId
        );
    }

    /**
     * Initialize event context
     *
     * Called by UIEventController before invoking event handler.
     *
     * @param  array<string, mixed>  $incomingStorage  Storage data from frontend (decrypted)
     * @param  array<string, mixed>  $queryParams  Query parameters from frontend
     * @param  int|string|null  $parent  The parent screen/container ID (used for nested screens)
     * @param  array<string, mixed>  $eventParameters  Parameters sent with the UI event
     * @param  int|null  $triggerComponentId  ID of the component that triggered the event
     * @param  array<int|string, mixed>  $buildParams  Parameters passed to buildBaseUI if regenerating cache
     */
    public function initializeEventContext(
        array $incomingStorage = [],
        array $queryParams = [],
        int|string|null $parent = null,
        array $eventParameters = [],
        ?int $triggerComponentId = null,
        array $buildParams = []
    ): void {
        static::getLifecycleOrchestrator()->initializeEventContext(
            screen: $this,
            incomingStorage: $incomingStorage,
            queryParams: $queryParams,
            parent: $parent,
            eventParameters: $eventParameters,
            triggerComponentId: $triggerComponentId,
            buildParams: $buildParams
        );
    }

    /**
     * Finalize event context
     *
     * Called by UIEventController after event handler completes.
     */
    public function finalizeEventContext(bool $reload = false): void
    {
        static::getLifecycleOrchestrator()->finalizeEventContext(
            screen: $this,
            reload: $reload
        );
    }

    /**
     * Build and attach a nested screen inside a parent container.
     *
     * @param  class-string<Screen>  $class
     */
    public static function embedInto(string $class, Container $parent): void
    {
        static::getLifecycleOrchestrator()->embedInto($class, $parent);
    }

    /**
     * Store UI state in cache.
     *
     * @param  Container  $container  UI container to store
     */
    protected function cacheScreenSnapshot(Container $container): void
    {
        static::getLifecycleOrchestrator()->cacheScreenSnapshot($this, $container);
    }

    /**
     * Get stored user Interface state, regenerate if missing.
     *
     * @param  mixed  ...$params  Optional parameters passed to buildBaseUI
     * @return array<int|string, array<string, mixed>> user Interface structure in JSON format
     */
    protected function getCachedScreenSnapshot(...$params): array
    {
        return static::getLifecycleOrchestrator()->getCachedScreenSnapshot($this, ...$params);
    }

    /**
     * Reconstruct the current screen component tree from cache.
     *
     * @param  mixed  ...$params  Optional parameters passed to buildBaseUI if regenerating cache
     */
    protected function reconstructScreenTreeFromCache(...$params): Container
    {
        return static::getLifecycleOrchestrator()->reconstructScreenTree($this, ...$params);
    }

    /**
     * Build diff response in indexed format.
     *
     * @return array<int|string, array<string, mixed>> Indexed diff response
     */
    protected function buildDiffResponse(bool $reload = false): array
    {
        return static::getLifecycleOrchestrator()->buildDiffResponse($this, $reload);
    }

    // Navigation and slot routing methods are provided by HandlesNavigation trait.

    /**
     * Clear the cached screen snapshot.
     */
    public function clearCachedScreenSnapshot(): bool
    {
        return static::getLifecycleOrchestrator()->clearCachedScreenSnapshot($this);
    }

    /**
     * Allow child classes to react when the screen is reset.
     */
    public function onResetScreen(): void
    {
        $this->clearCachedScreenSnapshot();
    }

    /**
     * Get the component ID of the current screen root container.
     *
     * Used for modal callbacks to route events back to this screen.
     *
     * @return int Screen component ID
     */
    protected function getScreenComponentId(): int
    {
        if (isset($this->container)) {
            return $this->container->getId();
        }

        $ui = $this->getCachedScreenSnapshot();

        // Find the first container (main container that represents the screen)
        foreach ($ui as $id => $component) {
            if ($component['type'] === 'container') {
                return (int) $id;
            }
        }

        // Fallback: generate deterministic ID from screen class name
        return UIIdGenerator::generateFromName(
            $this->getContextIdentifier(),
            'screen_root'
        );
    }

    /**
     * @deprecated Use cacheScreenSnapshot() instead.
     */
    protected function storeUI(Container $ui): void
    {
        $this->cacheScreenSnapshot($ui);
    }

    /**
     * @deprecated Use getScreenComponentId() instead.
     */
    protected function getServiceComponentId(): int
    {
        return $this->getScreenComponentId();
    }

    // Modal events, toasts, redirects, and client meta-actions are provided by HandlesModals and HandlesNavigation traits.

    /**
     * Find a component by ID and return it only if it matches the expected class.
     *
     * @template T of UIElement
     *
     * @param  class-string<T>  $expectedClass
     * @return T|null
     */
    protected function findComponentAs(Container $container, int|string|null $id, string $expectedClass): ?UIElement
    {
        if ($id === null || $id === '') {
            return null;
        }

        $component = $container->findById((int) $id);

        return $component instanceof $expectedClass ? $component : null;
    }

    /**
     * Find a component in the root service container and return it as the expected class.
     *
     * @template T of UIElement
     *
     * @param  class-string<T>  $expectedClass
     * @return T|null
     */
    protected function findRootComponentAs(int|string|null $id, string $expectedClass): ?UIElement
    {
        if (! isset($this->container)) {
            return null;
        }

        return $this->findComponentAs($this->container, $id, $expectedClass);
    }

    /**
     * Get agent context for this screen.
     *
     * Optional method that can be overridden to provide metadata describing
     * what this screen does, what inputs it expects, and what outputs it may produce.
     *
     * Used by headless/AI clients to understand screen semantics without UI rendering.
     *
     * Default implementation returns an empty array (no agent context).
     * Override this method in child classes to provide semantic information.
     *
     * Example:
     * ```php
     * public function getAgentContext(): array
     * {
     *     return [
     *         'purpose' => 'User authentication',
     *         'inputs' => ['email', 'password'],
     *         'outputs' => ['redirect', 'toast', 'abort'],
     *         'constraints' => 'Email must be valid format. Password 8+ chars.'
     *     ];
     * }
     * ```
     *
     * @return array<string, mixed> Empty array by default. Override to provide agent context metadata.
     */
    public function getAgentContext(): array
    {
        return [];
    }
}
