<?php

namespace Idei\Usim\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * UI State Manager
 *
 * Centralized management of UI state caching.
 * Provides methods to store, retrieve, and update UI component state.
 *
 * Usage:
 * - Store entire UI: UIStateManager::store($serviceClass, $uiArray)
 * - Get entire UI: UIStateManager::get($serviceClass)
 * - Update component: UIStateManager::updateComponent($serviceClass, $componentId, $updates)
 * - Get component property: UIStateManager::getComponentProperty($serviceClass, $componentName, $property)
 */
class UIStateManager
{
    /**
     * Cookie name for client identification
     */
    public const CLIENT_ID_COOKIE = 'ui_client_id';

    /**
     * Cookie lifetime (1 year in minutes)
     */
    public const COOKIE_LIFETIME = 525600;

    /**
     * Get or create a unique client identifier
     *
     * This identifier persists across sessions and survives logout,
     * allowing UI preferences to be maintained per device/browser.
     *
     * @return string Client UUID
     */
    public static function getOrCreateClientId(): string
    {
        $clientId = request()->cookie(self::CLIENT_ID_COOKIE);

        // If a client cookie is present, it must be the source of truth
        // for this request (important for test scenarios and multi-tab flows).
        if (\is_string($clientId) && $clientId !== '') {
            session()->put(self::CLIENT_ID_COOKIE, $clientId);

            return $clientId;
        }

        $sessionClientId = session()->get(self::CLIENT_ID_COOKIE);
        if (\is_string($sessionClientId) && $sessionClientId !== '') {
            return $sessionClientId;
        }

        // Generate new UUID for this client
        $clientId = (string) Str::uuid();

        // Queue functional cookie (no consent required - essential for UI preferences)
        cookie()->queue(
            name: self::CLIENT_ID_COOKIE,
            value: $clientId,
            minutes: self::COOKIE_LIFETIME,
            path: '/',
            domain: null,
            secure: request()->secure(), // HTTPS only in production
            httpOnly: true, // Not accessible from JavaScript
            sameSite: 'lax' // CSRF protection
        );

        session()->put(self::CLIENT_ID_COOKIE, $clientId);

        return $clientId;
    }

    /**
     * Generate cache key for a service
     *
     * @param  string  $serviceClass  Full service class name
     * @return string Cache key
     */
    public static function getCacheKey(?string $serviceClass = null, string $prefix = 'ui_state'): string
    {
        $serviceBaseName = $serviceClass ? class_basename($serviceClass) : '';
        $clientId = self::getOrCreateClientId();

        return "{$prefix}:{$serviceBaseName}:{$clientId}";
    }

    // /**
    //  * Store root component ID and its parent container in session
    //  *
    //  * @param string $parent Parent container name (e.g., 'main', 'modal')
    //  * @param string $rootComponentId Root component ID
    //  * @return void
    //  */
    // private static function storeRootComponentId(string $parent, string $rootComponentId): void
    // {
    //     $parents = session()->get('ui_parents', []);
    //     $parents[$parent] = $rootComponentId;
    //     session()->put('ui_parents', $parents);
    // }

    // /**
    //  * Get root components from session
    //  *
    //  * @return array Root components array
    //  */
    // public static function getRootComponents(): array
    // {
    //     return session()->get('ui_parents', []);
    // }

    /**
     * Store UI state in cache
     *
     * @param  string  $serviceClass  Service class name
     * @param  array<array-key, mixed>  $uiState  UI state array (indexed by component ID)
     * @return bool Success
     */
    public static function store(string $serviceClass, array $uiState): bool
    {
        if (empty($uiState)) {
            return false;
        }

        // Get TTL from environment or use default
        $ttlConfig = config('usim.ui_cache_ttl', 60);
        $ttl = \is_int($ttlConfig) ? $ttlConfig : 60;
        if (\is_string($ttlConfig) && ctype_digit($ttlConfig)) {
            $ttl = (int) $ttlConfig;
        }
        $encodedState = json_encode($uiState);

        // Store main UI state
        $cacheKey = self::getCacheKey($serviceClass);
        $result = Cache::put($cacheKey, $encodedState, $ttl);
        $logLevel = $result ? 'warning' : 'error';

        // UIDebug::$logLevel("Stored UI State of {$serviceClass}", [
        //     'result' => $result ? 'CACHED' : 'NOT CACHED',
        //     'cache_key' => $cacheKey,
        //     'ids' => implode(', ', array_keys($uiState)),
        //     'caller' => self::getCallerServiceInfo(),
        // ]);

        // Store root component ID and its parent container
        $firstKey = array_key_first($uiState);
        // if (isset($uiState[$firstKey]['parent'])) {
        //     self::storeRootComponentId(
        //         $uiState[$firstKey]['parent'],
        //         (string) $firstKey
        //     );
        // }

        // invoca a una funcion que cachea el $firstKey en un arreglo asociativo específico al clientId,
        // para luego poder recuperar todas las screens que el cliente tiene abiertas, y así enviar eventos solo a esas screens.
        self::storeClientOpenedScreens($firstKey);

        return $result;
    }

