<?php

namespace Idei\Usim\Support;

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
use Idei\Usim\Components\Textarea;
use Idei\Usim\Components\Timer;
use Idei\Usim\Components\UIComponent;
use Idei\Usim\Components\Uploader;
use Idei\Usim\Contracts\ComponentIdGeneratorInterface;
use Idei\Usim\Contracts\ScreenLifecycleOrchestratorInterface;
use Idei\Usim\Contracts\UIDifferInterface;
use Idei\Usim\Contracts\UIElement;
use Idei\Usim\Contracts\UIStateRepositoryInterface;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\Layout\AbstractLayout;
use Idei\Usim\Layout\LayoutInterface;
use Idei\Usim\Screen;
use Idei\Usim\UI;
use Idei\Usim\UIChangesCollector;
use Idei\Usim\ValueObjects\Spacing;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionProperty;
use RuntimeException;

/**
 * Orchestrates the full lifecycle of a Screen:
 * - Request / render initialization
 * - Event action execution
 * - Component tree reconstruction and caching
 * - Component dirty tracking and diff calculation
 * - Layout wrapping and nested screen embedding
 */
class ScreenLifecycleOrchestrator implements ScreenLifecycleOrchestratorInterface
{
    public function __construct(
        protected UIStateRepositoryInterface $stateRepository,
        protected UIDifferInterface $differ,
        protected ComponentIdGeneratorInterface $idGenerator,
        protected UIChangesCollector $changesCollector,
        protected ?UsimConfig $usimConfig = null,
    ) {
        $this->usimConfig ??= app(UsimConfig::class);
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
        Screen $screen,
        array $incomingStorage = [],
        array $queryParams = [],
        int|string|null $parent = 'main',
        bool $shouldReset = false,
        array $buildParams = []
    ): void {
        $this->idGenerator->pushCurrentContext($screen->getContextIdentifier());
        try {
            $this->changesCollector->setStorage($incomingStorage);

            if ($shouldReset) {
                $screen->onResetScreen();
            }

            // Check if this screen should be wrapped by a Layout Screen
            if ($parent === 'main' && $screen->modalLayerIndex === 0 && ! ($screen instanceof LayoutInterface)) {
                $layoutClass = $screen::getLayoutClass();
                if ($layoutClass !== null && class_exists($layoutClass)) {
                    AbstractLayout::setActiveHostScreen($screen::class);
                    try {
                        /** @var AbstractLayout $layout */
                        $layout = Screen::make($layoutClass);
                        AbstractLayout::setCurrent($layout);
                        $screen->setLayoutInstance($layout);
                        $layout->setActiveScreen('main', $screen::class);

                        $layout->render(
                            incomingStorage: $incomingStorage,
                            queryParams: $queryParams,
                            parent: 'main',
                            shouldReset: $shouldReset
                        );

                        $slot = $layout->getSlot('content') ?? $layout->getSlot('main');
                        if ($slot instanceof Container) {
                            $slot->clear();
                            $this->embedInto($screen::class, $slot);
                        }

                        return;
                    } finally {
                        AbstractLayout::setActiveHostScreen(null);
                    }
                }
            }

            $this->initializeEventContext(
                screen: $screen,
                incomingStorage: $incomingStorage,
                queryParams: $queryParams,
                parent: $parent,
                buildParams: $buildParams
            );

            $this->finalizeEventContext(screen: $screen, reload: true);

            $agentContext = $screen->getAgentContext();
            if (! empty($agentContext)) {
                $this->changesCollector->add(['agent_context' => $agentContext]);
            }
        } finally {
            $this->idGenerator->popCurrentContext();
        }
    }

