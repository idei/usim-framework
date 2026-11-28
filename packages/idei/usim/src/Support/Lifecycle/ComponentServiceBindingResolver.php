<?php

declare(strict_types=1);

namespace Idei\Usim\Support\Lifecycle;

use BadMethodCallException;
use Idei\Usim\Components\CardGrid;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\ItemList;
use Idei\Usim\Components\UIComponent;
use Idei\Usim\Screen;

/**
 * Resolves and executes service-backed component handlers (ItemList and CardGrid)
 * when an action or query is not handled directly by a Screen method.
 */
final class ComponentServiceBindingResolver
{
    /**
     * @var array<string, string>
     */
    private const array ITEM_LIST_MUTATION_EVENT_MAP = [
        'on_create' => 'create',
        'on_update' => 'update',
        'on_delete' => 'delete',
        'on_bulk_delete' => 'bulk_delete',
    ];

    /**
     * @var list<string>
     */
    private const array ITEM_LIST_MUTATION_OPERATIONS = [
        'create',
        'update',
        'delete',
        'bulk_delete',
    ];

    /**
     * Resolve a service-bound component from an already-initialized screen container.
     */
    public function resolveForScreen(
        Screen $screen,
        ?int $componentId,
        string $action,
        bool $queryOnly
    ): ?ComponentServiceBinding {
        $container = $screen->getContainer();

        return $container !== null
            ? $this->resolve($container, $componentId, $action, $queryOnly)
            : null;
    }

    /**
     * Resolve a service-bound component (ItemList or CardGrid) and operation.
     */
    public function resolve(
        Container $container,
        ?int $componentId,
        string $action,
        bool $queryOnly
    ): ?ComponentServiceBinding {
        $itemListBinding = $this->resolveItemList($container, $componentId, $action, $queryOnly);
        if ($itemListBinding !== null) {
            return $itemListBinding;
        }

        if (! $queryOnly) {
            return $this->resolveCardGrid($container, $componentId, $action);
        }

        return null;
    }

    /**
     * Execute a mutation/event service action on a resolved component binding.
     *
     * @param  array<string, mixed>  $parameters
     */
    public function executeAction(ComponentServiceBinding $binding, array $parameters, Screen $screen): void
    {
        $binding->component->handleServiceAction($binding->operation, $parameters, $screen);
    }

    /**
     * Execute a read-only service query on a resolved component binding.
     *
     * @param  array<string, mixed>  $parameters
     * @param  array<string, mixed>  $queryParams
     */
    public function executeQuery(
        ComponentServiceBinding $binding,
        array $parameters,
        array $queryParams,
        Screen $screen,
        string $method
    ): mixed {
        if ($binding->component instanceof ItemList) {
            $rawSearch = $parameters['search'] ?? $queryParams['search'] ?? '';
            $search = is_scalar($rawSearch) ? (string) $rawSearch : '';

            return $binding->component->queryServiceItems($search, $parameters);
        }

        throw new BadMethodCallException("Query method [{$method}] does not exist on screen [".get_class($screen).'].');
    }

    /**
     * Resolve a CardGrid component and event-driven query operation (`POST /api/ui-event`).
     */
    public function resolveCardGrid(
        Container $container,
        ?int $componentId,
        string $action
    ): ?ComponentServiceBinding {
        $parsed = CardGrid::parseServiceAction($action);
        $component = $this->findTargetComponent($container, $componentId, $parsed, CardGrid::class);

        if ($component === null) {
            return null;
        }

        $operation = $this->resolveCardGridOperation($component, $parsed, $action);
        if ($operation === null || ! $component->supportsServiceOperation($operation)) {
            return null;
        }

        return new ComponentServiceBinding($component, $operation);
    }

    /**
     * Resolve an ItemList component and operation for a service-wired query or mutation action.
     */
    public function resolveItemList(
        Container $container,
        ?int $componentId,
        string $action,
        bool $queryOnly
    ): ?ComponentServiceBinding {
        $parsed = ItemList::parseServiceAction($action);
        $component = $this->findTargetComponent($container, $componentId, $parsed, ItemList::class);

        if ($component === null) {
            return null;
        }

        $operation = $this->resolveItemListOperation($component, $parsed, $action, $queryOnly);
        if ($operation === null || ! $this->isAllowedItemListOperation($operation, $queryOnly)) {
            return null;
        }

        if (! $component->supportsServiceOperation($operation)) {
            return null;
        }

        return new ComponentServiceBinding($component, $operation);
    }

    /**
     * Locate the target component by trigger ID or parsed action target identifier.
     *
     * @template T of UIComponent
     *
     * @param  array{target: string, operation: string}|null  $parsed
     * @param  class-string<T>  $expectedClass
     * @return T|null
     */
    private function findTargetComponent(
        Container $container,
        ?int $componentId,
        ?array $parsed,
        string $expectedClass
    ): ?UIComponent {
        $component = $componentId !== null ? $container->findById($componentId) : null;

        if (! ($component instanceof $expectedClass) && $parsed !== null) {
            $component = $this->findByTargetIdentifier($container, $parsed['target']);
        }

        return $component instanceof $expectedClass ? $component : null;
    }

    private function findByTargetIdentifier(Container $container, string $target): mixed
    {
        $byName = $container->findByName($target);
        if ($byName !== null) {
            return $byName;
        }

        return ctype_digit($target) ? $container->findById((int) $target) : null;
    }

    /**
     * @param  array{target: string, operation: string}|null  $parsed
     */
    private function resolveCardGridOperation(CardGrid $component, ?array $parsed, string $action): ?string
    {
        $operation = $parsed['operation'] ?? null;
        if ($operation === null && $component->get('query_action') === $action) {
            $operation = 'query';
        }

        return $operation === 'query' ? $operation : null;
    }

    /**
     * @param  array{target: string, operation: string}|null  $parsed
     */
    private function resolveItemListOperation(
        ItemList $component,
        ?array $parsed,
        string $action,
        bool $queryOnly
    ): ?string {
        if ($parsed !== null) {
            return $parsed['operation'];
        }

        if ($queryOnly) {
            return $component->get('action') === $action ? 'list' : null;
        }

        return $this->resolveConfiguredItemListMutation($component, $action);
    }

    private function resolveConfiguredItemListMutation(ItemList $component, string $action): ?string
    {
        foreach (self::ITEM_LIST_MUTATION_EVENT_MAP as $configKey => $candidateOp) {
            $cfg = $component->get($configKey);
            $configuredAction = is_array($cfg) ? ($cfg['action'] ?? null) : (is_string($cfg) ? $cfg : null);
            if ($configuredAction === $action) {
                return $candidateOp;
            }
        }

        return null;
    }

    private function isAllowedItemListOperation(string $operation, bool $queryOnly): bool
    {
        if ($queryOnly) {
            return $operation === 'list';
        }

        return in_array($operation, self::ITEM_LIST_MUTATION_OPERATIONS, true);
    }
}
