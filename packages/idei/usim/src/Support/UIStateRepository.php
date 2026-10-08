<?php

namespace Idei\Usim\Support;

use Idei\Usim\Contracts\ComponentIdGeneratorInterface;
use Idei\Usim\Contracts\UIStateRepositoryInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Concrete implementation of UIStateRepositoryInterface.
 *
 * Scoped service responsible for UI state caching, client identification,
 * modal stack tracking, and active unit resolution.
 */
class UIStateRepository implements UIStateRepositoryInterface
{
    /** Cookie name for client identification */
    public const CLIENT_ID_COOKIE = 'ui_client_id';

    /** Header name for client identification (supports per-tab isolation) */
    public const CLIENT_ID_HEADER = 'X-UI-Client-Id';

    /** Cookie lifetime (1 year in minutes) */
    public const COOKIE_LIFETIME = 525600;

    public function __construct(
        protected ?ComponentIdGeneratorInterface $idGenerator = null
    ) {}

    protected function getIdGenerator(): ComponentIdGeneratorInterface
    {
        if ($this->idGenerator === null) {
            $this->idGenerator = app(ComponentIdGeneratorInterface::class);
        }

        return $this->idGenerator;
    }

    /**
     * Get or create a unique client identifier
     */
    public function getOrCreateClientId(): string
    {
        $headerClientId = request()->header(self::CLIENT_ID_HEADER);
        if (\is_string($headerClientId) && trim($headerClientId) !== '') {
            return trim($headerClientId);
        }

        $clientId = request()->cookie(self::CLIENT_ID_COOKIE);

        if (\is_string($clientId) && $clientId !== '') {
            session()->put(self::CLIENT_ID_COOKIE, $clientId);

            return $clientId;
        }

        $sessionClientId = session()->get(self::CLIENT_ID_COOKIE);
        if (\is_string($sessionClientId) && $sessionClientId !== '') {
            return $sessionClientId;
        }

        $clientId = (string) Str::uuid();

        cookie()->queue(
            name: self::CLIENT_ID_COOKIE,
            value: $clientId,
            minutes: self::COOKIE_LIFETIME,
            path: '/',
            domain: null,
            secure: request()->secure(),
            httpOnly: true,
            sameSite: 'lax'
        );

        session()->put(self::CLIENT_ID_COOKIE, $clientId);

        return $clientId;
    }

    /**
     * Generate cache key for a service
     */
    public function getCacheKey(?string $serviceClass = null, string $prefix = 'ui_state', ?string $clientId = null): string
    {
        $serviceBaseName = $serviceClass ? class_basename($serviceClass) : '';
        $clientId = $clientId ?? $this->getOrCreateClientId();

        return "{$prefix}:{$serviceBaseName}:{$clientId}";
    }

    /**
     * Store UI state in cache
     *
     * @param  array<array-key, mixed>  $uiState
     */
    public function store(string $serviceClass, array $uiState, ?string $clientId = null): bool
    {
        if (empty($uiState)) {
            return false;
        }

        $ttl = $this->getCacheTtl();
        $encodedState = json_encode($uiState);

        $cacheKey = $this->getCacheKey($serviceClass, clientId: $clientId);
        $result = Cache::put($cacheKey, $encodedState, $ttl);

        $firstKey = (string) array_key_first($uiState);
        $this->storeClientOpenedScreens($firstKey, $clientId);

        return $result;
    }

    /**
     * Store each screen opened by the user
     */
    protected function storeClientOpenedScreens(string $screenId, ?string $clientId = null): void
    {
        $clientId ??= $this->getOrCreateClientId();
        $cacheKey = "ui_open_screens:{$clientId}";
        $ttl = $this->getCacheTtl();

        $openedScreens = Cache::get($cacheKey, []);
        if (! \is_array($openedScreens)) {
            $openedScreens = [];
        }
        $openedScreens[$screenId] = true;
        Cache::put($cacheKey, $openedScreens, $ttl);
    }

    /**
     * @return list<string>
     */
    public function getClientOpenedScreens(?string $clientId = null): array
    {
        $clientId ??= $this->getOrCreateClientId();
        $cacheKey = "ui_open_screens:{$clientId}";
        $openedScreens = Cache::get($cacheKey, []);

        return \is_array($openedScreens) ? array_keys($openedScreens) : [];
    }

