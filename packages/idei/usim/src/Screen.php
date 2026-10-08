<?php

namespace Idei\Usim;

use Idei\Usim\Components\Button;
use Idei\Usim\Components\Calendar;
use Idei\Usim\Components\Card;
use Idei\Usim\Components\Carousel;
use Idei\Usim\Components\Checkbox;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\Form;
use Idei\Usim\Components\Input;
use Idei\Usim\Components\Label;
use Idei\Usim\Components\MenuDropdown;
use Idei\Usim\Components\Select;
use Idei\Usim\Components\Split;
use Idei\Usim\Components\Table;
use Idei\Usim\Components\TableCell;
use Idei\Usim\Components\TableHeaderCell;
use Idei\Usim\Components\TableHeaderRow;
use Idei\Usim\Components\TableRow;
use Idei\Usim\Components\Timer;
use Idei\Usim\Components\UIComponent;
use Idei\Usim\Components\Uploader;
use Idei\Usim\Concerns\HandlesAuthorization;
use Idei\Usim\Concerns\HandlesModals;
use Idei\Usim\Concerns\HandlesNavigation;
use Idei\Usim\Contracts\UIElement;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\Enums\Visibility;
use Idei\Usim\Layout\AbstractLayout;
use Idei\Usim\Layout\LayoutInterface;
use Idei\Usim\Support\UIDiffer;
use Idei\Usim\Support\UIIdGenerator;
use Idei\Usim\Support\UIStateManager;
use Idei\Usim\Support\UsimConfig;
use Idei\Usim\ValueObjects\Spacing;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionProperty;
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
        UIIdGenerator::pushCurrentContext($this->getContextIdentifier());
        try {
            $this->uiChanges()->setStorage($incomingStorage);

            if ($shouldReset) {
                $this->onResetScreen();
            }

            // Check if this screen should be wrapped by a Layout Screen
            if ($parent === 'main' && $this->modalLayerIndex === 0 && ! ($this instanceof LayoutInterface)) {
                $layoutClass = static::getLayoutClass();
                if ($layoutClass !== null && class_exists($layoutClass)) {
                    AbstractLayout::setActiveHostScreen(static::class);
                    try {
                        /** @var AbstractLayout $layout */
                        $layout = static::make($layoutClass);
                        AbstractLayout::setCurrent($layout);
                        $this->layoutInstance = $layout;
                        $layout->setActiveScreen('main', static::class);

                        $layout->render(
                            incomingStorage: $incomingStorage,
                            queryParams: $queryParams,
                            parent: 'main',
                            shouldReset: $shouldReset
                        );

                        $slot = $layout->getSlot('content') ?? $layout->getSlot('main');
                        if ($slot instanceof Container) {
                            $slot->clear();
                            self::embedInto(static::class, $slot);
                        }

                        return;
                    } finally {
                        AbstractLayout::setActiveHostScreen(null);
                    }
                }
            }

            $this->initializeEventContext(
                incomingStorage: $incomingStorage,
                queryParams: $queryParams,
                parent: $parent,
                buildParams: $buildParams
            );

            $this->finalizeEventContext(reload: true);

            $agentContext = $this->getAgentContext();
            if (! empty($agentContext)) {
                $this->uiChanges()->add(['agent_context' => $agentContext]);
            }
        } finally {
            UIIdGenerator::popCurrentContext();
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
        string $method,
        array $parameters = [],
        array $incomingStorage = [],
        array $queryParams = [],
        int|string|null $parent = null,
        ?int $triggerComponentId = null
    ): void {
        UIIdGenerator::pushCurrentContext($this->getContextIdentifier());
        try {
            $this->uiChanges()->setStorage($incomingStorage);

            if ($method === 'onResetScreen') {
                $this->onResetScreen();
                $this->initializeEventContext(
                    incomingStorage: $incomingStorage,
                    queryParams: $queryParams,
                    parent: $parent,
                    eventParameters: $parameters,
                    triggerComponentId: $triggerComponentId
                );
                $this->finalizeEventContext(reload: false);

                return;
            }

            $this->initializeEventContext(
                incomingStorage: $incomingStorage,
                queryParams: $queryParams,
                parent: $parent,
                eventParameters: $parameters,
                triggerComponentId: $triggerComponentId
            );

            if (is_callable([$this, $method])) {
                $this->$method($parameters);
            }

            $this->finalizeEventContext(reload: false);
        } finally {
            UIIdGenerator::popCurrentContext();
        }
    }

    /**
     * Initialize event context
     *
     * Called by UIEventController before invoking event handler.
     * Loads user Interface container and captures state for diff calculation.
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
        array $incomingStorage = [],
        array $queryParams = [],
        int|string|null $parent = null,
        array $eventParameters = [],
        ?int $triggerComponentId = null,
        array $buildParams = []
    ): void {
        $this->incomingStorage = $incomingStorage;
        $this->queryParams = $queryParams;
        self::$currentIncomingStorage = $incomingStorage;
        self::$currentQueryParams = $queryParams;

        // Inject storage values into protected properties (store_* variables)
        $this->injectStorageValues($incomingStorage);

        // Restore internal screen state from UIStateManager snapshot cache (state_* variables)
        $cachedState = UIStateManager::getScreenState($this->getContextIdentifier());
        if (! empty($cachedState)) {
            $this->injectStateVariables($cachedState);
        }

        $this->container = $this->reconstructScreenTreeFromCache(...$buildParams);
        if ($parent !== null && $parent !== '') {
            $this->parent = $parent;
            $this->container->setParent($parent);
        } elseif ($this->container->getParent() !== null) {
            $this->parent = $this->container->getParent();
        }

        if (! empty($eventParameters) || $triggerComponentId !== null) {
            $this->hydrateClientComponentState($eventParameters, $triggerComponentId);
        }

        $this->clearContainerDirtyState($this->container);
        $this->oldUI = $this->container->toJson();

        // Inject component references into protected properties
        $this->injectComponentReferences();
    }

    /**
     * Hydrate live component instances with the values currently held in the client DOM
     * before capturing the $oldUI snapshot.
     *
     * @param  array<string, mixed>  $eventParameters
     */
    protected function hydrateClientComponentState(array $eventParameters, ?int $triggerComponentId = null): void
    {
        if ($triggerComponentId !== null) {
            $triggerElement = $this->container->findById($triggerComponentId);
            if ($triggerElement instanceof UIComponent) {
                $type = $triggerElement->getType();
                if (in_array($type, ['input', 'textarea', 'select'], true) && array_key_exists('value', $eventParameters)) {
                    $val = $eventParameters['value'];
                    if (is_scalar($val) || $val === null) {
                        $triggerElement->syncClientConfig('value', $val ?? '');
                    }
                } elseif ($type === 'checkbox' && array_key_exists('checked', $eventParameters)) {
                    $triggerElement->syncClientConfig('checked', (bool) $eventParameters['checked']);
                }
            }
        }

        $reservedKeys = ['value', 'checked', 'name', '_caller_screen_id', '_caller_service_id'];

        foreach ($eventParameters as $paramKey => $paramValue) {
            if ($paramKey === '' || in_array($paramKey, $reservedKeys, true)) {
                continue;
            }

            $element = $this->container->findByName($paramKey);
            if (! ($element instanceof UIComponent)) {
                continue;
            }

            $type = $element->getType();
            if (in_array($type, ['input', 'textarea', 'select'], true)) {
                if (is_scalar($paramValue) || $paramValue === null) {
                    $element->syncClientConfig('value', $paramValue ?? '');
                }
            } elseif ($type === 'checkbox') {
                if (is_bool($paramValue)) {
                    $element->syncClientConfig('checked', $paramValue);
                } elseif (is_array($paramValue)) {
                    $element->syncClientConfig('selected_values', $paramValue);
                }
            }
        }
    }

    /**
     * Reset dirty tracking across all leaf components in a container tree.
     */
    protected function clearContainerDirtyState(Container $container): void
    {
        foreach ($container->getChildren() as $child) {
            if ($child instanceof UIComponent) {
                $child->clearDirtyKeys();
            } elseif ($child instanceof Container) {
                $this->clearContainerDirtyState($child);
            }
        }
    }

    /**
     * Inject storage values into protected properties
     *
     * Uses reflection to find protected properties whose names start with 'store_'.
     * If a matching key exists in the incoming storage array, the value is injected.
     * Properties ending with '_crypt' are automatically decrypted before injection.
     *
     * Convention: Property name must match storage key
     * Example: protected int $store_user_id; matches storage['store_user_id']
     * Example: protected string $store_token_crypt; decrypts storage['store_token_crypt'] before injection
     *
     * @param  array<string, mixed>  $incomingStorage  Storage data from frontend
     */
    public function injectStorageValues(array $incomingStorage): void
    {
        if (empty($incomingStorage)) {
            return;
        }

        $reflection = new ReflectionClass($this);

        $injected = [];

        foreach ($reflection->getProperties(ReflectionProperty::IS_PROTECTED) as $property) {
            // Skip properties declared in Screen itself
            if ($property->getDeclaringClass()->getName() === self::class) {
                continue;
            }

            $propertyName = $property->getName();

            // Only process properties that start with 'store_'
            if (! str_starts_with($propertyName, 'store_')) {
                continue;
            }

            // Check if this key exists in incoming storage
            if (! array_key_exists($propertyName, $incomingStorage)) {
                // \Illuminate\Support\Facades\Log::info("Skipping inject $propertyName - Not in storage");
                continue;
            }

            $value = $incomingStorage[$propertyName];
            // \Illuminate\Support\Facades\Log::info("Injecting $propertyName = $value");

            // if the propertyName ends with '_crypt' we attempt to decrypt it before injecting
            if (str_ends_with($propertyName, '_crypt')) {
                try {
                    if (! \is_string($value)) {
                        continue;
                    }

                    $value = decrypt($value);
                } catch (DecryptException $e) {
                    Log::warning("Failed to decrypt storage variable '{$propertyName}': ".$e->getMessage());

                    continue; // Skip injection if decryption fails
                }
            }

            // Set the value
            $property->setValue($this, $value);

            $injected[$propertyName] = $value;
        }
    }

    /**
     * Inject component references into protected properties
     *
     * Uses reflection to find protected properties with user Interface component type hints.
     * If a property name matches a component name in the container,
     * the component is injected into that property.
     *
     * Convention: Property name must match component name
     * Example: protected Label $lbl_result; matches component 'lbl_result'
     */
    private function injectComponentReferences(): void
    {
        $reflection = new ReflectionClass($this);
        $injected = [];

        foreach ($reflection->getProperties(ReflectionProperty::IS_PROTECTED) as $property) {
            // Skip properties declared in Screen itself
            if ($property->getDeclaringClass()->getName() === self::class) {
                continue;
            }

            $propertyType = $property->getType();

            // Skip if no type hint or is a built-in type
            if (! $propertyType) {
                continue;
            }

            // Check if it's a built-in type (only ReflectionNamedType has isBuiltin)
            if ($propertyType instanceof \ReflectionNamedType && $propertyType->isBuiltin()) {
                continue;
            }

            // Get the type name (only ReflectionNamedType has getName)
            if (! ($propertyType instanceof \ReflectionNamedType)) {
                continue;
            }

            $typeName = $propertyType->getName();

            // Only process UI component types from the current package namespace.
            if (str_starts_with($typeName, 'Idei\\Usim\\Components\\')) {
                $componentName = $property->getName();
                $component = $this->container->findByName($componentName);

                if ($component) {
                    $property->setValue($this, $component);
                    $injected[$componentName] = $typeName;
                } elseif (! $propertyType->allowsNull()) {
                    $className = static::class;
                    // Component not found and property is not nullable
                    throw new RuntimeException(
                        "Component '{$componentName}' not found in {$className}. ".
                            "Make sure the component exists or make the property nullable: protected ?{$typeName} \${$componentName};"
                    );
                }
            }
        }
    }

    /**
     * Finalize event context
     *
     * Called by UIEventController after event handler completes.
     * Automatically detects changes by comparing user Interface state, stores updated user Interface,
     * and returns formatted response.
     */
    public function finalizeEventContext(bool $reload = false): void
    {

        if ($reload) {
            $this->postLoadUI();
        }

        // Get current user Interface state
        $this->newUI = $this->container->toJson();

        // Persist the final container state for both event diffs and full reloads.
        // Without this, reload flows such as ?reset=true can leave cache with a pre-postLoad snapshot.
        $this->cacheScreenSnapshot($this->container);

        $diff = $this->buildDiffResponse($reload);
        $storageVariables = $this->getStorageVariables();
        $this->uiChanges()->add($diff);
        $this->uiChanges()->setStorage($storageVariables);
    }

    /**
     * Build diff response in indexed format
     *
     * @return array<int|string, array<string, mixed>> Indexed diff response
     */
    protected function buildDiffResponse(bool $reload = false): array
    {
        $oldUI = $this->oldUI ?? [];
        $newUI = $this->newUI ?? [];

        $diff = $reload ?
            UIDiffer::compare([], $newUI) :
            UIDiffer::compare($oldUI, $newUI);

        if (! $reload && isset($this->container)) {
            foreach ($this->collectDirtyComponentChanges($this->container) as $componentId => $dirtyProps) {
                if (! isset($this->newUI[$componentId])) {
                    continue;
                }
                if (($this->newUI[$componentId]['parent'] ?? null) === null) {
                    continue;
                }
                foreach ($dirtyProps as $propKey => $propValue) {
                    $diff[$componentId][$propKey] = $propValue;
                }
            }
        }

        $result = [];
        foreach ($diff as $componentId => $changes) {
            // Always include 'type' from newUI so frontend knows how to handle the change
            if (isset($this->newUI[$componentId]['type'])) {
                $changes['type'] = $this->newUI[$componentId]['type'];
            }

            $result[$componentId] = $changes;
        }

        return $result;
    }

    /**
     * Collect properties explicitly mutated via setConfig() during the current event lifecycle.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function collectDirtyComponentChanges(Container $container): array
    {
        $dirtyChanges = [];
        $ignoredKeys = ['type', 'parent', '_order'];

        foreach ($container->getChildren() as $child) {
            if ($child instanceof UIComponent) {
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
     * Get stored user Interface state, regenerate if missing
     *
     * @param  mixed  ...$params  Optional parameters passed to buildBaseUI
     * @return array<int|string, array<string, mixed>> user Interface structure in JSON format
     */
    protected function getCachedScreenSnapshot(...$params): array
    {
        $contextKey = $this->getContextIdentifier();

        // Check if user Interface exists in cache
        $cachedUI = UIStateManager::get($contextKey);

        if ($this->isTypedCachedScreenSnapshot($cachedUI) && $this->isValidCachedScreenSnapshot($cachedUI)) {
            $cachedState = UIStateManager::getScreenState($contextKey);
            if (! empty($cachedState)) {
                $this->injectStateVariables($cachedState);
            }

            return $cachedUI;
        }

        if ($cachedUI !== null) {
            UIStateManager::clear($contextKey);
        }

        $current_class = static::class;
        $current_class_slug = strtolower(str_replace('\\', '_', $current_class))
            .($this->modalLayerIndex > 0 ? "_{$this->modalLayerIndex}" : '');
        $container = UI::container($current_class_slug, $contextKey)
            ->parent($this->parent)
            ->modalLayerIndex($this->modalLayerIndex)
            ->padding(Spacing::px(5))
            ->layout(LayoutType::VERTICAL)
            ->justifyContent('center')
            ->alignItems('center');

        // Generate and cache user Interface directly
        $this->buildBaseUI($container, ...$params);

        $ui = $container
            ->root(true)
            ->toJson();

        $this->cacheScreenSnapshot($container);

        return $ui;
    }

    /**
     * Build and attach a nested screen inside a parent container.
     *
     * @param  class-string<Screen>  $class
     */
    public static function embedInto(string $class, Container $parent): void
    {
        $parentId = $parent->getId();

        $instance = self::make($class);
        $instance->parent = $parentId;

        $prevIncomingStorage = self::$currentIncomingStorage;
        $prevQueryParams = self::$currentQueryParams;

        UIIdGenerator::pushCurrentContext($instance->getContextIdentifier());
        try {
            $shouldReset = (bool) (self::$currentQueryParams['reset'] ?? request()->query('reset', false));
            if ($shouldReset) {
                $instance->onResetScreen();
            }

            $instance->initializeEventContext(
                incomingStorage: self::$currentIncomingStorage,
                queryParams: self::$currentQueryParams,
                parent: $parentId
            );
            $instance->postLoadUI();

            // Store the child screen's own snapshot with root=true and parent=$parentId
            // so direct events on the child screen can reconstruct its tree.
            $instance->container->root(true);
            $instance->cacheScreenSnapshot($instance->container);

            // Attach to the host screen's container with root=false so it does not
            // collide with the host screen's root container.
            $instance->container->root(false);
            $parent->add($instance->container);

            $instance->uiChanges()->add($instance->container->toJson());
            $instance->uiChanges()->setStorage($instance->getStorageVariables());
        } finally {
            self::$currentIncomingStorage = $prevIncomingStorage;
            self::$currentQueryParams = $prevQueryParams;
            UIIdGenerator::popCurrentContext();
        }
    }

    // Navigation and slot routing methods are provided by HandlesNavigation trait.

    /**
     * @phpstan-assert-if-true array<int|string, array<string, mixed>> $ui
     */
    private function isTypedCachedScreenSnapshot(mixed $ui): bool
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
     */
    /**
     * @param  array<int|string, array<string, mixed>>  $ui
     */
    private function isValidCachedScreenSnapshot(array $ui): bool
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

    /**
     * Reconstruct the current screen component tree from cache.
     *
     * If no cached snapshot exists, the UI is generated first and then reconstructed.
     *
     * @param  mixed  ...$params  Optional parameters passed to buildBaseUI if regenerating cache
     */
    protected function reconstructScreenTreeFromCache(...$params): Container
    {
        // Always get JSON from cache and reconstruct container
        // This ensures we get the latest state after events modify it
        $jsonUI = $this->getCachedScreenSnapshot(...$params);

        // Reconstruct container from JSON
        return $this->reconstructContainerFromJson($jsonUI);
    }

    /**
     * Reconstruct UI container from JSON array
     *
     * @param  array<int|string, array<string, mixed>>  $jsonUI  JSON representation of UI
     * @return Container Reconstructed container
     */
    private function reconstructContainerFromJson(array $jsonUI): Container
    {
        /** @var array<int|string, UIElement> $components */
        $components = [];
        $rootContainer = null;

        // UIDebug::debug("Reconstructing UI Container from JSON", $jsonUI);

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

            // Reserve IDs from cached snapshots so future auto-generated IDs
            // in this request do not collide with already deserialized components.
            if (is_numeric($id)) {
                UIIdGenerator::reserveContextId($this->getContextIdentifier(), (int) $id);
            }

            $components[$id] = $className::deserialize($id, $component);
        }

        // Second pass: set up parent-child relationships
        foreach ($components as $id => $component) {
            $parentId = $jsonUI[$id]['parent'] ?? null;

            if ($component instanceof Container && $component->isRoot()) {
                $rootContainer = $component;
            }

            // Detached components (parent=null) are valid during incremental remove operations.
            // Ignore them while rebuilding the live tree from cache.
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

        // UIDebug::debug("Reconstructed UI Container:\n", $rootContainer);

        return $rootContainer;
    }

    private function mapTypeToClass(string $type): ?string
    {
        return match ($type) {
            'label' => Label::class,
            'button' => Button::class,
            'input' => Input::class,
            'select' => Select::class,
            'checkbox' => Checkbox::class,
            'card' => Card::class,
            'table' => Table::class,
            'container' => Container::class,
            'tablerow' => TableRow::class,
            'tablecell' => TableCell::class,
            'tableheadercell' => TableHeaderCell::class,
            'form' => Form::class,
            'tableheaderrow' => TableHeaderRow::class,
            'menudropdown' => MenuDropdown::class,
            'uploader' => Uploader::class,
            'calendar' => Calendar::class,
            'carousel' => Carousel::class,
            'textarea' => 'Idei\\Usim\\Components\\Textarea',
            'split' => Split::class,
            'timer' => Timer::class,
            default => null,
        };
    }

    /**
     * Store UI state in cache
     *
     * @param  Container  $container  UI container to store
     */
    protected function cacheScreenSnapshot(Container $container): void
    {
        $contextKey = $this->getContextIdentifier();
        UIStateManager::store($contextKey, $container->toJson());

        $state = $this->getStateVariables();
        if (! empty($state)) {
            UIStateManager::storeScreenState($contextKey, $state);
        } else {
            UIStateManager::clearScreenState($contextKey);
        }
    }

    /**
     * Clear the cached screen snapshot.
     */
    public function clearCachedScreenSnapshot(): bool
    {
        $contextKey = $this->getContextIdentifier();
        UIStateManager::clearScreenState($contextKey);

        return UIStateManager::clear($contextKey);
    }

    /**
     * Allow child classes to react when the screen is reset.
     */
    public function onResetScreen(): void
    {
        $cleared = $this->clearCachedScreenSnapshot();
        // $screenName = class_basename(static::class);
        // if ($cleared) {
        //     Log::info("Screen '{$screenName}' cache cleared successfully.");
        // } else {
        //     Log::warning("Screen '{$screenName}' cache was already empty or could not be cleared.");
        // }
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

    /**
     * Uses reflection to scan private and protected properties whose names start with the "store_"
     * prefix and whose type hints are non-nullable primitive types (int, float, string, bool) or array.
     * Properties ending with "_crypt" are automatically encrypted before storage.
     * It then builds an associative array with the following structure:
     *
     * [
     *   'storage' => [
     *      [front_store_key] => 'encrypted_json_string',
     *   ]
     * ]
     *
     * @return array<string, mixed> Associative array with the variables to be stored on the frontend
     */
    public function getStorageVariables(): array
    {
        $storage = [];
        $reflection = new ReflectionClass($this);
        $properties = $reflection->getProperties(ReflectionProperty::IS_PROTECTED);

        foreach ($properties as $property) {
            $propertyName = $property->getName();
            if (str_starts_with($propertyName, 'store_')) {
                $propertyType = $property->getType();
                if ($propertyType) {
                    // Get the type name (only ReflectionNamedType has getName)
                    if (! ($propertyType instanceof \ReflectionNamedType)) {
                        continue;
                    }
                    $typeName = $propertyType->getName();
                    $isPrimitive = in_array($typeName, ['int', 'float', 'string', 'bool', 'array', 'mixed']);
                    if ($isPrimitive) {
                        $value = $property->getValue($this);
                        if ($value !== null && str_ends_with($propertyName, '_crypt')) {
                            $value = encrypt($value);
                        }
                        $storage[$propertyName] = $value;
                    }
                }
            }
        }

        return $storage;
    }

    /**
     * Get internal screen state variables to be persisted in UIStateManager snapshot cache.
     *
     * By convention, any protected or public property starting with 'state_' is automatically collected.
     * Unlike 'store_' variables, 'state_' variables are server-side only and never sent to the client payload.
     *
     * @return array<string, mixed> Associative array of state variables
     */
    public function getStateVariables(): array
    {
        $state = [];
        $reflection = new ReflectionClass($this);
        $properties = $reflection->getProperties(ReflectionProperty::IS_PROTECTED | ReflectionProperty::IS_PUBLIC);

        foreach ($properties as $property) {
            // Skip properties declared in Screen base class
            if ($property->getDeclaringClass()->getName() === self::class) {
                continue;
            }

            $propertyName = $property->getName();
            if (str_starts_with($propertyName, 'state_')) {
                if (! $property->isInitialized($this)) {
                    continue;
                }
                $state[$propertyName] = $property->getValue($this);
            }
        }

        return $state;
    }

    /**
     * Inject cached internal screen state variables into screen properties.
     *
     * @param  array<string, mixed>  $state
     */
    public function injectStateVariables(array $state): void
    {
        if (empty($state)) {
            return;
        }

        $reflection = new ReflectionClass($this);

        foreach ($reflection->getProperties(ReflectionProperty::IS_PROTECTED | ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->getDeclaringClass()->getName() === self::class) {
                continue;
            }

            $propertyName = $property->getName();
            if (! str_starts_with($propertyName, 'state_')) {
                continue;
            }

            if (! array_key_exists($propertyName, $state)) {
                continue;
            }

            $value = $state[$propertyName];
            $type = $property->getType();

            if ($type instanceof \ReflectionNamedType) {
                $typeName = $type->getName();
                if ($value === null && $type->allowsNull()) {
                    $property->setValue($this, null);

                    continue;
                }
                if ($typeName === 'int' && is_numeric($value)) {
                    $value = (int) $value;
                } elseif ($typeName === 'float' && is_numeric($value)) {
                    $value = (float) $value;
                } elseif ($typeName === 'bool') {
                    $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                } elseif ($typeName === 'string' && (is_scalar($value) || is_null($value))) {
                    $value = (string) ($value ?? '');
                }
            }

            $property->setValue($this, $value);
        }
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
