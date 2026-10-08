<?php

namespace Idei\Usim\Contracts;

/**
 * Contract for managing UI state persistence, client identification, and modal stack caches.
 */
interface UIStateRepositoryInterface
{
    /**
     * Get or create a unique client identifier (scoped per tab/browser).
     */
    public function getOrCreateClientId(): string;

    /**
     * Generate cache key for a service class and client.
     */
    public function getCacheKey(?string $serviceClass = null, string $prefix = 'ui_state', ?string $clientId = null): string;

    /**
     * Store the serialized UI component tree of a screen.
     *
     * @param  array<array-key, mixed>  $uiState
     */
    public function store(string $serviceClass, array $uiState, ?string $clientId = null): bool;

    /**
     * Retrieve the serialized UI component tree of a screen.
     *
     * @return array<string, mixed>|null
     */
    public function get(string $serviceClass, ?string $clientId = null): ?array;

    /**
     * Clear the cached UI component tree of a screen.
     */
    public function clear(string $serviceClass, ?string $clientId = null): bool;

    /**
     * Store screen business state (e.g. store_* properties).
     *
     * @param  array<string, mixed>  $state
     */
    public function storeScreenState(string $serviceClass, array $state, ?string $clientId = null): bool;

    /**
     * Retrieve screen business state.
     *
     * @return array<string, mixed>
     */
    public function getScreenState(string $serviceClass, ?string $clientId = null): array;

    /**
     * Clear screen business state.
     */
    public function clearScreenState(string $serviceClass, ?string $clientId = null): bool;

    /**
     * Get list of screen component IDs currently opened by the client.
     *
     * @return list<string>
     */
    public function getClientOpenedScreens(?string $clientId = null): array;

    /**
     * Remove a screen ID from the client's opened screens tracking.
     */
    public function removeClientOpenedScreen(string|int $screenId, ?string $clientId = null): void;

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
    ): bool;

    /**
     * Pop the top active modal from the client's modal stack.
     *
     * @return array{modal_class: string, caller_screen_id: ?int, caller_screen_class: ?string, callback_action: ?string, params: array<int|string, mixed>, layer_index: int, page_screen_route: ?string}|null
     */
    public function popClientActiveModal(?string $clientId = null): ?array;

    /**
     * Get active modal stack for the client.
     *
     * @return list<array{modal_class: string, caller_screen_id: ?int, caller_screen_class: ?string, callback_action: ?string, params: array<int|string, mixed>, layer_index: int, page_screen_route: ?string}>
     */
    public function getClientActiveModalStack(?string $clientId = null): array;

    /**
     * Set the entire active modal stack for the client.
     *
     * @param  list<array{modal_class: string, caller_screen_id: ?int, caller_screen_class: ?string, callback_action: ?string, params: array<int|string, mixed>, layer_index?: int, page_screen_route?: ?string}>  $stack
     */
    public function setClientActiveModalStack(array $stack, ?string $clientId = null): bool;

    /**
     * Store active modal (pushes onto the client stack).
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
    ): bool;

    /**
     * Get the currently active top modal definition.
     *
     * @return array{modal_class: string, caller_screen_id: ?int, caller_screen_class: ?string, callback_action: ?string, params: array<int|string, mixed>, layer_index: int, page_screen_route: ?string}|null
     */
    public function getClientActiveModal(?string $clientId = null): ?array;

    /**
     * Clear all active modals for the client.
     */
    public function clearClientActiveModal(?string $clientId = null): bool;

    /**
     * Record the current active screen route and class for the client.
     */
    public function setClientCurrentScreen(string $screenRoute, ?string $screenClass = null, ?string $clientId = null): bool;

    /**
     * Get the current active screen route for the client.
     */
    public function getClientCurrentScreenRoute(?string $clientId = null): ?string;

    /**
     * Get the current active screen class for the client.
     */
    public function getClientCurrentScreenClass(?string $clientId = null): ?string;

    /**
     * Clear the current active screen tracking for the client.
     */
    public function clearClientCurrentScreen(?string $clientId = null): bool;

    /**
     * Set the active authentication token in UI state.
     */
    public function setAuthToken(?string $token, ?string $clientId = null): bool;

    /**
     * Get the active authentication token from UI state.
     */
    public function getAuthToken(?string $clientId = null): ?string;

    /**
     * Store arbitrary key-value state for the client.
     */
    public function storeKeyValue(string $key, mixed $value, ?string $clientId = null): bool;

    /**
     * Retrieve arbitrary key-value state for the client.
     */
    public function getKeyValue(string $key, ?string $clientId = null): mixed;

    /**
     * Clear arbitrary key-value state for the client.
     */
    public function clearKeyValue(string $key, ?string $clientId = null): bool;

    /**
     * Set the active organizational unit slug for the client.
     */
    public function setActiveUnit(?string $slug, ?string $clientId = null): void;

    /**
     * Get the active organizational unit slug for the client.
     */
    public function getActiveUnit(?string $clientId = null): ?string;
}
