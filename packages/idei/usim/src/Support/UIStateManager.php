<?php

namespace Idei\Usim\Support;

use Idei\Usim\Contracts\UIStateRepositoryInterface;

/**
 * UI State Manager (Static Facade / Adapter)
 *
 * Forwards all UI state caching and client tracking operations to the scoped
 * UIStateRepositoryInterface instance in the Laravel service container.
 */
class UIStateManager
{
    public const CLIENT_ID_COOKIE = UIStateRepository::CLIENT_ID_COOKIE;

    public const CLIENT_ID_HEADER = UIStateRepository::CLIENT_ID_HEADER;

    public const COOKIE_LIFETIME = UIStateRepository::COOKIE_LIFETIME;

    private static ?UIStateRepositoryInterface $fallbackInstance = null;

    protected static function getRepository(): UIStateRepositoryInterface
    {
        if (function_exists('app') && app()->bound(UIStateRepositoryInterface::class)) {
            return app(UIStateRepositoryInterface::class);
        }

        if (self::$fallbackInstance === null) {
            self::$fallbackInstance = new UIStateRepository;
        }

        return self::$fallbackInstance;
    }

    public static function getOrCreateClientId(): string
    {
        return self::getRepository()->getOrCreateClientId();
    }

    public static function getCacheKey(?string $serviceClass = null, string $prefix = 'ui_state', ?string $clientId = null): string
    {
        return self::getRepository()->getCacheKey($serviceClass, $prefix, $clientId);
    }

    /**
     * @param  array<array-key, mixed>  $uiState
     */
    public static function store(string $serviceClass, array $uiState): bool
    {
        return self::getRepository()->store($serviceClass, $uiState);
    }

    /**
     * @return list<string>
     */
    public static function getClientOpenedScreens(?string $clientId = null): array
    {
        return self::getRepository()->getClientOpenedScreens($clientId);
    }

    public static function removeClientOpenedScreen(string|int $screenId, ?string $clientId = null): void
    {
        self::getRepository()->removeClientOpenedScreen($screenId, $clientId);
    }

    /**
     * @param  array<int|string, mixed>  $params
     */
    public static function pushClientActiveModal(
        string $modalClass,
        ?int $callerScreenId = null,
        ?string $callbackAction = null,
        array $params = [],
        ?int $layerIndex = null,
        ?string $clientId = null,
        ?string $callerScreenClass = null,
        ?string $pageScreenRoute = null,
    ): bool {
        return self::getRepository()->pushClientActiveModal(
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
     * @return array{modal_class: string, caller_screen_id: ?int, caller_screen_class: ?string, callback_action: ?string, params: array<int|string, mixed>, layer_index: int, page_screen_route: ?string}|null
     */
    public static function popClientActiveModal(?string $clientId = null): ?array
    {
        return self::getRepository()->popClientActiveModal($clientId);
    }

    /**
     * @return list<array{modal_class: string, caller_screen_id: ?int, caller_screen_class: ?string, callback_action: ?string, params: array<int|string, mixed>, layer_index: int, page_screen_route: ?string}>
     */
    public static function getClientActiveModalStack(?string $clientId = null): array
    {
        return self::getRepository()->getClientActiveModalStack($clientId);
    }

    /**
     * @param  list<array{modal_class: string, caller_screen_id: ?int, caller_screen_class: ?string, callback_action: ?string, params: array<int|string, mixed>, layer_index?: int, page_screen_route?: ?string}>  $stack
     */
    public static function setClientActiveModalStack(array $stack, ?string $clientId = null): bool
    {
        return self::getRepository()->setClientActiveModalStack($stack, $clientId);
    }

    /**
     * @param  array<int|string, mixed>  $params
     */
    public static function storeClientActiveModal(
        string $modalClass,
        ?int $callerScreenId = null,
        ?string $callbackAction = null,
        array $params = [],
        ?int $layerIndex = null,
        ?string $clientId = null,
        ?string $callerScreenClass = null,
        ?string $pageScreenRoute = null,
    ): bool {
        return self::getRepository()->storeClientActiveModal(
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

    public static function setClientCurrentScreen(string $screenRoute, ?string $screenClass = null, ?string $clientId = null): bool
    {
        return self::getRepository()->setClientCurrentScreen($screenRoute, $screenClass, $clientId);
    }

    public static function getClientCurrentScreenRoute(?string $clientId = null): ?string
    {
        return self::getRepository()->getClientCurrentScreenRoute($clientId);
    }

    public static function getClientCurrentScreenClass(?string $clientId = null): ?string
    {
        return self::getRepository()->getClientCurrentScreenClass($clientId);
    }

    public static function clearClientCurrentScreen(?string $clientId = null): bool
    {
        return self::getRepository()->clearClientCurrentScreen($clientId);
    }

    /**
     * @return array{modal_class: string, caller_screen_id: ?int, caller_screen_class: ?string, callback_action: ?string, params: array<int|string, mixed>, layer_index: int, page_screen_route: ?string}|null
     */
    public static function getClientActiveModal(?string $clientId = null): ?array
    {
        return self::getRepository()->getClientActiveModal($clientId);
    }

    public static function clearClientActiveModal(?string $clientId = null): bool
    {
        return self::getRepository()->clearClientActiveModal($clientId);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function get(string $serviceClass): ?array
    {
        return self::getRepository()->get($serviceClass);
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public static function storeScreenState(string $serviceClass, array $state): bool
    {
        return self::getRepository()->storeScreenState($serviceClass, $state);
    }

    /**
     * @return array<string, mixed>
     */
    public static function getScreenState(string $serviceClass): array
    {
        return self::getRepository()->getScreenState($serviceClass);
    }

    public static function clearScreenState(string $serviceClass): bool
    {
        return self::getRepository()->clearScreenState($serviceClass);
    }

    public static function clear(string $serviceClass): bool
    {
        return self::getRepository()->clear($serviceClass);
    }

    public static function setAuthToken(?string $token, ?string $clientId = null): bool
    {
        return self::getRepository()->setAuthToken($token, $clientId);
    }

    public static function getAuthToken(?string $clientId = null): ?string
    {
        return self::getRepository()->getAuthToken($clientId);
    }

    public static function storeKeyValue(string $key, mixed $value, ?string $clientId = null): bool
    {
        return self::getRepository()->storeKeyValue($key, $value, $clientId);
    }

    public static function getKeyValue(string $key, ?string $clientId = null): mixed
    {
        return self::getRepository()->getKeyValue($key, $clientId);
    }

    public static function clearKeyValue(string $key, ?string $clientId = null): bool
    {
        return self::getRepository()->clearKeyValue($key, $clientId);
    }

    public static function setActiveUnit(?string $slug, ?string $clientId = null): void
    {
        self::getRepository()->setActiveUnit($slug, $clientId);
    }

    public static function getActiveUnit(?string $clientId = null): ?string
    {
        return self::getRepository()->getActiveUnit($clientId);
    }
}