    /**
     * Remove a screen from client opened screens tracking.
     */
    public function removeClientOpenedScreen(string|int $screenId, ?string $clientId = null): void
    {
        $clientId ??= $this->getOrCreateClientId();
        $cacheKey = "ui_open_screens:{$clientId}";
        $ttl = $this->getCacheTtl();

        $openedScreens = Cache::get($cacheKey, []);
        if (\is_array($openedScreens) && isset($openedScreens[(string) $screenId])) {
            unset($openedScreens[(string) $screenId]);
            Cache::put($cacheKey, $openedScreens, $ttl);
        }
    }

    /**
     * Push active modal metadata onto the client's modal stack.
     *
     * @param  array<int|string, mixed>  $params
     */
    public function pushClientActiveModal(
        string $modalClass,
        ?int $callerScreenId = null,
        ?string $callbackAction = null,
        array $params = [],
        ?int $layerIndex = null,
        ?string $clientId = null,
        ?string $callerScreenClass = null,
        ?string $pageScreenRoute = null,
    ): bool {
        $clientId ??= $this->getOrCreateClientId();
        $cacheKey = "ui_active_modal:{$clientId}";
        $ttl = $this->getCacheTtl();

        if ($callerScreenClass === null && $callerScreenId !== null) {
            $context = $this->getIdGenerator()->getContextFromId($callerScreenId);
            if (\is_string($context) && $context !== '') {
                $callerScreenClass = $context;
            }
        }

        $pageScreenRoute ??= $this->getClientCurrentScreenRoute($clientId);

        $stack = $this->getClientActiveModalStack($clientId);

        if ($layerIndex === null) {
            $count = 0;
            foreach ($stack as $item) {
                if ($item['modal_class'] === $modalClass) {
                    $count++;
                }
            }
            $layerIndex = $count;
        }

        $entry = [
            'modal_class' => $modalClass,
            'caller_screen_id' => $callerScreenId,
            'caller_screen_class' => $callerScreenClass,
            'callback_action' => $callbackAction,
            'params' => $params,
            'layer_index' => $layerIndex,
            'page_screen_route' => $pageScreenRoute,
        ];

        $stack[] = $entry;

        return Cache::put($cacheKey, $stack, $ttl);
    }

    /**
     * Pop the top active modal from the client's modal stack.
     *
     * @return array{modal_class: string, caller_screen_id: ?int, caller_screen_class: ?string, callback_action: ?string, params: array<int|string, mixed>, layer_index: int, page_screen_route: ?string}|null
     */
    public function popClientActiveModal(?string $clientId = null): ?array
    {
        $clientId ??= $this->getOrCreateClientId();
        $cacheKey = "ui_active_modal:{$clientId}";
        $stack = $this->getClientActiveModalStack($clientId);

        if (empty($stack)) {
            return null;
        }

        $popped = array_pop($stack);

        if (empty($stack)) {
            Cache::forget($cacheKey);
        } else {
            $ttl = $this->getCacheTtl();
            Cache::put($cacheKey, $stack, $ttl);
        }

        return $popped;
    }

    /**
     * Get active modal stack for the client.
     *
     * @return list<array{modal_class: string, caller_screen_id: ?int, caller_screen_class: ?string, callback_action: ?string, params: array<int|string, mixed>, layer_index: int, page_screen_route: ?string}>
     */
    public function getClientActiveModalStack(?string $clientId = null): array
    {
        $clientId ??= $this->getOrCreateClientId();
        $cacheKey = "ui_active_modal:{$clientId}";
        $data = Cache::get($cacheKey);

        if (! \is_array($data) || empty($data)) {
            return [];
        }

        if (isset($data['modal_class']) && \is_string($data['modal_class'])) {
            return [$this->normalizeModalEntry($data)];
        }

        $stack = [];
        foreach ($data as $item) {
            if (\is_array($item) && ! empty($item['modal_class']) && \is_string($item['modal_class'])) {
                $stack[] = $this->normalizeModalEntry($item);
            }
        }

        return $stack;
    }

    /**
     * Set the entire active modal stack for the client.
     *
     * @param  list<array{modal_class: string, caller_screen_id: ?int, caller_screen_class: ?string, callback_action: ?string, params: array<int|string, mixed>, layer_index?: int, page_screen_route?: ?string}>  $stack
     */
    public function setClientActiveModalStack(array $stack, ?string $clientId = null): bool
    {
        $clientId ??= $this->getOrCreateClientId();
        $cacheKey = "ui_active_modal:{$clientId}";

        if (empty($stack)) {
            return Cache::forget($cacheKey);
        }

        $ttl = $this->getCacheTtl();

        return Cache::put($cacheKey, $stack, $ttl);
    }

