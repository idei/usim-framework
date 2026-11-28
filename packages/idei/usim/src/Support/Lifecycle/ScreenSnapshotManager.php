<?php

declare(strict_types=1);

namespace Idei\Usim\Support\Lifecycle;

use Idei\Usim\Components\Button;
use Idei\Usim\Components\Calendar;
use Idei\Usim\Components\Card;
use Idei\Usim\Components\CardGrid;
use Idei\Usim\Components\Carousel;
use Idei\Usim\Components\Checkbox;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\Form;
use Idei\Usim\Components\Image;
use Idei\Usim\Components\Input;
use Idei\Usim\Components\ItemCard;
use Idei\Usim\Components\ItemList;
use Idei\Usim\Components\Label;
use Idei\Usim\Components\MenuDropdown;
use Idei\Usim\Components\NavTree;
use Idei\Usim\Components\NewLabel;
use Idei\Usim\Components\RichText;
use Idei\Usim\Components\Select;
use Idei\Usim\Components\Split;
use Idei\Usim\Components\Table;
use Idei\Usim\Components\TableCell;
use Idei\Usim\Components\TableHeaderCell;
use Idei\Usim\Components\TableHeaderRow;
use Idei\Usim\Components\TableRow;
use Idei\Usim\Components\Textarea;
use Idei\Usim\Components\Timer;
use Idei\Usim\Components\Uploader;
use Idei\Usim\Contracts\ComponentIdGeneratorInterface;
use Idei\Usim\Contracts\UIElement;
use Idei\Usim\Contracts\UIStateRepositoryInterface;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\Screen;
use Idei\Usim\UI;
use RuntimeException;

/**
 * Manages cached Screen snapshots: validation, persistence, clearing,
 * and deterministic JSON-to-Container tree reconstruction.
 */
final class ScreenSnapshotManager
{
    /**
     * @var array<string, class-string<UIElement>>
     */
    private const array TYPE_CLASS_MAP = [
        'label' => Label::class,
        'newlabel' => NewLabel::class,
        'button' => Button::class,
        'input' => Input::class,
        'select' => Select::class,
        'checkbox' => Checkbox::class,
        'card' => Card::class,
        'image' => Image::class,
        'table' => Table::class,
        'container' => Container::class,
        'tablerow' => TableRow::class,
        'tablecell' => TableCell::class,
        'tableheadercell' => TableHeaderCell::class,
        'form' => Form::class,
        'tableheaderrow' => TableHeaderRow::class,
        'menudropdown' => MenuDropdown::class,
        'navtree' => NavTree::class,
        'nav_tree' => NavTree::class,
        'uploader' => Uploader::class,
        'calendar' => Calendar::class,
        'carousel' => Carousel::class,
        'textarea' => Textarea::class,
        'rich_text' => RichText::class,
        'richtext' => RichText::class,
        'split' => Split::class,
        'timer' => Timer::class,
        'item_list' => ItemList::class,
        'itemlist' => ItemList::class,
        'item_card' => ItemCard::class,
        'itemcard' => ItemCard::class,
        'card_grid' => CardGrid::class,
        'cardgrid' => CardGrid::class,
    ];

    public function __construct(
        private readonly UIStateRepositoryInterface $stateRepository,
        private readonly ComponentIdGeneratorInterface $idGenerator,
    ) {}

    /**
     * Reconstruct the current screen component tree from cache,
     * regenerating it via buildBaseUI if missing or invalid.
     */
    public function reconstructTree(Screen $screen, mixed ...$params): Container
    {
        $jsonUI = $this->getCachedSnapshot($screen, ...$params);

        return $this->reconstructContainerFromJson($jsonUI, $screen->getContextIdentifier());
    }

    /**
     * Get stored UI state from cache, regenerating it if missing or invalid.
     *
     * @return array<int|string, array<string, mixed>>
     */
    public function getCachedSnapshot(Screen $screen, mixed ...$params): array
    {
        $contextKey = $screen->getContextIdentifier();
        $cachedUI = $this->stateRepository->get($contextKey);

        if ($this->isTypedSnapshot($cachedUI) && $this->isValidSnapshot($cachedUI)) {
            $this->restoreStateVariables($screen, $contextKey);

            return $cachedUI;
        }

        if ($cachedUI !== null) {
            $this->stateRepository->clear($contextKey);
        }

        $container = $this->buildRootContainer($screen, $contextKey);
        $screen->callBuildBaseUI($container, ...$params);

        $ui = $container->root(true)->toJson();
        $this->cacheSnapshot($screen, $container);

        return $ui;
    }

    /**
     * Store UI state snapshot and internal state in repository cache.
     */
    public function cacheSnapshot(Screen $screen, Container $container): void
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
    public function clearCachedSnapshot(Screen $screen): bool
    {
        $contextKey = $screen->getContextIdentifier();
        $this->stateRepository->clearScreenState($contextKey);

        return $this->stateRepository->clear($contextKey);
    }

