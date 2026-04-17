<?php
namespace Idei\Usim;

use Idei\Usim\Components\Button;
use Idei\Usim\Components\Card;
use Idei\Usim\Components\Checkbox;
use Idei\Usim\Components\Form;
use Idei\Usim\Components\Input;
use Idei\Usim\Components\Label;
use Idei\Usim\Components\MenuDropdown;
use Idei\Usim\Components\Select;
use Idei\Usim\Components\Table;
use Idei\Usim\Components\TableCell;
use Idei\Usim\Components\TableHeaderCell;
use Idei\Usim\Components\TableHeaderRow;
use Idei\Usim\Components\TableRow;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\UploaderBuilder;
use Idei\Usim\Contracts\UIElement;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\Support\UIDiffer;
use Idei\Usim\Support\UIIdGenerator;
use Idei\Usim\Support\UIStateManager;
use Idei\Usim\UIBuilder;
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
     * E.g. App\UI\Screens\Admin\Dashboard -> /admin/dashboard
     */
    public static function getRoutePath(): string
    {
        $class = static::class;
        $prefix = config('ui-services.screens_namespace', 'App\\UI\\Screens');

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
     * @return Container Base user Interface structure
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
        $this->container = $this->getUIContainer($debug);
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
            if (!$propertyType || $propertyType->isBuiltin()) {
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
        $this->storeUI($this->container);

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
            $changes['_id'] = $componentId;

            // Always include 'type' from newUI so frontend knows how to handle the change
            if (isset($this->newUI[$componentId]['type'])) {
                $changes['type'] = $this->newUI[$componentId]['type'];
            }

            $result[$componentId] = $changes;
        }

        return $result;
    }

    // /**
    //  * Get the user Interface structure
    //  *
    //  * Returns the user Interface from cache or regenerates if not exists.
    //  * This is the standard public method to retrieve user Interface for all services.
    //  *
    //  * @param mixed ...$params Optional parameters that can be used by child classes
    //  * @return array user Interface structure in JSON format
    //  */
    // public function getUI(string $parent = 'main', ...$params): array
    // {
    //     $ui = $this->getStoredUI($parent, ...$params);
    //     return $ui;
    // }

    /**
     * Get stored user Interface state, regenerate if missing
     *
     * @param mixed ...$params Optional parameters passed to buildBaseUI
     * @return array user Interface structure in JSON format
     */
    protected function getStoredUI(string $parent = 'main', bool $debug = false, ...$params): array
    {
        // Check if user Interface exists in cache
        $cachedUI = UIStateManager::get(static::class);

        if ($cachedUI !== null) {
            return $cachedUI;
        }

        $current_class = static::class;
        $current_class_slug = strtolower(str_replace('\\', '_', $current_class));
        $container = UIBuilder::container($current_class_slug, $current_class)
            ->parent($parent)
            ->padding(30)
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
     * Get user Interface container instance from cache, regenerate if missing
     *
     * @return Container user Interface container instance
     */
    protected function getUIContainer(bool $debug = false): Container
    {
        // Always get JSON from cache and reconstruct container
        // This ensures we get the latest state after events modify it
        $jsonUI = $this->getStoredUI(debug: $debug);
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
            $components[$id] = $className::deserialize($id, $component);
        }

        // Second pass: set up parent-child relationships
        foreach ($components as $id => $component) {
            $parentId = $jsonUI[$id]['parent'] ?? null;

            if ($component->isContainer() && $component->isRoot()) {
                $rootContainer = $component;
            }

            if (!$parentId) {
                throw new RuntimeException("Component '{$id}' has no parent defined.");
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
            'uploader' => UploaderBuilder::class,
            'calendar' => \Idei\Usim\Components\Calendar::class,
            'carousel' => \Idei\Usim\Components\Carousel::class,
            'default' => null,
        };
    }

    /**
     * Store UI state in cache
     *
     * @param Container $ui UI container to store
     * @return void
     */
    protected function storeUI(Container $ui): void
    {
        UIStateManager::store(static::class, $ui->toJson());
    }

    /**
     * Clear stored UI state
     *
     * @return void
     */
    public function clearStoredUI(): void
    {
        UIStateManager::clear(static::class);
    }

    /**
     * Allow child classes to react when the service is reset.
     *
     * @return void
     */
    public function onResetService(): void
    {
    }

    /**
     * Get the service component ID
     * Returns the ID of the main container, which represents this service
     * Used for modal callbacks to route events back to this service
     *
     * @return int Service component ID
     */
    protected function getServiceComponentId(): int
    {
        $ui = $this->getStoredUI();

        // Find the first container (main container that represents the service)
        foreach ($ui as $id => $component) {
            if ($component['type'] === 'container') {
                return (int) $id;
            }
        }

        // Fallback: generate deterministic ID from service class name
        return UIIdGenerator::generateFromName(
            static::class,
            'service_root'
        );
    }

    /**
     * Uses reflection to scan private and protected properties whose names start with the "store_"
     * prefix and whose type hints are non-nullable primitive types (int, float, string, bool) or array.
     * Properties ending with "_crypt" are automatically encrypted before storage.
     * It then builds an associative array with the following structure:
     *
     * [
     *   'storage' => [
     *      [app_id] => 'encrypted_json_string',
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
}
