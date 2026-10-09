<?php

namespace Idei\Usim\Concerns;

use Idei\Usim\Contracts\DeviceSecurityGuardInterface;
use Idei\Usim\Contracts\ScreenAuthorizerInterface;
use Idei\Usim\Contracts\UnitContextResolverInterface;
use Idei\Usim\Enums\Visibility;
use Idei\Usim\Models\UsimUnit;
use Idei\Usim\Screen;
use Idei\Usim\Support\UIStateManager;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Authorization and access control logic for Screen.
 *
 * @mixin Screen
 */
trait HandlesAuthorization
{
    /**
     * Check if current user has access to this screen and handle unauthorized actions.
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

        if ($guard === 'device' && Auth::guard('device')->check()) {
            if (app()->bound(DeviceSecurityGuardInterface::class)) {
                /** @var DeviceSecurityGuardInterface $deviceSecurity */
                $deviceSecurity = app(DeviceSecurityGuardInterface::class);
                if (! $deviceSecurity->isDevicePaired()) {
                    $deviceSecurity->logoutDevice();
                }
            }
        }

        // 2. Handle failure based on authentication state
        if (! Auth::guard($guard)->check()) {
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
     * Resolve the authentication guard for this screen.
     */
    public static function getAuthGuard(): string
    {
        if (isset(static::$guard)) {
            return static::$guard;
        }

        // Screens under the Device namespace default to the 'device' guard
        if (str_contains(static::class, 'Screens\\Device\\')) {
            return 'device';
        }

        return 'web';
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
        if (! \is_object($user)) {
            return false;
        }

        $callback = [$user, $method];
        if (! \is_callable($callback)) {
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
        $slug = UIStateManager::getActiveUnit();

        if (app()->bound(UnitContextResolverInterface::class)) {
            return app(UnitContextResolverInterface::class)->resolveUserUnit($user, $slug);
        }

        $fallbackResolver = 'App\\Services\\Units\\UnitContextResolver';
        if (class_exists($fallbackResolver)) {
            return $fallbackResolver::resolve($user, $slug);
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
        if (! self::requireAuth($effectiveGuard)) {
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

        if (! self::callUserBoolMethod($user, 'hasAnyRole', $roles)) {
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
        if (! self::requireAuth($effectiveGuard)) {
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

        if (! self::callUserBoolMethod($user, 'hasAnyPermission', $permissions)) {
            return false;
        }

        return true;
    }

    /**
     * Gets a unique identifier for the screen based on its Namespace and Class.
     */
    protected static function getScreenSlug(): string
    {
        $className = static::class;
        $cleanPath = Str::after($className, 'Screens\\');
        $segments = explode('\\', $cleanPath);

        return collect($segments)
            ->map(fn ($segment) => Str::snake(Str::replaceLast('Screen', '', $segment)))
            ->implode('.');
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
     * Dynamically generates "[slug].access" permission based on screen namespace and class name.
     *
     * @return array<string, string>
     */
    final public static function resolvedPermissions(): array
    {
        $ret = [];

        if (static::$visibility !== Visibility::AUTHENTICATED) {
            return $ret;
        }

        $allPermissions = array_unique(['access', ...static::permissions()]);
        $screenContextPart = static::getScreenSlug();

        foreach ($allPermissions as $permission) {
            $permission = "$screenContextPart.$permission";
            $translationKey = "permission.$permission";
            $ret[$permission] = $translationKey;
        }

        return $ret;
    }

    /**
     * Determines if the currently authenticated user has a specific permission within the context of this screen.
     *
     * @param  UsimUnit|int|string|null  $unit
     */
    public function userCan(string $permission, mixed $unit = null): bool
    {
        if (static::$visibility === Visibility::PUBLIC) {
            return true;
        }

        if (! Auth::check()) {
            return false;
        }

        $user = Auth::user();
        if ($user === null) {
            return false;
        }

        if (method_exists($user, 'isRoot') && $user->isRoot()) {
            return true;
        }

        if (! str_contains($permission, '.')) {
            $permission = static::getScreenSlug().'.'.$permission;
        }

        if (app()->bound(ScreenAuthorizerInterface::class)) {
            return app(ScreenAuthorizerInterface::class)->can($user, $permission, $unit);
        }

        $targetUnitId = self::resolveUnitId($unit);
        if ($targetUnitId === null) {
            $activeUnitSlug = UIStateManager::getActiveUnit();
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
     * @param  UsimUnit|int|string|null  $unit
     */
    public function userHasRole(string|array $roles, mixed $unit = null): bool
    {
        if (static::$visibility === Visibility::PUBLIC) {
            return true;
        }

        if (! Auth::check()) {
            return false;
        }

        $user = Auth::user();
        if ($user === null) {
            return false;
        }

        if (method_exists($user, 'isRoot') && $user->isRoot()) {
            return true;
        }

        if (app()->bound(ScreenAuthorizerInterface::class)) {
            return app(ScreenAuthorizerInterface::class)->hasRole($user, $roles, $unit);
        }

        $targetUnitId = self::resolveUnitId($unit);
        if ($targetUnitId === null) {
            $activeUnitSlug = UIStateManager::getActiveUnit();
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
}