    /**
     * Execute an action handler within the managed Screen event lifecycle.
     *
     * @param  string  $method  name of the action handler method (e.g., 'onSave', 'onResetScreen')
     * @param  array<string, mixed>  $parameters  Parameters passed to the action handler
     * @param  array<string, mixed>  $incomingStorage  Storage data from frontend
     * @param  array<string, mixed>  $queryParams  Query parameters from frontend
     * @param  int|string|null  $parent  Target parent container
     * @param  int|null  $triggerComponentId  ID of the component that triggered the event
     */
    public function handleAction(
        Screen $screen,
        string $method,
        array $parameters = [],
        array $incomingStorage = [],
        array $queryParams = [],
        int|string|null $parent = null,
        ?int $triggerComponentId = null
    ): void {
        $this->idGenerator->pushCurrentContext($screen->getContextIdentifier());
        try {
            $this->changesCollector->setStorage($incomingStorage);

            if ($method === 'onResetScreen') {
                $screen->onResetScreen();
                $this->initializeEventContext(
                    screen: $screen,
                    incomingStorage: $incomingStorage,
                    queryParams: $queryParams,
                    parent: $parent,
                    eventParameters: $parameters,
                    triggerComponentId: $triggerComponentId
                );
                $this->finalizeEventContext(screen: $screen, reload: false);

                return;
            }

            $this->initializeEventContext(
                screen: $screen,
                incomingStorage: $incomingStorage,
                queryParams: $queryParams,
                parent: $parent,
                eventParameters: $parameters,
                triggerComponentId: $triggerComponentId
            );

            if (method_exists($screen, $method)) {
                $reflectionMethod = new ReflectionMethod($screen, $method);
                $reflectionMethod->invoke($screen, $parameters);
            }

            $this->finalizeEventContext(screen: $screen, reload: false);
        } finally {
            $this->idGenerator->popCurrentContext();
        }
    }

    /**
     * Initialize event context
     *
     * Called before invoking event handler.
     * Loads UI container and captures state for diff calculation.
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
        Screen $screen,
        array $incomingStorage = [],
        array $queryParams = [],
        int|string|null $parent = null,
        array $eventParameters = [],
        ?int $triggerComponentId = null,
        array $buildParams = []
    ): void {
        $screen->setIncomingStorage($incomingStorage);
        $screen->setQueryParams($queryParams);
        Screen::setCurrentIncomingStorage($incomingStorage);
        Screen::setCurrentQueryParams($queryParams);

        // Inject storage values into protected properties (store_* variables)
        $screen->injectStorageValues($incomingStorage);

        // Restore internal screen state from UIStateRepository snapshot cache (state_* variables)
        $cachedState = $this->stateRepository->getScreenState($screen->getContextIdentifier());
        if (! empty($cachedState)) {
            $screen->injectStateVariables($cachedState);
        }

        $container = $this->reconstructScreenTree($screen, ...$buildParams);
        $screen->setContainer($container);

        if ($parent !== null && $parent !== '') {
            $screen->setParent($parent);
            $container->setParent($parent);
        } elseif ($container->getParent() !== null) {
            $screen->setParent($container->getParent());
        }

        if (! empty($eventParameters) || $triggerComponentId !== null) {
            $this->hydrateClientComponentState($container, $eventParameters, $triggerComponentId);
        }

        $this->clearContainerDirtyState($container);
        $screen->setOldUI($container->toJson());

        // Inject component references into protected properties
        $this->injectComponentReferences($screen, $container);
    }

    /**
     * Finalize event context
     *
     * Automatically detects changes by comparing UI state, stores updated UI,
     * and records formatted response in changes collector.
     */
    public function finalizeEventContext(Screen $screen, bool $reload = false): void
    {
        if ($reload) {
            $screen->callPostLoadUI();
        }

        $container = $screen->getContainer();
        if ($container === null) {
            return;
        }

        // Get current UI state
        $newUI = $container->toJson();
        $screen->setNewUI($newUI);

        // Persist the final container state for both event diffs and full reloads.
        $this->cacheScreenSnapshot($screen, $container);

        $diff = $screen->callBuildDiffResponse(reload: $reload);
        $storageVariables = $screen->getStorageVariables();
        $this->changesCollector->add($diff);
        $this->changesCollector->setStorage($storageVariables);
    }