    /**
     * Store active modal metadata for the client (pushes to stack).
     *
     * @param  array<int|string, mixed>  $params
     */
    public function storeClientActiveModal(
        string $modalClass,
        ?int $callerScreenId = null,
        ?string $callbackAction = null,
        array $params = [],
        ?int $layerIndex = null,
        ?string $clientId = null,
        ?string $callerScreenClass = null,
        ?string $pageScreenRoute = null,
    ): bool {
        return $this->pushClientActiveModal(
            modalClass: $modalClass,
            callerScreenId: $callerScreenId,
            callbackAction: $callbackAction,
            params: $params,
            layerIndex: $layerIndex,
            clientId: $clientId,
            callerScreenClass: $callerScreenClass,
            pageScreenRoute: $pageScreenRoute
        );
    }

    /**
     * Set the current active screen route and class for the client.
     */
    public function setClientCurrentScreen(string $screenRoute, ?string $screenClass = null, ?string $clientId = null): bool
    {
        $clientId ??= $this->getOrCreateClientId();
        $cacheKey = "ui_current_screen:{$clientId}";
        $ttl = $this->getCacheTtl();

        $data = [
            'route' => $screenRoute,
            'class' => $screenClass,
        ];

        return Cache::put($cacheKey, $data, $ttl);
    }

    /**
     * Get the current active screen route for the client.
     */
    public function getClientCurrentScreenRoute(?string $clientId = null): ?string
    {
        $clientId ??= $this->getOrCreateClientId();
        $cacheKey = "ui_current_screen:{$clientId}";
        $data = Cache::get($cacheKey);

        if (\is_array($data) && isset($data['route']) && \is_string($data['route'])) {
            return $data['route'];
        }

        return null;
    }

    /**
     * Get the current active screen class for the client.
     */
    public function getClientCurrentScreenClass(?string $clientId = null): ?string
    {
        $clientId ??= $this->getOrCreateClientId();
        $cacheKey = "ui_current_screen:{$clientId}";
        $data = Cache::get($cacheKey);

        if (\is_array($data) && isset($data['class']) && \is_string($data['class'])) {
            return $data['class'];
        }

        return null;
    }

    /**
     * Clear the current active screen for the client.
     */
    public function clearClientCurrentScreen(?string $clientId = null): bool
    {
        $clientId ??= $this->getOrCreateClientId();
        $cacheKey = "ui_current_screen:{$clientId}";

        return Cache::forget($cacheKey);
    }

    /**
     * Get the currently active top modal definition.
     *
     * @return array{modal_class: string, caller_screen_id: ?int, caller_screen_class: ?string, callback_action: ?string, params: array<int|string, mixed>, layer_index: int, page_screen_route: ?string}|null
     */
    public function getClientActiveModal(?string $clientId = null): ?array
    {
        $stack = $this->getClientActiveModalStack($clientId);

        if (empty($stack)) {
            return null;
        }

        return end($stack);
    }

