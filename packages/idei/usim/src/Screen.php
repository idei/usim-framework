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
use Idei\Usim\Contracts\UIElement;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\Enums\Visibility;
use Idei\Usim\Models\UsimUnit;
use Idei\Usim\Support\UIDiffer;
use Idei\Usim\Support\UIIdGenerator;
use Idei\Usim\Support\UIStateManager;
use Idei\Usim\ValueObjects\Spacing;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Auth;
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
            return static::class . '@' . $this->modalLayerIndex;
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
     * @var class-string<\Idei\Usim\Layout\AbstractLayout>|string|null
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
    protected ?\Idei\Usim\Layout\AbstractLayout $layoutInstance = null;

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
     * @return class-string<\Idei\Usim\Layout\AbstractLayout>|null
     */
    public static function getLayoutClass(): ?string
    {
        if (static::$layout === null) {
            return null;
        }

        /** @var \Idei\Usim\Support\UsimConfig $usimConfig */
        $usimConfig = app(\Idei\Usim\Support\UsimConfig::class);
        $layoutClass = static::$layout === 'default' ? $usimConfig->defaultLayout : static::$layout;

        if (class_exists($layoutClass) && is_subclass_of($layoutClass, \Idei\Usim\Layout\AbstractLayout::class)) {
            /** @var class-string<\Idei\Usim\Layout\AbstractLayout> $layoutClass */
            return $layoutClass;
        }

        return null;
    }

    /**
     * Get the active layout instance associated with this screen or request context.
     */
    public function getLayout(): ?\Idei\Usim\Layout\AbstractLayout
    {
        if ($this->layoutInstance !== null) {
            return $this->layoutInstance;
        }

        if (\Idei\Usim\Layout\AbstractLayout::current() !== null) {
            return \Idei\Usim\Layout\AbstractLayout::current();
        }

        if (app()->bound(\Idei\Usim\Layout\AbstractLayout::class)) {
            /** @var \Idei\Usim\Layout\AbstractLayout $layout */
            $layout = app(\Idei\Usim\Layout\AbstractLayout::class);
            return $layout;
        }

        $layoutClass = null;
        $currentScreenClass = UIStateManager::getClientCurrentScreenClass();
        if ($currentScreenClass !== null && class_exists($currentScreenClass) && is_subclass_of($currentScreenClass, self::class)) {
            $layoutClass = $currentScreenClass::getLayoutClass();
        }

        if ($layoutClass === null) {
            /** @var \Idei\Usim\Support\UsimConfig $usimConfig */
            $usimConfig = app(\Idei\Usim\Support\UsimConfig::class);
            $defaultLayout = $usimConfig->defaultLayout;
            if (class_exists($defaultLayout) && is_subclass_of($defaultLayout, \Idei\Usim\Layout\AbstractLayout::class)) {
                /** @var class-string<\Idei\Usim\Layout\AbstractLayout> $defaultLayout */
                $layoutClass = $defaultLayout;
            }
        }

        if ($layoutClass !== null && class_exists($layoutClass) && is_subclass_of($layoutClass, \Idei\Usim\Layout\AbstractLayout::class)) {
            /** @var \Idei\Usim\Layout\AbstractLayout $layout */
            $layout = app($layoutClass);
            $layout->initializeEventContext();
            \Idei\Usim\Layout\AbstractLayout::setCurrent($layout);
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

        /** @var \Idei\Usim\Support\UsimConfig $usimConfig */
        $usimConfig = app(\Idei\Usim\Support\UsimConfig::class);
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
        /** @var \Idei\Usim\Support\UsimConfig $usimConfig */
        $usimConfig = app(\Idei\Usim\Support\UsimConfig::class);

        return $usimConfig->resolveScreenClass($screenRoute);
    }

    /**
     * Resolve a screen slug from a class-string (e.g. 'App\UI\Screens\Admin\UsersManager' -> 'admin/users-manager').
     */
    public static function resolveScreenSlug(string $screenClass): string
    {
        /** @var \Idei\Usim\Support\UsimConfig $usimConfig */
        $usimConfig = app(\Idei\Usim\Support\UsimConfig::class);

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
    public function getLayoutInstance(): ?\Idei\Usim\Layout\AbstractLayout
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

        if (!class_exists($targetClass) || !is_subclass_of($targetClass, self::class)) {
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

    /**
     * Open this screen (or specified screenClass) as a modal overlay.
     *
     * @param  array<int|string, mixed>  $params  Parameters passed to buildBaseUI
     * @param  Screen|null  $caller  The parent/caller screen that invoked this modal
     * @param  string|null  $callbackAction  Action method on caller to invoke upon return
     * @param  array<string, mixed>  $queryParams  Additional query parameters
     * @param  class-string<Screen>|string|null  $screenClass  Target screen class if called statically from Screen base
     */
    public static function openAsModal(
        array $params = [],
        ?Screen $caller = null,
        ?string $callbackAction = null,
        array $queryParams = [],
        ?string $screenClass = null,
    ): Screen {
        $instance = static::make($screenClass);
        $instance->parent = 'modal';

        if ($caller !== null) {
            $instance->callerScreenId = $caller->getScreenComponentId();
            $instance->callerScreenClass = $caller::class;
            $instance->callbackAction = $callbackAction;
        } elseif ((isset($params['callerServiceId']) && is_numeric($params['callerServiceId'])) || (isset($params['caller_service_id']) && is_numeric($params['caller_service_id']))) {
            $rawCallerId = $params['callerServiceId'] ?? $params['caller_service_id'];
            if (is_int($rawCallerId) || (is_string($rawCallerId) && ctype_digit($rawCallerId))) {
                $instance->callerScreenId = (int) $rawCallerId;
                $context = UIIdGenerator::getContextFromId($instance->callerScreenId);
                if (is_string($context) && is_a($context, Screen::class, true)) {
                    $instance->callerScreenClass = $context;
                }
            }
            $instance->callbackAction = $callbackAction;
        }

        $currentStack = UIStateManager::getClientActiveModalStack();
        $sameClassCount = 0;
        foreach ($currentStack as $entry) {
            if ($entry['modal_class'] === $instance::class) {
                $sameClassCount++;
            }
        }
        $instance->modalLayerIndex = $sameClassCount;

        // Clear any previous cached snapshot so the modal renders fresh
        $instance->clearCachedScreenSnapshot();

        $instance->render(
            incomingStorage: self::$currentIncomingStorage,
            queryParams: $queryParams,
            parent: 'modal',
            shouldReset: false,
            buildParams: $params,
        );

        // Store active modal state in UIStateManager for F5 / reconnection persistence
        UIStateManager::storeClientActiveModal(
            modalClass: $instance::class,
            callerScreenId: $instance->callerScreenId,
            callbackAction: $instance->callbackAction,
            params: $params,
            layerIndex: $instance->modalLayerIndex,
            callerScreenClass: $instance->callerScreenClass,
        );

        return $instance;
    }

    /**
     * Helper to open any screen as a modal from within the current screen.
     *
     * @param  class-string<Screen>  $screenClass
     * @param  array<int|string, mixed>  $params
     * @param  array<string, mixed>  $queryParams
     */
    protected function openModal(
        string $screenClass,
        array $params = [],
        ?string $callbackAction = null,
        array $queryParams = [],
    ): Screen {
        return self::openAsModal(
            params: $params,
            caller: $this,
            callbackAction: $callbackAction,
            queryParams: $queryParams,
            screenClass: $screenClass,
        );
    }

    /**
     * Determine whether this screen is currently opened as a modal overlay.
     */
    public function isOpenedAsModal(): bool
    {
        if ($this->parent === 'modal') {
            return true;
        }

        if (isset($this->container) && $this->container->getParent() === 'modal') {
            return true;
        }

        $activeStack = UIStateManager::getClientActiveModalStack();
        foreach ($activeStack as $modalMeta) {
            if ($modalMeta['modal_class'] === static::class) {
                return true;
            }
        }

        return false;
    }

    /**
     * Restore and re-render an active modal for F5 / browser reload.
     *
     * @param  array{modal_class: class-string<Screen>|string, caller_screen_id: ?int, caller_screen_class: ?string, callback_action: ?string, params: array<int|string, mixed>}  $activeModal
     * @param  array<string, mixed>  $incomingStorage
     * @param  array<string, mixed>  $queryParams
     */
    public static function restoreActiveModal(
        array $activeModal,
        Screen $caller,
        array $incomingStorage = [],
        array $queryParams = [],
    ): ?Screen {
        $modalClass = $activeModal['modal_class'];
        if (!class_exists($modalClass) || !is_a($modalClass, self::class, true)) {
            return null;
        }

        $instance = static::make($modalClass);
        $instance->parent = 'modal';
        $instance->callerScreenId = $caller->getScreenComponentId();
        $instance->callerScreenClass = $caller::class;
        $instance->callbackAction = $activeModal['callback_action'];

        $instance->render(
            incomingStorage: $incomingStorage,
            queryParams: $queryParams,
            parent: 'modal',
            shouldReset: false,
            buildParams: $activeModal['params'],
        );

        // Keep active modal in UIStateManager refreshed with any updated caller ID
        UIStateManager::storeClientActiveModal(
            modalClass: $instance::class,
            callerScreenId: $instance->callerScreenId,
            callbackAction: $instance->callbackAction,
            params: $activeModal['params'],
        );

        return $instance;
    }

    /**
     * Restore and re-render an active modal stack for F5 / browser reload.
     *
     * @param  list<array{modal_class: string, caller_screen_id: ?int, caller_screen_class: ?string, callback_action: ?string, params: array<int|string, mixed>, layer_index?: int, page_screen_route?: ?string}>  $modalStack
     * @param  array<string, mixed>  $incomingStorage
     * @param  array<string, mixed>  $queryParams
     * @return list<Screen>
     */
    public static function restoreActiveModalStack(
        array $modalStack,
        Screen $caller,
        array $incomingStorage = [],
        array $queryParams = [],
    ): array {
        $restoredScreens = [];
        /** @var array<string, Screen> $screensByClass */
        $screensByClass = [];
        $screensByClass[$caller::class] = $caller;

        $updatedStack = [];
        $countsByClass = [];

        foreach ($modalStack as $modalMeta) {
            $modalClass = $modalMeta['modal_class'];
            if (!class_exists($modalClass) || !is_a($modalClass, self::class, true)) {
                continue;
            }

            $instance = static::make($modalClass);
            $instance->parent = 'modal';

            $layerIndex = $modalMeta['layer_index'] ?? ($countsByClass[$modalClass] ?? 0);
            $countsByClass[$modalClass] = $layerIndex + 1;
            $instance->modalLayerIndex = $layerIndex;

            // Resolve caller: check if caller was a previous modal in the stack,
            // or an explicit screen class (e.g. embedded screen or Menu), otherwise host caller.
            $callerScreenClass = $modalMeta['caller_screen_class'];
            $callerScreenId = $modalMeta['caller_screen_id'];

            if ($callerScreenClass !== null && isset($screensByClass[$callerScreenClass])) {
                $effectiveCaller = $screensByClass[$callerScreenClass];
            } elseif ($callerScreenClass !== null && class_exists($callerScreenClass) && is_a($callerScreenClass, self::class, true)) {
                $effectiveCaller = static::make($callerScreenClass);
                $screensByClass[$callerScreenClass] = $effectiveCaller;
            } else {
                $effectiveCaller = $caller;
            }

            $instance->callerScreenId = $callerScreenId ?? $effectiveCaller->getScreenComponentId();
            $instance->callerScreenClass = $effectiveCaller::class;
            $instance->callbackAction = $modalMeta['callback_action'];

            $instance->render(
                incomingStorage: $incomingStorage,
                queryParams: $queryParams,
                parent: 'modal',
                shouldReset: false,
                buildParams: $modalMeta['params'],
            );

            $screensByClass[$instance::class] = $instance;
            $restoredScreens[] = $instance;

            $updatedStack[] = [
                'modal_class' => $instance::class,
                'caller_screen_id' => $instance->callerScreenId,
                'caller_screen_class' => $instance->callerScreenClass,
                'callback_action' => $instance->callbackAction,
                'params' => $modalMeta['params'],
                'layer_index' => $instance->modalLayerIndex,
                'page_screen_route' => $modalMeta['page_screen_route'] ?? null,
            ];
        }

        // Refresh stack in UIStateManager
        UIStateManager::setClientActiveModalStack($updatedStack);

        return $restoredScreens;
    }

    /**
     * Check access permission and return result structure.
     * This method is static to allow checking permissions without instantiating the service.
     *
     * @return array{allowed: bool, action: ?string, params: array<string, mixed>}
     */
    public static function checkAccess(): array
    {
        // 1. Check authorization logic
        if (static::authorize()) {
            return ['allowed' => true, 'action' => null, 'params' => []];
        }

        $guard = static::getAuthGuard();

        // 2. Handle failure based on authentication state
        if (!Auth::guard($guard)->check()) {
            $redirectUrl = ($guard === 'device')
                ? url('/device/device-pairing-screen')
                : url('/auth/login');

            return [
                'allowed' => false,
                'action' => 'redirect',
                'params' => [
                    'url' => $redirectUrl,
                    'message' => 'Please authenticate to access this page.',
                ],
            ];
        }

        // 3. Authenticated but unauthorized
        return [
            'allowed' => false,
            'action' => 'abort',
            'params' => [
                'code' => 403,
                'message' => 'Unauthorized: Insufficient permissions.',
            ],
        ];
    }

    /**
     * Determine if the user is authorized to access this service.
     */
    public static function authorize(): bool
    {
        return true;
    }

    /**
     * Helper to require authentication.
     * Use this inside your authorize() method.
     *
     * @param  string|null  $guard  Optional guard to check. Defaults to getAuthGuard().
     */
    protected static function requireAuth(?string $guard = null): bool
    {
        $effectiveGuard = $guard ?? static::getAuthGuard();

        return Auth::guard($effectiveGuard)->check();
    }

    /**
     * Safely call a boolean-like method on a possibly unknown auth user object.
     */
    private static function callUserBoolMethod(mixed $user, string $method, mixed ...$args): bool
    {
        if (!\is_object($user)) {
            return false;
        }

        $callback = [$user, $method];
        if (!\is_callable($callback)) {
            return false;
        }

        return (bool) $callback(...$args);
    }

    /**
     * Resolve a unit ID from an instance, integer ID, or slug.
     */
    protected static function resolveUnitId(mixed $unit): ?int
    {
        if ($unit instanceof UsimUnit) {
            return (int) $unit->id;
        }

        if (is_numeric($unit) && (int) $unit > 0) {
            return (int) $unit;
        }

        if (is_string($unit) && $unit !== '') {
            $id = UsimUnit::where('slug', $unit)->value('id');

            return is_numeric($id) ? (int) $id : null;
        }

        return null;
    }

    /**
     * Get the active organizational unit for the current user and context.
     */
    public function getActiveUnit(): ?UsimUnit
    {
        $user = Auth::user();
        $slug = UIStateManager::getActiveUnit()
            ?? (property_exists($this, 'state_unit') && !empty($this->state_unit) ? $this->state_unit : null);

        if (class_exists(\App\Services\Units\UnitContextResolver::class)) {
            return \App\Services\Units\UnitContextResolver::resolve($user, $slug);
        }

        if ($slug !== null) {
            return UsimUnit::where('slug', $slug)->first();
        }

        return null;
    }

    /**
     * Helper to require a role (implies authentication).
     * Use this inside your authorize() method.
     *
     * @param  string|list<string>  $roles
     * @param  UsimUnit|int|string|null  $unit  Optional unit context
     */
    protected static function requireRole(string|array $roles, ?string $guard = null, mixed $unit = null): bool
    {
        $effectiveGuard = $guard ?? static::getAuthGuard();

        // Implicitly require authentication first
        if (!self::requireAuth($effectiveGuard)) {
            return false;
        }

        $user = Auth::guard($effectiveGuard)->user();
        if ($user === null) {
            return false;
        }

        if (method_exists($user, 'isRoot') && $user->isRoot()) {
            return true;
        }

        $targetUnitId = self::resolveUnitId($unit);

        if ($targetUnitId !== null && function_exists('getPermissionsTeamId') && function_exists('setPermissionsTeamId')) {
            $previousTeamId = getPermissionsTeamId();
            try {
                setPermissionsTeamId($targetUnitId);

                return self::callUserBoolMethod($user, 'hasAnyRole', $roles);
            } finally {
                setPermissionsTeamId($previousTeamId);
            }
        }

        if (!self::callUserBoolMethod($user, 'hasAnyRole', $roles)) {
            // user is authenticated but lacks role
            // Instead of aborting, we return false.
            // The framework will catch this in authorize() and call failedAuthorization()
            // where we can gracefully handle the error (toast + redirect).
            return false;
        }

        return true;
    }

    /**
     * Helper to require a permission (implies authentication).
     * Use this inside your authorize() method.
     *
     * @param  string|list<string>  $permissions
     * @param  UsimUnit|int|string|null  $unit  Optional unit context
     */
    protected static function requirePermission(string|array $permissions, ?string $guard = null, mixed $unit = null): bool
    {
        $effectiveGuard = $guard ?? static::getAuthGuard();

        // Implicitly require authentication first
        if (!self::requireAuth($effectiveGuard)) {
            return false;
        }

        $user = Auth::guard($effectiveGuard)->user();
        if ($user === null) {
            return false;
        }

        if (method_exists($user, 'isRoot') && $user->isRoot()) {
            return true;
        }

        $targetUnitId = self::resolveUnitId($unit);

        if ($targetUnitId !== null && function_exists('getPermissionsTeamId') && function_exists('setPermissionsTeamId')) {
            $previousTeamId = getPermissionsTeamId();
            try {
                setPermissionsTeamId($targetUnitId);

                return self::callUserBoolMethod($user, 'hasAnyPermission', $permissions);
            } finally {
                setPermissionsTeamId($previousTeamId);
            }
        }

        if (!self::callUserBoolMethod($user, 'hasAnyPermission', $permissions)) {
            return false;
        }

        return true;
    }

    /**
     * Gets a unique identifier for the screen based on its Namespace and Class.
     * Example: App\Usim\Screens\Admin\UserManagerScreen -> admin.user_manager
     */
    protected static function getScreenSlug(): string
    {
        // 1. Get the FQCN (Fully Qualified Class Name) of the child class
        $className = static::class;

        // For example, remove 'App\Usim\Screens\\' if you want to shorten it
        // 2. We remove the base namespace of the project (optional, to clean up the prefix)
        // For example, remove 'App\Usim\Screens\\' if you want to shorten it
        $cleanPath = Str::after($className, 'Screens\\');

        // 3. We convert 'Admin\UserManagerScreen' into ['Admin', 'UserManagerScreen']
        $segments = explode('\\', $cleanPath);

        // 4. We transform each segment to snake_case and join them with dots
        $dotted = collect($segments)
            ->map(fn($segment) => Str::snake(Str::replaceLast('Screen', '', $segment))) // Opcional: remover el sufijo 'Screen' si lo usan
            ->implode('.');

        return $dotted; // Returns "admin.user_manager"
    }

    /**
     * The required permissions to access this screen.
     * Override this in child classes to customize.
     *
     * @return array<string>
     */
    public static function requiredPermissions(): array
    {
        return ['access'];
    }

    /**
     * Extra screen's permissions can be defined overriding this.
     *
     * @return string[]
     */
    public static function permissions(): array
    {
        return [];
    }

    /**
     * Dynamically generates
     * "[slug].access" permission based on the screen's namespace and class name.
     *
     * @return array<string> Array of resolved permission strings
     */
    final public static function resolvedPermissions(): array
    {
        $ret = [];

        if (static::$visibility !== Visibility::AUTHENTICATED) {
            return $ret; // No permissions for guest-only or public screens
        }

        // We force 'access' to always be present by combining it with the extras. This ensures
        // the base permission is always generated, even if the child class forgets to include
        // it in permissions().
        $allPermissions = array_unique(['access', ...static::permissions()]);

        $screenContextPart = static::getScreenSlug(); // e.g., "admin.user_manager"

        foreach ($allPermissions as $permission) {
            $permission = "$screenContextPart.$permission"; // e.g., "admin.user_manager.access"
            $translationKey = "permission.$permission";
            $ret[$permission] = $translationKey;
        }

        return $ret;
    }

    /**
     * Determinates if the currently authenticated user has a specific permission within the context of this screen.
     *
     * @param  string  $permission  The short permission name (e.g., "publish") that will be resolved to a full permission
     *                              string based on the screen's slug (e.g., "blog.post_management.publish").
     * @param  UsimUnit|int|string|null  $unit  Optional unit context (instance, ID, or slug).
     *                                          Defaults to the screen's active unit ($this->state_unit) or the ambient permissions team.
     */
    public function userCan(string $permission, mixed $unit = null): bool
    {
        if (static::$visibility === Visibility::PUBLIC) {
            return true;
        }

        if (!Auth::check()) {
            return false;
        }

        $user = Auth::user();
        if ($user === null) {
            return false;
        }

        if (method_exists($user, 'isRoot') && $user->isRoot()) {
            return true;
        }

        // If the permission doesn't contain a dot, we assume it's a local permission and resolve
        //  it using the screen's slug.
        if (!str_contains($permission, '.')) {
            $permission = static::getScreenSlug() . '.' . $permission;
        }

        $targetUnitId = self::resolveUnitId($unit);
        if ($targetUnitId === null) {
            $activeUnitSlug = UIStateManager::getActiveUnit()
                ?? (property_exists($this, 'state_unit') && !empty($this->state_unit) ? $this->state_unit : null);
            if ($activeUnitSlug !== null) {
                $targetUnitId = self::resolveUnitId($activeUnitSlug);
            }
        }

        if ($targetUnitId !== null && function_exists('getPermissionsTeamId') && function_exists('setPermissionsTeamId')) {
            $previousTeamId = getPermissionsTeamId();
            try {
                setPermissionsTeamId($targetUnitId);

                return self::callUserBoolMethod($user, 'hasPermissionTo', $permission);
            } finally {
                setPermissionsTeamId($previousTeamId);
            }
        }

        return self::callUserBoolMethod($user, 'hasPermissionTo', $permission);
    }

    /**
     * Determines if the currently authenticated user has any of the given roles within the context of this screen.
     *
     * @param  string|list<string>  $roles
     * @param  UsimUnit|int|string|null  $unit  Optional unit context (instance, ID, or slug).
     *                                          Defaults to the active unit (UIStateManager::getActiveUnit()) or the ambient permissions team.
     */
    public function userHasRole(string|array $roles, mixed $unit = null): bool
    {
        if (static::$visibility === Visibility::PUBLIC) {
            return true;
        }

        if (!Auth::check()) {
            return false;
        }

        $user = Auth::user();
        if ($user === null) {
            return false;
        }

        if (method_exists($user, 'isRoot') && $user->isRoot()) {
            return true;
        }

        $targetUnitId = self::resolveUnitId($unit);
        if ($targetUnitId === null) {
            $activeUnitSlug = UIStateManager::getActiveUnit()
                ?? (property_exists($this, 'state_unit') && !empty($this->state_unit) ? $this->state_unit : null);
            if ($activeUnitSlug !== null) {
                $targetUnitId = self::resolveUnitId($activeUnitSlug);
            }
        }

        if ($targetUnitId !== null && function_exists('getPermissionsTeamId') && function_exists('setPermissionsTeamId')) {
            $previousTeamId = getPermissionsTeamId();
            try {
                setPermissionsTeamId($targetUnitId);

                return self::callUserBoolMethod($user, 'hasAnyRole', $roles);
            } finally {
                setPermissionsTeamId($previousTeamId);
            }
        }

        return self::callUserBoolMethod($user, 'hasAnyRole', $roles);
    }

    /**
     * Get the menu label for this screen.
     * Defaults to the class name (spaced and capitalized).
     * Override this in child classes to customize.
     */
    public static function getMenuLabel(): string
    {
        return t('screen.' . static::getScreenSlug() . '.menu_title');
    }

    /**
     * Get the menu icon for this screen.
     * Override this in child classes to customize.
     */
    public static function getMenuIcon(): ?string
    {
        return t('screen.' . static::getScreenSlug() . '.icon');
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
            $urlSegments = array_map(fn($s) => Str::kebab($s), $segments);

            return '/' . implode('/', $urlSegments);
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

    protected function postLoadUI(): void
    {
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
        UIIdGenerator::pushCurrentContext($this->getContextIdentifier());
        try {
            $this->uiChanges()->setStorage($incomingStorage);

            if ($shouldReset) {
                $this->onResetScreen();
            }

            // Check if this screen should be wrapped by a Layout Screen
            if ($parent === 'main' && $this->modalLayerIndex === 0 && !($this instanceof \Idei\Usim\Layout\LayoutInterface)) {
                $layoutClass = static::getLayoutClass();
                if ($layoutClass !== null && class_exists($layoutClass)) {
                    \Idei\Usim\Layout\AbstractLayout::setActiveHostScreen(static::class);
                    try {
                        /** @var static&\Idei\Usim\Layout\LayoutInterface $layout */
                        $layout = static::make($layoutClass);
                        if ($layout instanceof \Idei\Usim\Layout\AbstractLayout) {
                            \Idei\Usim\Layout\AbstractLayout::setCurrent($layout);
                            $this->layoutInstance = $layout;
                            $layout->setActiveScreen('main', static::class);
                        }

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
                        \Idei\Usim\Layout\AbstractLayout::setActiveHostScreen(null);
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
            if (!empty($agentContext)) {
                $this->uiChanges()->add(['agent_context' => $agentContext]);
            }
        } finally {
            UIIdGenerator::popCurrentContext();
        }
    }

    /**
     * Execute an action handler within the managed Screen event lifecycle.
     *
     * @param  string  $method  Name of the action handler method (e.g., 'onSave', 'onResetScreen')
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
        if (method_exists(UIStateManager::class, 'getScreenState')) {
            $cachedState = UIStateManager::getScreenState($this->getContextIdentifier());
            if (!empty($cachedState)) {
                $this->injectStateVariables($cachedState);
            }
        }

        $this->container = $this->reconstructScreenTreeFromCache(...$buildParams);
        if ($parent !== null && $parent !== '') {
            $this->parent = $parent;
            $this->container->setParent($parent);
        } elseif ($this->container->getParent() !== null) {
            $this->parent = $this->container->getParent();
        }

        if (!empty($eventParameters) || $triggerComponentId !== null) {
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
            if (!($element instanceof UIComponent)) {
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
            if (!str_starts_with($propertyName, 'store_')) {
                continue;
            }

            // Check if this key exists in incoming storage
            if (!array_key_exists($propertyName, $incomingStorage)) {
                // \Illuminate\Support\Facades\Log::info("Skipping inject $propertyName - Not in storage");
                continue;
            }

            $value = $incomingStorage[$propertyName];
            // \Illuminate\Support\Facades\Log::info("Injecting $propertyName = $value");

            // if the propertyName ends with '_crypt' we attempt to decrypt it before injecting
            if (str_ends_with($propertyName, '_crypt')) {
                try {
                    if (!\is_string($value)) {
                        continue;
                    }

                    $value = decrypt($value);
                } catch (DecryptException $e) {
                    Log::warning("Failed to decrypt storage variable '{$propertyName}': " . $e->getMessage());

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
            if (!$propertyType) {
                continue;
            }

            // Check if it's a built-in type (only ReflectionNamedType has isBuiltin)
            if ($propertyType instanceof \ReflectionNamedType && $propertyType->isBuiltin()) {
                continue;
            }

            // Get the type name (only ReflectionNamedType has getName)
            if (!($propertyType instanceof \ReflectionNamedType)) {
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
                } elseif (!$propertyType->allowsNull()) {
                    $className = static::class;
                    // Component not found and property is not nullable
                    throw new RuntimeException(
                        "Component '{$componentName}' not found in {$className}. " .
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

        if (!$reload && isset($this->container)) {
            foreach ($this->collectDirtyComponentChanges($this->container) as $componentId => $dirtyProps) {
                if (!isset($this->newUI[$componentId])) {
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
                if (!empty($dirtyKeys)) {
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
            if (method_exists(UIStateManager::class, 'getScreenState')) {
                $cachedState = UIStateManager::getScreenState($contextKey);
                if (!empty($cachedState)) {
                    $this->injectStateVariables($cachedState);
                }
            }

            return $cachedUI;
        }

        if ($cachedUI !== null) {
            UIStateManager::clear($contextKey);
        }

        $current_class = static::class;
        $current_class_slug = strtolower(str_replace('\\', '_', $current_class))
            . ($this->modalLayerIndex > 0 ? "_{$this->modalLayerIndex}" : '');
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

    /**
     * Show a target screen inside a specific layout slot or named container.
     *
     * @param  class-string<Screen>|string  $screenClass  Target screen class or slug
     * @param  string|null  $slot Name of the slot in the layout (e.g. 'main', 'top_menu', 'right_panel'). If null, uses target's $defaultSlot.
     * @param  array<int|string, mixed>  $params  Parameters passed to buildBaseUI
     * @param  bool  $updateBrowserUrl  Whether to update browser location history for main content navigation
     * @param  bool  $force Whether to force reload the slot even if the target screen class matches current screen
     */
    public function showInto(string $screenClass, ?string $slot = null, array $params = [], bool $updateBrowserUrl = true, bool $force = false): bool
    {
        $targetClass = class_exists($screenClass) ? $screenClass : static::resolveScreenClassFromSlug($screenClass);
        if ($targetClass === null || !class_exists($targetClass) || !is_subclass_of($targetClass, self::class)) {
            throw new RuntimeException("Target screen [{$screenClass}] is not a valid Screen instance.");
        }

        $access = $targetClass::checkAccess();
        if (!$access['allowed']) {
            $redirectUrl = $access['params']['url'] ?? null;
            if (($access['action'] ?? null) === 'redirect' && is_string($redirectUrl) && $redirectUrl !== '') {
                $this->redirect($redirectUrl);
            } else {
                $code = $access['params']['code'] ?? 403;
                $message = $access['params']['message'] ?? 'Unauthorized';
                $this->abort(is_int($code) ? $code : 403, is_string($message) ? $message : 'Unauthorized');
            }
            return false;
        }

        $effectiveSlot = $slot ?? $targetClass::getDefaultSlot();

        $layout = $this->getLayout();
        $currentScreen = $layout?->getActiveScreen($effectiveSlot);
        if ($currentScreen === null && in_array($effectiveSlot, ['main', 'content', 'center'], true)) {
            $currentScreen = UIStateManager::getClientCurrentScreenClass();
        }

        // If the target screen is already active in the slot, no need to reload unless forced
        if (!$force && $currentScreen !== null) {
            $currentClass = class_exists($currentScreen) ? $currentScreen : static::resolveScreenClassFromSlug($currentScreen);
            if ($currentClass === $targetClass) {
                return true;
            }
        }

        $routePath = $targetClass::getRoutePath();
        $routeSlug = static::resolveScreenSlug($targetClass);

        // 1. Locate slot container from layout instance if available
        if ($layout !== null) {
            $layout->setActiveScreen($effectiveSlot, $targetClass);
            if ($layout->getSlot($effectiveSlot) !== null) {
                return $layout->showInto($targetClass, $effectiveSlot, $params, $updateBrowserUrl, $force);
            }
        }

        // 2. Fallback: Search in the current screen's component tree
        $targetContainer = null;
        if (isset($this->container)) {
            $found = $this->container->findByName($effectiveSlot);
            if ($found instanceof Container) {
                $targetContainer = $found;
            } elseif ($effectiveSlot === 'main' || $effectiveSlot === 'content') {
                $foundContent = $this->container->findByName('content_container');
                if ($foundContent instanceof Container) {
                    $targetContainer = $foundContent;
                }
            }
        }

        // Only clear and embed locally if target container is physically present in this screen's tree
        if ($targetContainer instanceof Container) {
            $targetContainer->clear();
            self::embedInto($targetClass, $targetContainer);
        }

        // Update browser URL and instruct frontend to load screen into slot
        if ($updateBrowserUrl && in_array($effectiveSlot, ['main', 'content', 'center'], true)) {
            UIStateManager::setClientCurrentScreen($routePath, $targetClass);
            $this->uiChanges()->add([
                'navigate' => [
                    'url' => $routePath,
                    'route' => $routeSlug,
                    'title' => $targetClass::getMenuLabel(),
                    'slot' => $targetContainer?->getName() ?? ($effectiveSlot === 'main' ? 'content_container' : $effectiveSlot),
                ],
            ]);
        }

        return true;
    }

    /**
     * Show a target screen inside its default slot (or specified slot).
     *
     * @param  class-string<Screen>|string  $screenClass  Target screen class or slug
     * @param  string|null  $slot  Optional slot override
     * @param  array<int|string, mixed>  $params  Parameters passed to buildBaseUI
     */
    public function show(string $screenClass, ?string $slot = null, array $params = []): bool
    {
        return $this->showInto($screenClass, $slot, $params);
    }

    /**
     * Navigate to a target screen inside the active layout shell.
     *
     * @param  class-string<Screen>|string  $screenClass  Target screen class or slug
     * @param  array<int|string, mixed>  $params  Parameters passed to buildBaseUI
     * @param  string|null  $slot  Optional slot override (defaults to target's $defaultSlot)
     */
    public function navigate(string $screenClass, array $params = [], ?string $slot = null): bool
    {
        return $this->show($screenClass, $slot, $params);
    }

    /**
     * @phpstan-assert-if-true array<int|string, array<string, mixed>> $ui
     */
    private function isTypedCachedScreenSnapshot(mixed $ui): bool
    {
        if (!\is_array($ui)) {
            return false;
        }

        foreach ($ui as $component) {
            if (!\is_array($component)) {
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
            if (!$isRoot && \is_int($parent) && !isset($ui[$parent]) && !isset($ui[(string) $parent])) {
                return false;
            }

            if (($component['type'] ?? null) !== 'table') {
                continue;
            }

            $rowsContainerId = $component['rows_container'] ?? null;
            $headerRowId = $component['header_row'] ?? null;

            if (!\is_int($rowsContainerId) || !isset($ui[$rowsContainerId])) {
                return false;
            }

            if (($ui[$rowsContainerId]['parent'] ?? null) !== $componentId) {
                return false;
            }

            if (!\is_int($headerRowId) || !isset($ui[$headerRowId])) {
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
            if (!\is_string($type) || $type === '') {
                throw new RuntimeException('Unknown component type.');
            }

            $className = $this->mapTypeToClass($type);
            if (!$className) {
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

            if (!\is_int($parentId) && !\is_string($parentId)) {
                continue;
            }

            if (!$parentId || !isset($components[$parentId])) {
                continue;
            }

            $components[$parentId]->connectChild($component);
        }

        // Third pass: post-connection initialization
        foreach ($components as $component) {
            $component->postConnect();
        }

        if (!$rootContainer) {
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

        if (method_exists(UIStateManager::class, 'storeScreenState')) {
            $state = $this->getStateVariables();
            if (!empty($state)) {
                UIStateManager::storeScreenState($contextKey, $state);
            } else {
                UIStateManager::clearScreenState($contextKey);
            }
        }
    }

    /**
     * Clear the cached screen snapshot.
     */
    public function clearCachedScreenSnapshot(): bool
    {
        $contextKey = $this->getContextIdentifier();
        if (method_exists(UIStateManager::class, 'clearScreenState')) {
            UIStateManager::clearScreenState($contextKey);
        }

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
                if ($propertyType && !$propertyType->allowsNull()) {
                    // Get the type name (only ReflectionNamedType has getName)
                    if (!($propertyType instanceof \ReflectionNamedType)) {
                        continue;
                    }
                    $typeName = $propertyType->getName();
                    $isPrimitive = in_array($typeName, ['int', 'float', 'string', 'bool', 'array']);
                    if ($isPrimitive) {
                        $value = $property->getValue($this);
                        if (str_ends_with($propertyName, '_crypt')) {
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
                if (!$property->isInitialized($this)) {
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
            if (!str_starts_with($propertyName, 'state_')) {
                continue;
            }

            if (!array_key_exists($propertyName, $state)) {
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

    /**
     * Generic handler for 'close_modal' action
     *
     * @param  array<string, mixed>  $params
     */
    public function onCloseModal(array $params): void
    {
        $this->closeModal();
    }

    /**
     * Sends 'close_modal' action to front and clears modal snapshot from cache.
     */
    public function closeModal(): void
    {
        $this->uiChanges()->add([
            'action' => 'close_modal',
        ]);

        if ($this->parent === 'modal') {
            $this->clearCachedScreenSnapshot();
            try {
                UIStateManager::removeClientOpenedScreen($this->getScreenComponentId());
            } catch (\Throwable) {
                // Ignore if container is not initialized
            }
        }

        $popped = UIStateManager::popClientActiveModal();
        if ($popped !== null) {
            $layerIndex = $popped['layer_index'];
            $contextKey = $layerIndex > 0 ? $popped['modal_class'] . '@' . $layerIndex : $popped['modal_class'];
            UIStateManager::clear($contextKey);
        }
    }

    /**
     * Close the current modal and return data to the caller screen.
     *
     * @param  string|array<string, mixed>|null  $action  Specific caller action method, or parameters array if action is omitted
     * @param  array<string, mixed>  $parameters  Parameters to pass to caller's handler
     */
    protected function returnToCaller(string|array|null $action = null, array $parameters = []): void
    {
        if (is_array($action)) {
            $parameters = $action;
            $action = null;
        }

        $targetAction = $action ?? $this->callbackAction;
        $callerScreenClass = $this->callerScreenClass;

        if ($callerScreenClass === null || $targetAction === null) {
            $activeModal = UIStateManager::getClientActiveModal();
            if ($activeModal !== null) {
                /** @var class-string<Screen>|null $resolvedCaller */
                $resolvedCaller = $activeModal['caller_screen_class'];
                $callerScreenClass ??= $resolvedCaller;
                $targetAction ??= $activeModal['callback_action'];
            }
        }

        // Close modal and purge modal cache
        $this->closeModal();

        // If caller and action are resolved, execute caller action
        if ($callerScreenClass !== null && $targetAction !== null && class_exists($callerScreenClass)) {
            if (!str_starts_with($targetAction, 'on')) {
                $targetAction = 'on' . str_replace(' ', '', ucwords(str_replace('_', ' ', $targetAction)));
            }

            $caller = self::make($callerScreenClass);
            $caller->handleAction(
                method: $targetAction,
                parameters: $parameters,
                incomingStorage: $this->incomingStorage,
            );
        }
    }

    /**
     * Requests to front to renderize a toast type message.
     */
    public function toast(
        string $message,
        string $type = 'info',
        int $duration = 4000,
        string $openEffect = 'fade',
        string $showEffect = 'bounce',
        string $closeEffect = 'fade',
        string $position = 'top-middle'
    ): void {
        $this->uiChanges()->add([
            'toast' => [
                'message' => $message,
                'type' => $type,
                'duration' => $duration,
                'open_effect' => $openEffect,
                'show_effect' => $showEffect,
                'close_effect' => $closeEffect,
                'position' => $position,
            ],
        ]);
    }

    /**
     * Requests to front to perform a redirect to the given URL.
     *
     * If no URL is provided, it will use Laravel's intended redirect
     * (the previous URL or the default URL if none).
     *
     * @param  string|null  $url  The URL to redirect to, or null to use intended redirect
     */
    public function redirect(?string $url = null): void
    {
        // If no URL provided, use Laravel's intended redirect (previous URL or default)
        if ($url === null) {
            $url = redirect()->intended('/')->getTargetUrl();
        }

        $this->uiChanges()->add([
            'redirect' => $url,
        ]);
    }

    /**
     * Requests to front to display an error message.
     *
     * @param  int  $statusCode  The HTTP status code (e.g., 403 for forbidden, 404 for not found)
     * @param  string  $message  The error message to display
     */
    protected function abort(int $statusCode, string $message = ''): void
    {
        $this->uiChanges()->add([
            'abort' => [
                'status_code' => $statusCode,
                'message' => $message,
            ],
        ]);
    }

    /**
     * Requet to front to change the current theme (e.g., 'light' or 'dark').
     */
    protected function changeTheme(string $theme): void
    {
        $this->uiChanges()->add([
            'change_theme' => $theme,
        ]);
    }

    /**
     * Requests to front to change the current language (e.g., 'en' or 'es').
     */
    protected function changeLanguage(string $language): void
    {
        app()->setLocale($language);

        // TODO: In the following way, the language is being stored on the front. It should be stored user's preferences.
        $this->uiChanges()->add([
            'change_language' => $language,
        ]);
    }

    /**
     * @param  array<string, mixed>  $content
     */
    public function updateModal(array $content): void
    {
        $this->uiChanges()->add([
            'update_modal' => $content,
        ]);
    }

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
        if (!isset($this->container)) {
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
