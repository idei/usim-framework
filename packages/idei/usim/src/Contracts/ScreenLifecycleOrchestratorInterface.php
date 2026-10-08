<?php

namespace Idei\Usim\Contracts;

use Idei\Usim\Components\Container;
use Idei\Usim\Screen;

/**
 * Contract for orchestrating the complete lifecycle of a Screen:
 * initialization, tree reconstruction, event dispatching, diff calculation,
 * and state synchronization.
 */
interface ScreenLifecycleOrchestratorInterface
{
    /**
     * Render the screen for an initial page view or full reload.
     *
     * @param  array<string, mixed>  $incomingStorage
     * @param  array<string, mixed>  $queryParams
     * @param  array<int|string, mixed>  $buildParams
     */
    public function render(
        Screen $screen,
        array $incomingStorage = [],
        array $queryParams = [],
        int|string|null $parent = 'main',
        bool $shouldReset = false,
        array $buildParams = []
    ): void;

    /**
     * Execute an action handler within the managed Screen event lifecycle.
     *
     * @param  array<string, mixed>  $parameters
     * @param  array<string, mixed>  $incomingStorage
     * @param  array<string, mixed>  $queryParams
     */
    public function handleAction(
        Screen $screen,
        string $method,
        array $parameters = [],
        array $incomingStorage = [],
        array $queryParams = [],
        int|string|null $parent = null,
        ?int $triggerComponentId = null
    ): void;

    /**
     * Initialize event context before invoking action or render.
     *
     * @param  array<string, mixed>  $incomingStorage
     * @param  array<string, mixed>  $queryParams
     * @param  array<string, mixed>  $eventParameters
     * @param  array<int|string, mixed>  $buildParams
     */
    public function initializeEventContext(
        Screen $screen,
        array $incomingStorage = [],
        array $queryParams = [],
        int|string|null $parent = null,
        array $eventParameters = [],
        ?int $triggerComponentId = null,
        array $buildParams = []
    ): void;

    /**
     * Finalize event context after action execution or render.
     */
    public function finalizeEventContext(Screen $screen, bool $reload = false): void;

    /**
     * Reconstruct the screen's component tree from cache (or build if not cached).
     */
    public function reconstructScreenTree(Screen $screen, mixed ...$params): Container;

    /**
     * Retrieve cached screen snapshot or generate a new one.
     *
     * @return array<int|string, array<string, mixed>>
     */
    public function getCachedScreenSnapshot(Screen $screen, mixed ...$params): array;

    /**
     * Persist screen state snapshot to repository.
     */
    public function cacheScreenSnapshot(Screen $screen, Container $container): void;

    /**
     * Clear cached screen snapshot and internal state.
     */
    public function clearCachedScreenSnapshot(Screen $screen): bool;

    /**
     * Build diff response comparing old and new UI snapshots.
     *
     * @return array<int|string, array<string, mixed>>
     */
    public function buildDiffResponse(Screen $screen, bool $reload = false): array;

    /**
     * Embed a screen into a parent container.
     *
     * @param  class-string<Screen>  $screenClass
     */
    public function embedInto(string $screenClass, Container $parent): void;
}