    /**
     * Clear the active modal state for the client.
     */
    public function clearClientActiveModal(?string $clientId = null): bool
    {
        $clientId ??= $this->getOrCreateClientId();
        $cacheKey = "ui_active_modal:{$clientId}";

        return Cache::forget($cacheKey);
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array{modal_class: string, caller_screen_id: ?int, caller_screen_class: ?string, callback_action: ?string, params: array<int|string, mixed>, layer_index: int, page_screen_route: ?string}
     */
    protected function normalizeModalEntry(array $data): array
    {
        $modalClass = isset($data['modal_class']) && \is_string($data['modal_class'])
            ? $data['modal_class']
            : '';

        return [
            'modal_class' => $modalClass,
            'caller_screen_id' => isset($data['caller_screen_id']) && is_numeric($data['caller_screen_id']) ? (int) $data['caller_screen_id'] : null,
            'caller_screen_class' => isset($data['caller_screen_class']) && \is_string($data['caller_screen_class']) ? $data['caller_screen_class'] : null,
            'callback_action' => isset($data['callback_action']) && \is_string($data['callback_action']) ? $data['callback_action'] : null,
            'params' => isset($data['params']) && \is_array($data['params']) ? $data['params'] : [],
            'layer_index' => isset($data['layer_index']) && is_numeric($data['layer_index']) ? (int) $data['layer_index'] : 0,
            'page_screen_route' => isset($data['page_screen_route']) && \is_string($data['page_screen_route']) ? $data['page_screen_route'] : null,
        ];
    }

    /**
     * Get UI state from cache
     *
     * @param  string  $serviceClass  Service class name
     * @return array<string, mixed>|null UI state array or null if not found
     */
    public function get(string $serviceClass, ?string $clientId = null): ?array
    {
        $cacheKey = $this->getCacheKey($serviceClass, clientId: $clientId);
        $content = Cache::get($cacheKey);
        $result = null;

        if (\is_string($content) && $content !== '') {
            /** @var array<string, mixed>|null $decodedState */
            $decodedState = json_decode($content, true);
            $result = \is_array($decodedState) ? $decodedState : null;
        }

        return $result;
    }

    /**
     * Store internal screen state in cache.
     *
     * @param  string  $serviceClass  Service class or context identifier
     * @param  array<string, mixed>  $state  Screen state array
     */
    public function storeScreenState(string $serviceClass, array $state, ?string $clientId = null): bool
    {
        if (empty($state)) {
            return $this->clearScreenState($serviceClass, $clientId);
        }

        $ttl = $this->getCacheTtl();
        $cacheKey = $this->getCacheKey($serviceClass, prefix: 'ui_screen_state', clientId: $clientId);

        return Cache::put($cacheKey, json_encode($state), $ttl);
    }

    /**
     * Get internal screen state from cache.
     *
     * @param  string  $serviceClass  Service class or context identifier
     * @return array<string, mixed> Screen state array
     */
    public function getScreenState(string $serviceClass, ?string $clientId = null): array
    {
        $cacheKey = $this->getCacheKey($serviceClass, prefix: 'ui_screen_state', clientId: $clientId);
        $content = Cache::get($cacheKey);

        if (\is_string($content) && $content !== '') {
            /** @var array<string, mixed>|null $decoded */
            $decoded = json_decode($content, true);

            return \is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    /**
     * Clear internal screen state from cache.
     */
    public function clearScreenState(string $serviceClass, ?string $clientId = null): bool
    {
        $cacheKey = $this->getCacheKey($serviceClass, prefix: 'ui_screen_state', clientId: $clientId);

        return Cache::forget($cacheKey);
    }

    /**
     * Clear UI state from cache
     */
    public function clear(string $serviceClass, ?string $clientId = null): bool
    {
        $this->clearScreenState($serviceClass, $clientId);
        $cacheKey = $this->getCacheKey($serviceClass, clientId: $clientId);

        return Cache::forget($cacheKey);
    }

    /**
     * Set the authentication token in the cache
     */
    public function setAuthToken(?string $token, ?string $clientId = null): bool
    {
        $cacheKey = $this->getCacheKey(prefix: 'ui_auth_token', clientId: $clientId);
        $ttl = $this->getCacheTtl();
        Cache::put($cacheKey, $token, $ttl);

        return true;
    }

    public function getAuthToken(?string $clientId = null): ?string
    {
        $cacheKey = $this->getCacheKey(prefix: 'ui_auth_token', clientId: $clientId);
        $token = Cache::get($cacheKey);

        return \is_string($token) ? $token : null;
    }

    public function storeKeyValue(string $key, mixed $value, ?string $clientId = null): bool
    {
        $cacheKey = $this->getCacheKey(prefix: "ui_key_{$key}", clientId: $clientId);
        $ttl = $this->getCacheTtl();
        Cache::put($cacheKey, $value, $ttl);

        return true;
    }

    public function getKeyValue(string $key, ?string $clientId = null): mixed
    {
        $cacheKey = $this->getCacheKey(prefix: "ui_key_{$key}", clientId: $clientId);

        return Cache::get($cacheKey);
    }

    public function clearKeyValue(string $key, ?string $clientId = null): bool
    {
        $cacheKey = $this->getCacheKey(prefix: "ui_key_{$key}", clientId: $clientId);

        return Cache::forget($cacheKey);
    }

    /**
     * Set the active organizational unit slug for the client.
     */
    public function setActiveUnit(?string $slug, ?string $clientId = null): void
    {
        if ($slug === null || $slug === '') {
            $this->clearKeyValue('active_unit', $clientId);
        } else {
            $this->storeKeyValue('active_unit', $slug, $clientId);
        }
    }

    /**
     * Get the active organizational unit slug for the client.
     */
    public function getActiveUnit(?string $clientId = null): ?string
    {
        $slug = $this->getKeyValue('active_unit', $clientId);

        return \is_string($slug) && $slug !== '' ? $slug : null;
    }

    /**
     * Resolve cache TTL from config.
     */
    protected function getCacheTtl(): int
    {
        $ttlConfig = config('usim.ui_cache_ttl', 60);
        $ttl = \is_int($ttlConfig) ? $ttlConfig : 60;
        if (\is_string($ttlConfig) && ctype_digit($ttlConfig)) {
            $ttl = (int) $ttlConfig;
        }

        return $ttl;
    }
}