    /**
     * Reconstruct the current screen component tree from cache.
     *
     * If no cached snapshot exists, the UI is generated first and then reconstructed.
     *
     * @param  mixed  ...$params  Optional parameters passed to buildBaseUI if regenerating cache
     */
    public function reconstructScreenTree(Screen $screen, mixed ...$params): Container
    {
        $jsonUI = $this->getCachedScreenSnapshot($screen, ...$params);

        return $this->reconstructContainerFromJson($jsonUI, $screen->getContextIdentifier());
    }

    /**
     * Get stored UI state from cache, regenerating it if missing or invalid.
     *
     * @param  mixed  ...$params  Optional parameters passed to buildBaseUI
     * @return array<int|string, array<string, mixed>> UI structure in JSON format
     */
    public function getCachedScreenSnapshot(Screen $screen, mixed ...$params): array
    {
        $contextKey = $screen->getContextIdentifier();

        // Check if UI exists in cache
        $cachedUI = $this->stateRepository->get($contextKey);

        if ($this->isTypedCachedScreenSnapshot($cachedUI) && $this->isValidCachedScreenSnapshot($cachedUI)) {
            $cachedState = $this->stateRepository->getScreenState($contextKey);
            if (! empty($cachedState)) {
                $screen->injectStateVariables($cachedState);
            }

            return $cachedUI;
        }

        if ($cachedUI !== null) {
            $this->stateRepository->clear($contextKey);
        }

        $currentClass = $screen::class;
        $currentClassSlug = strtolower(str_replace('\\', '_', $currentClass))
            .($screen->modalLayerIndex > 0 ? "_{$screen->modalLayerIndex}" : '');
        $container = UI::container($currentClassSlug, $contextKey)
            ->parent($screen->parent)
            ->modalLayerIndex($screen->modalLayerIndex)
            ->padding(Spacing::px(5))
            ->layout(LayoutType::VERTICAL)
            ->justifyContent('center')
            ->alignItems('center');

        // Generate and cache UI directly
        $screen->callBuildBaseUI($container, ...$params);

        $ui = $container
            ->root(true)
            ->toJson();

        $this->cacheScreenSnapshot($screen, $container);

        return $ui;
    }

    /**
     * Store UI state snapshot and internal state in repository cache.
     */
    public function cacheScreenSnapshot(Screen $screen, Container $container): void
    {
        $contextKey = $screen->getContextIdentifier();
        $this->stateRepository->store($contextKey, $container->toJson());

        $state = $screen->getStateVariables();
        if (! empty($state)) {
            $this->stateRepository->storeScreenState($contextKey, $state);
        } else {
            $this->stateRepository->clearScreenState($contextKey);
        }
    }

    /**
     * Clear the cached screen snapshot and state variables.
     */
    public function clearCachedScreenSnapshot(Screen $screen): bool
    {
        $contextKey = $screen->getContextIdentifier();
        $this->stateRepository->clearScreenState($contextKey);

        return $this->stateRepository->clear($contextKey);
    }