    /**
     * Store each screen opened by the user
     */
    private static function storeClientOpenedScreens(string $screenId): void
    {
        $clientId = self::getOrCreateClientId();
        $cacheKey = "ui_open_screens:{$clientId}";
        $ttlConfig = config('usim.ui_cache_ttl', 60);
        $ttl = \is_int($ttlConfig) ? $ttlConfig : 60;
        if (\is_string($ttlConfig) && ctype_digit($ttlConfig)) {
            $ttl = (int) $ttlConfig;
        }
        $openedScreens = Cache::get($cacheKey, []);
        if (! \is_array($openedScreens)) {
            $openedScreens = [];
        }
        $openedScreens[$screenId] = true;
        Cache::put($cacheKey, $openedScreens, $ttl);
    }

    /** @return list<string> */
    public static function getClientOpenedScreens(?string $clientId = null): array
    {
        $clientId ??= self::getOrCreateClientId();
        $cacheKey = "ui_open_screens:{$clientId}";
        $openedScreens = Cache::get($cacheKey, []);

        return \is_array($openedScreens) ? array_keys($openedScreens) : [];
    }

    /**
     * Remove a screen from client opened screens tracking.
     */
    public static function removeClientOpenedScreen(string|int $screenId, ?string $clientId = null): void
    {
        $clientId ??= self::getOrCreateClientId();
        $cacheKey = "ui_open_screens:{$clientId}";
        $ttlConfig = config('usim.ui_cache_ttl', 60);
        $ttl = \is_int($ttlConfig) ? $ttlConfig : 60;
        if (\is_string($ttlConfig) && ctype_digit($ttlConfig)) {
            $ttl = (int) $ttlConfig;
        }

        $openedScreens = Cache::get($cacheKey, []);
        if (\is_array($openedScreens) && isset($openedScreens[(string) $screenId])) {
            unset($openedScreens[(string) $screenId]);
            Cache::put($cacheKey, $openedScreens, $ttl);
        }
    }

    /**
     * Store active modal metadata for the client.
     *
     * @param  array<int|string, mixed>  $params
     */
    public static function storeClientActiveModal(
        string $modalClass,
        ?int $callerScreenId = null,
        ?string $callbackAction = null,
        array $params = [],
        ?string $clientId = null
    ): bool {
        $clientId ??= self::getOrCreateClientId();
        $cacheKey = "ui_active_modal:{$clientId}";
        $ttlConfig = config('usim.ui_cache_ttl', 60);
        $ttl = \is_int($ttlConfig) ? $ttlConfig : 60;
        if (\is_string($ttlConfig) && ctype_digit($ttlConfig)) {
            $ttl = (int) $ttlConfig;
        }

        $callerScreenClass = null;
        if ($callerScreenId !== null) {
            $context = UIIdGenerator::getContextFromId($callerScreenId);
            if (\is_string($context) && $context !== '') {
                $callerScreenClass = $context;
            }
        }

        $data = [
            'modal_class' => $modalClass,
            'caller_screen_id' => $callerScreenId,
            'caller_screen_class' => $callerScreenClass,
            'callback_action' => $callbackAction,
            'params' => $params,
        ];

        return Cache::put($cacheKey, $data, $ttl);
    }

