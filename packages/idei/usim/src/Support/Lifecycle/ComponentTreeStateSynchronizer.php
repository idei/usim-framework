<?php

declare(strict_types=1);

namespace Idei\Usim\Support\Lifecycle;

use Idei\Usim\Components\CardGrid;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\UIComponent;
use Idei\Usim\Contracts\UIDifferInterface;
use Idei\Usim\Contracts\UIElement;
use Idei\Usim\Screen;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use RuntimeException;

/**
 * Synchronizes component tree state during Screen lifecycles:
 * - Client DOM state hydration prior to capturing oldUI snapshots
 * - Dirty-key clearing and collection across nested containers and CardGrids
 * - Diff response construction preserving transformed serialized values
 * - Reflection-based injection of protected UI component properties
 */
final class ComponentTreeStateSynchronizer
{
    /**
     * @var list<string>
     */
    private const array VALUE_INPUT_TYPES = ['input', 'textarea', 'rich_text', 'select'];

    /**
     * @var list<string>
     */
    private const array ITEM_LIST_TYPES = ['item_list', 'itemlist'];

    /**
     * @var list<string>
     */
    private const array RESERVED_EVENT_KEYS = ['value', 'checked', 'name', '_caller_screen_id', '_caller_service_id'];

    /**
     * @var list<string>
     */
    private const array IGNORED_DIRTY_KEYS = ['type', 'parent', '_order', '_storage_path', '_image_placeholder', 'static_items'];

    public function __construct(
        private readonly UIDifferInterface $differ,
    ) {}

    /**
     * Hydrate live component instances with the values currently held in the client DOM
     * before capturing the $oldUI snapshot.
     *
     * @param  array<string, mixed>  $eventParameters
     */
    public function hydrateClientState(Container $container, array $eventParameters, ?int $triggerComponentId = null): void
    {
        if ($triggerComponentId !== null) {
            $this->hydrateTriggerComponent($container, $eventParameters, $triggerComponentId);
        }

        $this->hydrateParameterComponents($container, $eventParameters);
    }

    /**
     * Reset dirty tracking across all leaf components in a container tree.
     */
    public function clearDirtyState(Container $container): void
    {
        foreach ($container->getChildren() as $child) {
            if ($child instanceof CardGrid) {
                $this->clearCardGridDirtyState($child);
            } elseif ($child instanceof UIComponent) {
                $child->clearDirtyKeys();
            } elseif ($child instanceof Container) {
                $this->clearDirtyState($child);
            }
        }
    }

    /**
     * Collect properties explicitly mutated via setConfig() during the current event lifecycle.
     *
     * @return array<int, array<string, mixed>>
     */
    public function collectDirtyChanges(Container $container): array
    {
        $dirtyChanges = [];

        foreach ($container->getChildren() as $child) {
            if ($child instanceof CardGrid) {
                $this->collectCardGridDirtyChanges($child, $dirtyChanges);
            } elseif ($child instanceof UIComponent) {
                $this->collectComponentDirtyProps($child, $dirtyChanges);
            } elseif ($child instanceof Container) {
                foreach ($this->collectDirtyChanges($child) as $id => $props) {
                    $dirtyChanges[$id] = array_merge($dirtyChanges[$id] ?? [], $props);
                }
            }
        }

        return $dirtyChanges;
    }

    /**
     * Inject component references into protected Screen properties matching component names.
     */
    public function injectComponentReferences(Screen $screen, Container $container): void
    {
        $reflection = new ReflectionClass($screen);

        foreach ($reflection->getProperties(ReflectionProperty::IS_PROTECTED) as $property) {
            $propertyType = $this->resolveInjectableComponentType($property);
            if ($propertyType === null) {
                continue;
            }

            $this->injectSingleComponentProperty($screen, $container, $property, $propertyType);
        }
    }

    /**
     * Build indexed diff response comparing old and new UI snapshots.
     *
     * @return array<int|string, array<string, mixed>>
     */
    public function buildDiffResponse(Screen $screen, bool $reload = false): array
    {
        $oldUI = $screen->getOldUI() ?? [];
        $newUI = $screen->getNewUI() ?? [];

        $diff = $reload
            ? $this->differ->compare([], $newUI)
            : $this->differ->compare($oldUI, $newUI);

        $container = $screen->getContainer();
        if (! $reload && $container !== null) {
            $diff = $this->mergeDirtyComponentChanges($diff, $newUI, $container);
        }

        return $this->attachComponentTypes($diff, $newUI);
    }

    /**
     * @param  array<string, mixed>  $eventParameters
     */
    private function hydrateTriggerComponent(Container $container, array $eventParameters, int $triggerComponentId): void
    {
        $triggerElement = $container->findById($triggerComponentId);
        if (! $triggerElement instanceof UIComponent) {
            return;
        }

        $type = $triggerElement->getType();
        if (in_array($type, self::VALUE_INPUT_TYPES, true) && array_key_exists('value', $eventParameters)) {
            $this->syncScalarOrNullValue($triggerElement, $eventParameters['value']);
        } elseif ($type === 'checkbox' && array_key_exists('checked', $eventParameters)) {
            $triggerElement->syncClientConfig('checked', (bool) $eventParameters['checked']);
        } elseif (in_array($type, self::ITEM_LIST_TYPES, true) && array_key_exists('selected', $eventParameters)) {
            $triggerElement->syncClientConfig('selected_items', $this->normalizeSelectedItems($eventParameters['selected']));
        }
    }