    /**
     * Build diff response in indexed format.
     *
     * @return array<int|string, array<string, mixed>> Indexed diff response
     */
    public function buildDiffResponse(Screen $screen, bool $reload = false): array
    {
        $oldUI = $screen->getOldUI() ?? [];
        $newUI = $screen->getNewUI() ?? [];

        $diff = $reload ?
            $this->differ->compare([], $newUI) :
            $this->differ->compare($oldUI, $newUI);

        $container = $screen->getContainer();
        if (! $reload && $container !== null) {
            foreach ($this->collectDirtyComponentChanges($container) as $componentId => $dirtyProps) {
                if (! isset($newUI[$componentId])) {
                    continue;
                }
                if (($newUI[$componentId]['parent'] ?? null) === null) {
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
            if (isset($newUI[$componentId]['type'])) {
                $changes['type'] = $newUI[$componentId]['type'];
            }

            $result[$componentId] = $changes;
        }

        return $result;
    }

    /**
     * Build and attach a nested screen inside a parent container.
     *
     * @param  class-string<Screen>  $screenClass
     */
    public function embedInto(string $screenClass, Container $parent): void
    {
        $parentId = $parent->getId();

        $instance = Screen::make($screenClass);
        $instance->setParent($parentId);

        $prevIncomingStorage = Screen::getCurrentIncomingStorage();
        $prevQueryParams = Screen::getCurrentQueryParams();

        $this->idGenerator->pushCurrentContext($instance->getContextIdentifier());
        try {
            $shouldReset = (bool) (Screen::getCurrentQueryParams()['reset'] ?? request()->query('reset', false));
            if ($shouldReset) {
                $instance->onResetScreen();
            }

            $this->initializeEventContext(
                screen: $instance,
                incomingStorage: Screen::getCurrentIncomingStorage(),
                queryParams: Screen::getCurrentQueryParams(),
                parent: $parentId
            );
            $instance->callPostLoadUI();

            $container = $instance->getContainer();
            if ($container !== null) {
                // Store the child screen's own snapshot with root=true and parent=$parentId
                // so direct events on the child screen can reconstruct its tree.
                $container->root(true);
                $this->cacheScreenSnapshot($instance, $container);

                // Attach to the host screen's container with root=false so it does not
                // collide with the host screen's root container.
                $container->root(false);
                $parent->add($container);

                $this->changesCollector->add($container->toJson());
            }

            $this->changesCollector->setStorage($instance->getStorageVariables());
        } finally {
            Screen::setCurrentIncomingStorage($prevIncomingStorage);
            Screen::setCurrentQueryParams($prevQueryParams);
            $this->idGenerator->popCurrentContext();
        }
    }

    /**
     * Hydrate live component instances with the values currently held in the client DOM
     * before capturing the $oldUI snapshot.
     *
     * @param  array<string, mixed>  $eventParameters
     */
    protected function hydrateClientComponentState(Container $container, array $eventParameters, ?int $triggerComponentId = null): void
    {
        if ($triggerComponentId !== null) {
            $triggerElement = $container->findById($triggerComponentId);
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

            $element = $container->findByName($paramKey);
            if (! ($element instanceof UIComponent)) {
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
     * Inject component references into protected properties.
     *
     * Uses reflection to find protected properties with UI component type hints.
     * If a property name matches a component name in the container,
     * the component is injected into that property.
     */
    protected function injectComponentReferences(Screen $screen, Container $container): void
    {
        $reflection = new ReflectionClass($screen);

        foreach ($reflection->getProperties(ReflectionProperty::IS_PROTECTED) as $property) {
            // Skip properties declared in Screen base class
            if ($property->getDeclaringClass()->getName() === Screen::class) {
                continue;
            }

            $propertyType = $property->getType();
            if (! $propertyType) {
                continue;
            }

            if ($propertyType instanceof ReflectionNamedType && $propertyType->isBuiltin()) {
                continue;
            }

            if (! ($propertyType instanceof ReflectionNamedType)) {
                continue;
            }

            $typeName = $propertyType->getName();

            // Only process UI component types from the current package namespace.
            if (str_starts_with($typeName, 'Idei\\Usim\\Components\\')) {
                $componentName = $property->getName();
                $component = $container->findByName($componentName);

                if ($component) {
                    $property->setValue($screen, $component);
                } elseif (! $propertyType->allowsNull()) {
                    $className = $screen::class;
                    throw new RuntimeException(
                        "Component '{$componentName}' not found in {$className}. ".
                            "Make sure the component exists or make the property nullable: protected ?{$typeName} \${$componentName};"
                    );
                }
            }
        }
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
                if (! empty($dirtyKeys)) {
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
     * Reconstruct UI container from JSON array.
     *
     * @param  array<int|string, array<string, mixed>>  $jsonUI  JSON representation of UI
     * @return Container Reconstructed container
     */
    protected function reconstructContainerFromJson(array $jsonUI, string $contextKey): Container
    {
        /** @var array<int|string, UIElement> $components */
        $components = [];
        $rootContainer = null;

        // First pass: instantiate all components
        foreach ($jsonUI as $id => $component) {
            $type = $component['type'] ?? null;
            if (! \is_string($type) || $type === '') {
                throw new RuntimeException('Unknown component type.');
            }

            $className = $this->mapTypeToClass($type);
            if (! $className) {
                throw new RuntimeException("Unknown component type '{$type}'.");
            }

            $numericId = (int) $id;

            // Reserve IDs from cached snapshots so future auto-generated IDs
            // in this request do not collide with already deserialized components.
            if (is_numeric($id)) {
                $this->idGenerator->reserveContextId($contextKey, $numericId);
            }

            $components[$id] = $className::deserialize($numericId, $component);
        }

        // Second pass: set up parent-child relationships
        foreach ($components as $id => $component) {
            $parentId = $jsonUI[$id]['parent'] ?? null;

            if ($component instanceof Container && $component->isRoot()) {
                $rootContainer = $component;
            }

            // Detached components (parent=null) are valid during incremental remove operations.
            if ($parentId === null || $parentId === '') {
                continue;
            }

            if (! \is_int($parentId) && ! \is_string($parentId)) {
                continue;
            }

            if (! $parentId || ! isset($components[$parentId])) {
                continue;
            }

            $components[$parentId]->connectChild($component);
        }

        // Third pass: post-connection initialization
        foreach ($components as $component) {
            $component->postConnect();
        }

        if (! $rootContainer) {
            throw new RuntimeException('No root container found in UI JSON.');
        }

        return $rootContainer;
    }

    /**
     * Map component type string to corresponding PHP class.
     *
     * @return class-string<UIElement>|null
     */
    protected function mapTypeToClass(string $type): ?string
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
            'textarea' => Textarea::class,
            'split' => Split::class,
            'timer' => Timer::class,
            default => null,
        };
    }

    /**
     * @phpstan-assert-if-true array<int|string, array<string, mixed>> $ui
     */
    protected function isTypedCachedScreenSnapshot(mixed $ui): bool
    {
        if (! \is_array($ui)) {
            return false;
        }

        foreach ($ui as $component) {
            if (! \is_array($component)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Cached snapshots can become stale after structural screen changes.
     * Reject snapshots that reference component parents not present in the payload
     * or table internals that no longer form a consistent subtree.
     *
     * @param  array<int|string, array<string, mixed>>  $ui
     */
    protected function isValidCachedScreenSnapshot(array $ui): bool
    {
        foreach ($ui as $componentId => $component) {
            $parent = $component['parent'] ?? null;
            $isRoot = (bool) ($component['root'] ?? false);
            if (! $isRoot && \is_int($parent) && ! isset($ui[$parent]) && ! isset($ui[(string) $parent])) {
                return false;
            }

            if (($component['type'] ?? null) !== 'table') {
                continue;
            }

            $rowsContainerId = $component['rows_container'] ?? null;
            $headerRowId = $component['header_row'] ?? null;

            if (! \is_int($rowsContainerId) || ! isset($ui[$rowsContainerId])) {
                return false;
            }

            if (($ui[$rowsContainerId]['parent'] ?? null) !== $componentId) {
                return false;
            }

            if (! \is_int($headerRowId) || ! isset($ui[$headerRowId])) {
                return false;
            }

            if (($ui[$headerRowId]['parent'] ?? null) !== $componentId) {
                return false;
            }
        }

        return true;
    }
}