    /**
     * Get active modal metadata for the client.
     *
     * @return array{modal_class: string, caller_screen_id: ?int, caller_screen_class: ?string, callback_action: ?string, params: array<int|string, mixed>}|null
     */
    public static function getClientActiveModal(?string $clientId = null): ?array
    {
        $clientId ??= self::getOrCreateClientId();
        $cacheKey = "ui_active_modal:{$clientId}";
        $data = Cache::get($cacheKey);

        if (! \is_array($data) || empty($data['modal_class']) || ! \is_string($data['modal_class'])) {
            return null;
        }

        return [
            'modal_class' => $data['modal_class'],
            'caller_screen_id' => isset($data['caller_screen_id']) && is_numeric($data['caller_screen_id']) ? (int) $data['caller_screen_id'] : null,
            'caller_screen_class' => isset($data['caller_screen_class']) && \is_string($data['caller_screen_class']) ? $data['caller_screen_class'] : null,
            'callback_action' => isset($data['callback_action']) && \is_string($data['callback_action']) ? $data['callback_action'] : null,
            'params' => isset($data['params']) && \is_array($data['params']) ? $data['params'] : [],
        ];
    }

    /**
     * Clear active modal metadata for the client.
     */
    public static function clearClientActiveModal(?string $clientId = null): bool
    {
        $clientId ??= self::getOrCreateClientId();
        $cacheKey = "ui_active_modal:{$clientId}";

        return Cache::forget($cacheKey);
    }

    /**
     * Get UI state from cache
     *
     * @param  string  $serviceClass  Service class name
     * @return array<string, mixed>|null UI state array or null if not found
     */
    public static function get(string $serviceClass): ?array
    {
        $cacheKey = self::getCacheKey($serviceClass);
        $content = Cache::get($cacheKey);
        $result = null;

        if (\is_string($content) && $content !== '') {
            /** @var array<string, mixed>|null $decodedState */
            $decodedState = json_decode($content, true);
            $result = \is_array($decodedState) ? $decodedState : null;
        }

        $logLevel = $result !== null ? 'info' : 'error';

        // UIDebug::$logLevel("Retrieving UI State of {$serviceClass}", [
        //     'result' => $result !== null ? 'FOUND' : 'NOT FOUND',
        //     'cache_key' => $cacheKey,
        //     'service_class' => $serviceClass,
        //     'caller' => self::getCallerServiceInfo(),
        // ]);

        return $result;
    }

    /**
     * Clear UI state from cache
     *
     * @param  string  $serviceClass  Service class name
     * @return bool Success
     */
    public static function clear(string $serviceClass): bool
    {
        $cacheKey = self::getCacheKey($serviceClass);

        return Cache::forget($cacheKey);
    }

    /**
     * Set the authentication token in the cache
     *
     * @param  string|null  $token  The authentication token
     * @return bool Success
     */
    public static function setAuthToken(?string $token): bool
    {
        $cacheKey = self::getCacheKey(prefix: 'ui_auth_token');
        $ttlConfig = config('usim.ui_cache_ttl', 60);
        $ttl = \is_int($ttlConfig) ? $ttlConfig : 60;
        if (\is_string($ttlConfig) && ctype_digit($ttlConfig)) {
            $ttl = (int) $ttlConfig;
        }
        Cache::put($cacheKey, $token, $ttl);

        return true;
    }

    public static function getAuthToken(): ?string
    {
        $cacheKey = self::getCacheKey(prefix: 'ui_auth_token');
        $token = Cache::get($cacheKey);

        return \is_string($token) ? $token : null;
    }

    public static function storeKeyValue(string $key, mixed $value): bool
    {
        $cacheKey = self::getCacheKey(prefix: "ui_key_{$key}");
        $ttlConfig = config('usim.ui_cache_ttl', 60);
        $ttl = \is_int($ttlConfig) ? $ttlConfig : 60;
        if (\is_string($ttlConfig) && ctype_digit($ttlConfig)) {
            $ttl = (int) $ttlConfig;
        }
        Cache::put($cacheKey, $value, $ttl);

        return true;
    }

    public static function getKeyValue(string $key): mixed
    {
        $cacheKey = self::getCacheKey(prefix: "ui_key_{$key}");

        return Cache::get($cacheKey);
    }

    public static function clearKeyValue(string $key): bool
    {
        $cacheKey = self::getCacheKey(prefix: "ui_key_{$key}");

        return Cache::forget($cacheKey);
    }
}