    /**
     * @param  array<string, mixed>  $eventParameters
     */
    private function hydrateParameterComponents(Container $container, array $eventParameters): void
    {
        foreach ($eventParameters as $paramKey => $paramValue) {
            if ($paramKey === '' || in_array($paramKey, self::RESERVED_EVENT_KEYS, true)) {
                continue;
            }

            $element = $container->findByName($paramKey);
            if ($element instanceof UIComponent) {
                $this->syncParameterComponentValue($element, $paramValue);
            }
        }
    }

    private function syncParameterComponentValue(UIComponent $element, mixed $paramValue): void
    {
        $type = $element->getType();

        if (in_array($type, self::VALUE_INPUT_TYPES, true)) {
            $this->syncScalarOrNullValue($element, $paramValue);
        } elseif ($type === 'checkbox') {
            $this->syncCheckboxParameterValue($element, $paramValue);
        } elseif (in_array($type, self::ITEM_LIST_TYPES, true)) {
            $element->syncClientConfig('selected_items', $this->normalizeSelectedItems($paramValue));
        }
    }

    private function syncScalarOrNullValue(UIComponent $element, mixed $value): void
    {
        if (is_scalar($value) || $value === null) {
            $element->syncClientConfig('value', $value ?? '');
        }
    }

    private function syncCheckboxParameterValue(UIComponent $element, mixed $paramValue): void
    {
        if (is_bool($paramValue)) {
            $element->syncClientConfig('checked', $paramValue);
        } elseif (is_array($paramValue)) {
            $element->syncClientConfig('selected_values', $paramValue);
        }
    }

    /**
     * @return list<int|string>
     */
    private function normalizeSelectedItems(mixed $rawValue): array
    {
        if (is_array($rawValue)) {
            return array_values(array_filter(
                $rawValue,
                static fn (mixed $v): bool => is_int($v) || is_string($v)
            ));
        }

        if ((is_int($rawValue) || is_string($rawValue)) && $rawValue !== '') {
            return [$rawValue];
        }

        return [];
    }

    private function clearCardGridDirtyState(CardGrid $cardGrid): void
    {
        $cardGrid->clearDirtyKeys();
        foreach ($cardGrid->getCards() as $card) {
            $card->clearDirtyKeys();
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $dirtyChanges
     */
    private function collectCardGridDirtyChanges(CardGrid $cardGrid, array &$dirtyChanges): void
    {
        $this->collectComponentDirtyProps($cardGrid, $dirtyChanges);
        foreach ($cardGrid->getCards() as $card) {
            $this->collectComponentDirtyProps($card, $dirtyChanges);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $dirtyChanges
     */
    private function collectComponentDirtyProps(UIComponent $component, array &$dirtyChanges): void
    {
        $dirtyKeys = $component->getDirtyKeys();
        if (empty($dirtyKeys)) {
            return;
        }

        $id = $component->getId();
        foreach ($dirtyKeys as $key) {
            if (! in_array($key, self::IGNORED_DIRTY_KEYS, true)) {
                $dirtyChanges[$id][$key] = $component->get($key);
            }
        }
    }

    private function resolveInjectableComponentType(ReflectionProperty $property): ?ReflectionNamedType
    {
        if ($property->getDeclaringClass()->getName() === Screen::class) {
            return null;
        }

        $propertyType = $property->getType();
        if (! $propertyType instanceof ReflectionNamedType || $propertyType->isBuiltin()) {
            return null;
        }

        $typeName = $propertyType->getName();
        if (str_starts_with($typeName, 'Idei\\Usim\\Components\\') || is_subclass_of($typeName, UIElement::class)) {
            return $propertyType;
        }

        return null;
    }

    private function injectSingleComponentProperty(
        Screen $screen,
        Container $container,
        ReflectionProperty $property,
        ReflectionNamedType $propertyType
    ): void {
        $componentName = $property->getName();
        $component = $container->findByName($componentName)
            ?? $container->findByName(Str::snake($componentName));

        if ($component !== null) {
            $property->setValue($screen, $component);

            return;
        }

        if (! $propertyType->allowsNull()) {
            $className = $screen::class;
            $typeName = $propertyType->getName();
            throw new RuntimeException(
                "Component '{$componentName}' not found in {$className}. ".
                    "Make sure the component exists or make the property nullable: protected ?{$typeName} \${$componentName};"
            );
        }
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $diff
     * @param  array<int|string, array<string, mixed>>  $newUI
     * @return array<int|string, array<string, mixed>>
     */
    private function mergeDirtyComponentChanges(array $diff, array $newUI, Container $container): array
    {
        foreach ($this->collectDirtyChanges($container) as $componentId => $dirtyProps) {
            if (! isset($newUI[$componentId]) || ($newUI[$componentId]['parent'] ?? null) === null) {
                continue;
            }

            foreach ($dirtyProps as $propKey => $propValue) {
                $diff[$componentId][$propKey] = array_key_exists($propKey, $newUI[$componentId])
                    ? $newUI[$componentId][$propKey]
                    : $propValue;
            }
        }

        return $diff;
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $diff
     * @param  array<int|string, array<string, mixed>>  $newUI
     * @return array<int|string, array<string, mixed>>
     */
    private function attachComponentTypes(array $diff, array $newUI): array
    {
        $result = [];

        foreach ($diff as $componentId => $changes) {
            if (isset($newUI[$componentId]['type'])) {
                $changes['type'] = $newUI[$componentId]['type'];
            }

            $result[$componentId] = $changes;
        }

        return $result;
    }
}