    /**
     * Reconstruct a UI Container tree from an indexed JSON array snapshot.
     *
     * @param  array<int|string, array<string, mixed>>  $jsonUI
     */
    public function reconstructContainerFromJson(array $jsonUI, string $contextKey): Container
    {
        $components = $this->instantiateComponents($jsonUI, $contextKey);
        $rootContainer = $this->connectComponentHierarchy($components, $jsonUI);

        foreach ($components as $component) {
            $component->postConnect();
        }

        if (! $rootContainer instanceof Container) {
            throw new RuntimeException('No root container found in UI JSON.');
        }

        return $rootContainer;
    }

    /**
     * Map component type string to corresponding PHP class.
     *
     * @return class-string<UIElement>|null
     */
    public function mapTypeToClass(string $type): ?string
    {
        return self::TYPE_CLASS_MAP[$type] ?? null;
    }

    /**
     * @phpstan-assert-if-true array<int|string, array<string, mixed>> $ui
     */
    public function isTypedSnapshot(mixed $ui): bool
    {
        if (! is_array($ui)) {
            return false;
        }

        foreach ($ui as $component) {
            if (! is_array($component)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Reject snapshots that reference missing parents or inconsistent table subtrees.
     *
     * @param  array<int|string, array<string, mixed>>  $ui
     */
    public function isValidSnapshot(array $ui): bool
    {
        foreach ($ui as $componentId => $component) {
            if ($this->hasMissingParent($ui, $component)) {
                return false;
            }

            if (($component['type'] ?? null) === 'table' && ! $this->isValidTableSnapshot($ui, $componentId, $component)) {
                return false;
            }
        }

        return true;
    }

    private function restoreStateVariables(Screen $screen, string $contextKey): void
    {
        $cachedState = $this->stateRepository->getScreenState($contextKey);
        if (! empty($cachedState)) {
            $screen->injectStateVariables($cachedState);
        }
    }

    private function buildRootContainer(Screen $screen, string $contextKey): Container
    {
        $currentClassSlug = strtolower(str_replace('\\', '_', $screen::class))
            .($screen->modalLayerIndex > 0 ? "_{$screen->modalLayerIndex}" : '');

        return UI::container($currentClassSlug, $contextKey)
            ->parent($screen->parent)
            ->modalLayerIndex($screen->modalLayerIndex)
            ->layout(LayoutType::VERTICAL)
            ->justifyContent('center')
            ->alignItems('center');
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $jsonUI
     * @return array<int|string, UIElement>
     */
    private function instantiateComponents(array $jsonUI, string $contextKey): array
    {
        $components = [];

        foreach ($jsonUI as $id => $component) {
            $className = $this->resolveComponentClass($component);
            $numericId = (int) $id;

            if (is_numeric($id)) {
                $this->idGenerator->reserveContextId($contextKey, $numericId);
            }

            $components[$id] = $className::deserialize($numericId, $component);
        }

        return $components;
    }

    /**
     * @param  array<string, mixed>  $component
     * @return class-string<UIElement>
     */
    private function resolveComponentClass(array $component): string
    {
        $type = $component['type'] ?? null;
        if (! is_string($type) || $type === '') {
            throw new RuntimeException('Unknown component type.');
        }

        $className = $this->mapTypeToClass($type);
        if ($className === null) {
            throw new RuntimeException("Unknown component type '{$type}'.");
        }

        return $className;
    }

    /**
     * @param  array<int|string, UIElement>  $components
     * @param  array<int|string, array<string, mixed>>  $jsonUI
     */
    private function connectComponentHierarchy(array $components, array $jsonUI): ?Container
    {
        $rootContainer = null;

        foreach ($components as $id => $component) {
            if ($component instanceof Container && $component->isRoot()) {
                $rootContainer = $component;
            }

            $parentId = $this->resolveConnectableParentId($jsonUI[$id]['parent'] ?? null, $components);
            if ($parentId !== null) {
                $components[$parentId]->connectChild($component);
            }
        }

        return $rootContainer;
    }

    /**
     * @param  array<int|string, UIElement>  $components
     */
    private function resolveConnectableParentId(mixed $parentId, array $components): int|string|null
    {
        if (! is_int($parentId) && ! is_string($parentId)) {
            return null;
        }

        if (! $parentId || ! isset($components[$parentId])) {
            return null;
        }

        return $parentId;
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $ui
     * @param  array<string, mixed>  $component
     */
    private function hasMissingParent(array $ui, array $component): bool
    {
        $parent = $component['parent'] ?? null;
        $isRoot = (bool) ($component['root'] ?? false);

        return ! $isRoot
            && is_int($parent)
            && ! isset($ui[$parent])
            && ! isset($ui[(string) $parent]);
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $ui
     * @param  array<string, mixed>  $component
     */
    private function isValidTableSnapshot(array $ui, int|string $componentId, array $component): bool
    {
        return $this->hasValidTableChild($ui, $componentId, $component['rows_container'] ?? null)
            && $this->hasValidTableChild($ui, $componentId, $component['header_row'] ?? null);
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $ui
     */
    private function hasValidTableChild(array $ui, int|string $tableId, mixed $childId): bool
    {
        if (! is_int($childId) || ! isset($ui[$childId])) {
            return false;
        }

        return ($ui[$childId]['parent'] ?? null) === $tableId;
    }
}
