<?php
namespace Idei\Usim;

use Idei\Usim\Components\Button;
use Idei\Usim\Components\Card;
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
use Idei\Usim\Components\Uploader;
use Idei\Usim\Contracts\UIElement;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\Enums\Visibility;
use Idei\Usim\Support\UIDiffer;
use Idei\Usim\Support\UIIdGenerator;
use Idei\Usim\Support\UIStateManager;
use Idei\Usim\UI;
use Idei\Usim\UIChangesCollector;
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
     */
    protected ?array $oldUI = null;

    /**
     * State after modifications (for diff calculation)
     */
    protected ?array $newUI = null;

    /**
     * Query parameters from the request
     */
    protected array $queryParams = [];

    /**
     * Screen visibility level. Used by the framework to determine access and menu display.
     * @var Visibility
     */
    public static Visibility $visibility = Visibility::AUTHENTICATED;

    protected function uiChanges(): UIChangesCollector
    {
        return app(UIChangesCollector::class);
    }

    /**
     * Check access permission and return result structure.
     * This method is static to allow checking permissions without instantiating the service.
     *
     * @return array{allowed: bool, action: ?string, params: array}
     */
    public static function checkAccess(): array
    {
        // 1. Check authorization logic
        if (static::authorize()) {
            return ['allowed' => true, 'action' => null, 'params' => []];
        }

        // 2. Handle failure based on authentication state
        if (!Auth::check()) {
            return [
                'allowed' => false,
                'action' => 'redirect',
                'params' => [
                    'url' => url('/auth/login'),
                    'message' => 'Please login to access this page.'
                ]
            ];
        }

        // 3. Authenticated but unauthorized
        return [
            'allowed' => false,
            'action' => 'abort',
            'params' => [
                'code' => 403,
                'message' => 'Unauthorized: Insufficient permissions.'
            ]
        ];
    }

    /**
     * Determine if the user is authorized to access this service.
     *
     * @return bool
     */
    public static function authorize(): bool
    {
        return true;
    }

    /**
     * Helper to require authentication.
     * Use this inside your authorize() method.
     *
     * @return bool
     */
    protected static function requireAuth(): bool
    {
        if (!Auth::check()) {
            return false;
        }

        return true;
    }

    /**
     * Helper to require a role (implies authentication).
     * Use this inside your authorize() method.
     *
     * @param string|array $roles
     * @param string $guard
     * @return bool
     */
    protected static function requireRole(string|array $roles, ?string $guard = null): bool
    {
        // Implicitly require authentication first
        if (!self::requireAuth()) {
            return false;
        }

        /** @var mixed $user */
        $user = Auth::guard($guard)->user();

        if (!$user || !method_exists($user, 'hasAnyRole')) {
            // user exists but trait is missing or logic fails
            return false;
        }

        if (!$user->hasAnyRole($roles)) {
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
     * @param string|array $permissions
     * @param string $guard
     * @return bool
     */
    protected static function requirePermission(string|array $permissions, ?string $guard = null): bool
    {
        // Implicitly require authentication first
        if (!self::requireAuth()) {
            return false;
        }

        /** @var mixed $user */
        $user = Auth::guard($guard)->user();

        if (!$user || !method_exists($user, 'hasAnyPermission')) {
            return false;
        }

        if (!$user->hasAnyPermission($permissions)) {
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
            $translationKey = "screen.permissions.$permission";
            $ret[$permission] = $translationKey;
        }

        return $ret;
    }

    /**
     * Determinates if the currently authenticated user has a specific permission within the context of this screen.
     *
     * @param string $permission The short permission name (e.g., "publish") that will be resolved to a full permission
     * string based on the screen's slug (e.g., "blog.post_management.publish").
     * @return bool
     */
    public function userCan(string $permission): bool
    {
        if (static::$visibility === Visibility::PUBLIC) {
            return true;
        }

        if (!Auth::check()) {
            return false;
        }

        $user = Auth::user();

        // If the permission doesn't contain a dot, we assume it's a local permission and resolve
        //  it using the screen's slug.
        if (!str_contains($permission, '.')) {
            $permission = static::getScreenSlug() . '.' . $permission;
        }

        return method_exists($user, 'hasPermissionTo') && $user->hasPermissionTo($permission);
    }

    /**
     * Get the menu label for this screen.
     * Defaults to the class name (spaced and capitalized).
     * Override this in child classes to customize.
     */
    public static function getMenuLabel(): string
    {
        return class_basename(static::class);
    }

    /**
     * Get the menu icon for this screen.
     * Override this in child classes to customize.
     */
    public static function getMenuIcon(): ?string
    {
        return null;
    }

    /**
     * Get the route path for this screen.
     * Auto-generates based on namespace location relative to Screen root.
     * E.g. App\UI\Screens\Admin\UsersManager -> /admin/dashboard
     */
    public static function getRoutePath(): string
    {
        $class = static::class;
        $prefix = config('usim.screens_namespace', 'App\\UI\\Screens');

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
     * @param mixed ...$params Optional parameters for user Interface construction
     */
    abstract protected function buildBaseUI(Container $container, ...$params): void;

    protected function postLoadUI(): void
    {
    }

    /**
     * Initialize event context
     *
     * Called by UIEventController before invoking event handler.
     * Loads user Interface container and captures state for diff calculation.
     * Also injects storage values and component references into protected properties.
     *
     * @param array $incomingStorage Storage data from frontend (decrypted)
     * @return void
     */
    public function initializeEventContext(array $incomingStorage = [], array $queryParams = [], bool $debug = false): void
    {
        $this->container = $this->reconstructScreenTreeFromCache($debug);
        $this->oldUI = $this->container->toJson();

        $this->queryParams = $queryParams;

        // Inject storage values into protected properties (store_* variables)
        $this->injectStorageValues($incomingStorage);

        // Inject component references into protected properties
        $this->injectComponentReferences();
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
     * @param array $incomingStorage Storage data from frontend
     * @return void
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
     *
     * @return void
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
                    // Component not found and property is not nullable
                    throw new RuntimeException(
                        "Component '{$componentName}' not found in user Interface container. " .
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
     *
     * @return void
     */
    public function finalizeEventContext(bool $reload = false, bool $debug = false): void
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
     * @return array Indexed diff response
     */
    protected function buildDiffResponse(bool $reload = false): array
    {
        $diff = $reload ?
            UIDiffer::compare([], $this->newUI) :
            UIDiffer::compare($this->oldUI, $this->newUI);

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
     * Get stored user Interface state, regenerate if missing
     *
     * @param mixed ...$params Optional parameters passed to buildBaseUI
     * @return array user Interface structure in JSON format
     */
    protected function getCachedScreenSnapshot(string $parent = 'main', bool $debug = false, ...$params): array
    {
        // Check if user Interface exists in cache
        $cachedUI = UIStateManager::get(static::class);

        if ($cachedUI !== null && $this->isValidCachedScreenSnapshot($cachedUI)) {
            return $cachedUI;
        }

        if ($cachedUI !== null) {
            UIStateManager::clear(static::class);
        }

        $current_class = static::class;
        $current_class_slug = strtolower(str_replace('\\', '_', $current_class));
        $container = UI::container($current_class_slug, $current_class)
            ->parent($parent)
            ->padding('30')
            ->layout(LayoutType::VERTICAL)
            ->justifyContent('center')
            ->alignItems('center');

        // Generate and cache new user Interface
        $this->buildBaseUI($container, ...$params);

        $ui = $container
            ->root(true)
            // ->parent($parent)   // TODO: Acá está el problema.
            ->toJson();

        UIStateManager::store(static::class, $ui);

        return $ui;
    }

    /**
     * Cached snapshots can become stale after structural screen changes.
     * Reject snapshots that reference component parents not present in the payload
     * or table internals that no longer form a consistent subtree.
     */
    private function isValidCachedScreenSnapshot(array $ui): bool
    {
        foreach ($ui as $componentId => $component) {
            if (!\is_array($component)) {
                continue;
            }

            $parent = $component['parent'] ?? null;
            if (\is_int($parent) && !isset($ui[$parent]) && !isset($ui[(string) $parent])) {
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
     */
    protected function reconstructScreenTreeFromCache(bool $debug = false): Container
    {
        // Always get JSON from cache and reconstruct container
        // This ensures we get the latest state after events modify it
        $jsonUI = $this->getCachedScreenSnapshot(debug: $debug);
        // Log::info(json_encode($jsonUI));

        // Reconstruct container from JSON
        return $this->reconstructContainerFromJson($jsonUI);
    }

    /**
     * Reconstruct UI container from JSON array
     *
     * @param array $jsonUI JSON representation of UI
     * @return Container Reconstructed container
     */
    private function reconstructContainerFromJson(array $jsonUI): Container
    {
        $components = [];
        $rootContainer = null;

        // UIDebug::debug("Reconstructing UI Container from JSON", $jsonUI);

        // First pass: instantiate all components
        foreach ($jsonUI as $id => $component) {
            $type = $component['type'];
            $className = $this->mapTypeToClass($type);
            if (!$className) {
                throw new RuntimeException("Unknown component type '{$type}'.");
            }

            // Reserve IDs from cached snapshots so future auto-generated IDs
            // in this request do not collide with already deserialized components.
            if (is_numeric($id)) {
                UIIdGenerator::reserveContextId(static::class, (int) $id);
            }

            $components[$id] = $className::deserialize($id, $component);
        }

        // Second pass: set up parent-child relationships
        foreach ($components as $id => $component) {
            $parentId = $jsonUI[$id]['parent'] ?? null;

            if ($component->isContainer() && $component->isRoot()) {
                $rootContainer = $component;
            }

            // Detached components (parent=null) are valid during incremental remove operations.
            // Ignore them while rebuilding the live tree from cache.
            if ($parentId === null || $parentId === '') {
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
            throw new RuntimeException("No root container found in UI JSON.");
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
            'calendar' => \Idei\Usim\Components\Calendar::class,
            'carousel' => \Idei\Usim\Components\Carousel::class,
            'textarea' => 'Idei\\Usim\\Components\\Textarea',
            'split' => Split::class,
            default => null,
        };
    }

    /**
     * Store UI state in cache
     *
     * @param Container $container UI container to store
     * @return void
     */
    protected function cacheScreenSnapshot(Container $container): void
    {
        UIStateManager::store(static::class, $container->toJson());
    }

    /**
     * Clear the cached screen snapshot.
     *
     * @return bool
     */
    public function clearCachedScreenSnapshot(): bool
    {
        return UIStateManager::clear(static::class);
    }

    /**
     * Allow child classes to react when the screen is reset.
     *
     * @return void
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
        $ui = $this->getCachedScreenSnapshot();

        // Find the first container (main container that represents the screen)
        foreach ($ui as $id => $component) {
            if ($component['type'] === 'container') {
                return (int) $id;
            }
        }

        // Fallback: generate deterministic ID from screen class name
        return UIIdGenerator::generateFromName(
            static::class,
            'screen_root'
        );
    }

    /**
     * @deprecated Use getCachedScreenSnapshot() instead.
     */
    protected function getStoredUI(string $parent = 'main', bool $debug = false, ...$params): array
    {
        return $this->getCachedScreenSnapshot($parent, $debug, ...$params);
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
     * @return array Associative array with the variables to be stored on the frontend
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
     * Generic handler for 'close_modal' action
     */
    public function onCloseModal(array $params): void
    {
        $this->closeModal();
    }

    /**
     * Sends 'close_modal' action to front.
     *
     * @return void
     */
    protected function closeModal(): void
    {
        $this->uiChanges()->add([
            'action' => 'close_modal',
        ]);
    }

    /**
     * Requests to front to renderize a toast type message.
     *
     * @param string $message
     * @param string $type
     * @param int $duration
     * @param string $openEffect
     * @param string $showEffect
     * @param string $closeEffect
     * @param string $position
     * @return void
     */
    protected function toast(
        string $message,
        string $type = 'info',
        int $duration = 5000,
        string $openEffect = 'fade',
        string $showEffect = 'bounce',
        string $closeEffect = 'fade',
        string $position = 'top-right'
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
     * @param string|null $url The URL to redirect to, or null to use intended redirect
     * @return void
     */
    protected function redirect(?string $url = null): void
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
     * @param int  $statusCode The HTTP status code (e.g., 403 for forbidden, 404 for not found)
     * @param string $message The error message to display
     * @return void
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
     *
     * @param string $theme
     * @return void
     */
    protected function changeTheme(string $theme): void
    {
        $this->uiChanges()->add([
            'change_theme' => $theme,
        ]);
    }

    /**
     * Requests to front to change the current language (e.g., 'en' or 'es').
     *
     * @param string $language
     * @return void
     */
    protected function changeLanguage(string $language): void
    {
        app()->setLocale($language);

        // TODO: In the following way, the language is being stored on the front. It should be stored user's preferences.
        $this->uiChanges()->add([
            'change_language' => $language,
        ]);
    }

    protected function updateModal(array $content): void
    {
        $this->uiChanges()->add([
            'update_modal' => $content,
        ]);
    }

    /**
     * Find a component by ID and return it only if it matches the expected class.
     *
     * @template T of UIElement
     * @param Container $container
     * @param int|string|null $id
     * @param class-string<T> $expectedClass
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
     * @param int|string|null $id
     * @param class-string<T> $expectedClass
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
     * @return array Empty array by default. Override to provide agent context metadata.
     */
    public function getAgentContext(): array
    {
        return [];
    }

}


