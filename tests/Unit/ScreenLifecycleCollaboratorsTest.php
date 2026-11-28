<?php

declare(strict_types=1);

use Idei\Usim\Components\CardGrid;
use Idei\Usim\Components\Checkbox;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\Input;
use Idei\Usim\Components\ItemList;
use Idei\Usim\Components\Label;
use Idei\Usim\Components\RichText;
use Idei\Usim\Contracts\ComponentIdGeneratorInterface;
use Idei\Usim\Contracts\ItemList\BulkDeletable;
use Idei\Usim\Contracts\ItemList\Creatable;
use Idei\Usim\Contracts\ItemList\Deletable;
use Idei\Usim\Contracts\ItemList\Listable;
use Idei\Usim\Contracts\ItemList\Updatable;
use Idei\Usim\Contracts\UIDifferInterface;
use Idei\Usim\Contracts\UIStateRepositoryInterface;
use Idei\Usim\DTOs\ListItemDTO;
use Idei\Usim\Exceptions\UsimReadOnlyLifecycleException;
use Idei\Usim\Screen;
use Idei\Usim\Support\ComponentDiffer;
use Idei\Usim\Support\Lifecycle\ComponentServiceBinding;
use Idei\Usim\Support\Lifecycle\ComponentServiceBindingResolver;
use Idei\Usim\Support\Lifecycle\ComponentTreeStateSynchronizer;
use Idei\Usim\Support\Lifecycle\LayoutRenderCoordinator;
use Idei\Usim\Support\Lifecycle\ScreenHandlerDispatcher;
use Idei\Usim\Support\Lifecycle\ScreenSnapshotManager;
use Idei\Usim\Support\MethodParameterResolver;
use Idei\Usim\Support\UsimConfig;
use Idei\Usim\UI;
use Illuminate\Support\Collection;
use Mockery\MockInterface;

final class FullItemListTestService implements BulkDeletable, Creatable, Deletable, Listable, Updatable
{
    /** @var list<string> */
    public array $calls = [];

    public function list(string $search = '', array $context = []): Collection
    {
        $this->calls[] = "list:{$search}";

        return collect([
            new ListItemDTO(id: 1, label: $search !== '' ? "Match: {$search}" : 'Alpha'),
        ]);
    }

    public function create(string $label, array $payload = []): ListItemDTO
    {
        $this->calls[] = "create:{$label}";

        return new ListItemDTO(id: 99, label: $label);
    }

    public function update(int|string $id, string $label, array $payload = []): ?ListItemDTO
    {
        $this->calls[] = "update:{$id}:{$label}";

        return new ListItemDTO(id: $id, label: $label);
    }

    public function delete(int|string $id): bool
    {
        $this->calls[] = "delete:{$id}";

        return true;
    }

    public function deleteMany(array $ids): int
    {
        $this->calls[] = 'bulk_delete:'.implode(',', $ids);

        return count($ids);
    }
}

final class ListOnlyItemListTestService implements Listable
{
    public function list(string $search = '', array $context = []): Collection
    {
        return collect([new ListItemDTO(id: 1, label: 'Only')]);
    }
}

afterEach(function () {
    Mockery::close();
});

it('resolves ItemList service bindings across trigger id, name target, numeric id target, and custom action configs', function () {
    $resolver = new ComponentServiceBindingResolver;
    $service = new FullItemListTestService;

    $root = UI::container('binding_root')->root(true);
    $list = UI::itemList('catalog_list')->service($service);
    $otherBtn = UI::button('other_btn')->label('Click');
    $root->addMany([$list, $otherBtn]);

    $listId = $list->getId();
    $otherId = $otherBtn->getId();

    // 1. Standard parsed actions resolved by trigger component ID, name target, and numeric ID target
    $listQuery = $resolver->resolve($root, $listId, '__usim_item_list:catalog_list:list', queryOnly: true);
    expect($listQuery)->toBeInstanceOf(ComponentServiceBinding::class)
        ->and($listQuery?->component)->toBe($list)
        ->and($listQuery?->operation)->toBe('list');

    $byName = $resolver->resolve($root, $otherId, '__usim_item_list:catalog_list:create', queryOnly: false);
    expect($byName?->operation)->toBe('create')
        ->and($byName?->component)->toBe($list);

    $byNumericId = $resolver->resolve($root, null, "__usim_item_list:{$listId}:update", queryOnly: false);
    expect($byNumericId?->operation)->toBe('update')
        ->and($byNumericId?->component)->toBe($list);

    // 2. Custom (non-prefixed) action names for query and mutation events (both string and array configs)
    $list->action('custom_list_query');
    $list->onCreate('custom_create_action');
    $list->onUpdate('custom_update_action', ['extra' => 1]);
    $list->onDelete('custom_delete_action');
    $list->onBulkDelete('custom_bulk_delete_action');

    expect($resolver->resolve($root, $listId, 'custom_list_query', queryOnly: true)?->operation)->toBe('list')
        ->and($resolver->resolve($root, $listId, 'custom_create_action', queryOnly: false)?->operation)->toBe('create')
        ->and($resolver->resolve($root, $listId, 'custom_update_action', queryOnly: false)?->operation)->toBe('update')
        ->and($resolver->resolve($root, $listId, 'custom_delete_action', queryOnly: false)?->operation)->toBe('delete')
        ->and($resolver->resolve($root, $listId, 'custom_bulk_delete_action', queryOnly: false)?->operation)->toBe('bulk_delete');

    // 3. Query execution using parameters or queryParams search fallback
    $screen = new class extends Screen
    {
        protected function buildBaseUI(Container $container, ...$params): void {}
    };

    /** @var ComponentServiceBinding $listQuery */
    $resFromParams = $resolver->executeQuery($listQuery, ['search' => 'foo'], [], $screen, 'list');
    $resFromQuery = $resolver->executeQuery($listQuery, [], ['search' => 'bar'], $screen, 'list');
    $resNonScalar = $resolver->executeQuery($listQuery, ['search' => ['not-scalar']], [], $screen, 'list');

    expect($resFromParams[0]['label'])->toBe('Match: foo')
        ->and($resFromQuery[0]['label'])->toBe('Match: bar')
        ->and($resNonScalar[0]['label'])->toBe('Alpha');
});

it('rejects incompatible, unknown, or unsupported ItemList and CardGrid service bindings', function () {
    $resolver = new ComponentServiceBindingResolver;

    $root = UI::container('reject_root')->root(true);
    $listOnly = UI::itemList('readonly_list')->service(new ListOnlyItemListTestService);
    $grid = UI::cardGrid('catalog_grid')->items([
        ['id' => 1, 'title' => 'Card A'],
    ]);
    $label = UI::label('plain_label')->text('Plain');
    $root->addMany([$listOnly, $grid, $label]);

    // Missing / non-matching component
    expect($resolver->resolve($root, $label->getId(), 'unknown_action', queryOnly: false))->toBeNull()
        ->and($resolver->resolve($root, null, '__usim_item_list:missing:list', queryOnly: true))->toBeNull();

    // Query-only rejecting mutation operation & mutation-only rejecting list operation
    expect($resolver->resolve($root, $listOnly->getId(), '__usim_item_list:readonly_list:create', queryOnly: true))->toBeNull()
        ->and($resolver->resolve($root, $listOnly->getId(), '__usim_item_list:readonly_list:list', queryOnly: false))->toBeNull();

    // Unsupported mutation on Listable-only service
    expect($resolver->resolve($root, $listOnly->getId(), '__usim_item_list:readonly_list:delete', queryOnly: false))->toBeNull();

    // Unknown non-prefixed action on ItemList
    expect($resolver->resolve($root, $listOnly->getId(), 'completely_unknown', queryOnly: true))->toBeNull()
        ->and($resolver->resolve($root, $listOnly->getId(), 'completely_unknown', queryOnly: false))->toBeNull();

    // CardGrid resolution by trigger ID, name, numeric ID, custom query_action, and rejection on queryOnly / wrong op
    $gridId = $grid->getId();
    expect($resolver->resolve($root, $gridId, '__usim_card_grid:catalog_grid:query', queryOnly: false)?->operation)->toBe('query')
        ->and($resolver->resolve($root, null, '__usim_card_grid:catalog_grid:query', queryOnly: false)?->component)->toBe($grid)
        ->and($resolver->resolve($root, null, "__usim_card_grid:{$gridId}:query", queryOnly: false)?->component)->toBe($grid)
        ->and($resolver->resolve($root, $gridId, '__usim_card_grid:catalog_grid:query', queryOnly: true))->toBeNull()
        ->and($resolver->resolve($root, $gridId, '__usim_card_grid:catalog_grid:invalid_op', queryOnly: false))->toBeNull();

    $grid->queryAction('custom_grid_query');
    expect($resolver->resolve($root, $gridId, 'custom_grid_query', queryOnly: false)?->operation)->toBe('query');

    // Screen without container returns null; executeQuery on CardGrid throws BadMethodCallException
    $screen = new class extends Screen
    {
        protected function buildBaseUI(Container $container, ...$params): void {}
    };

    expect($resolver->resolveForScreen($screen, $gridId, 'custom_grid_query', queryOnly: false))->toBeNull();

    $cardGridBinding = new ComponentServiceBinding($grid, 'query');
    expect(fn () => $resolver->executeQuery($cardGridBinding, [], [], $screen, 'custom_grid_query'))
        ->toThrow(BadMethodCallException::class);
});

it('hydrates client component state for trigger and form parameter components and tracks dirty changes', function () {
    $sync = new ComponentTreeStateSynchronizer(new ComponentDiffer);

    $root = UI::container('hydrate_root')->root(true);
    $nested = UI::container('nested_box');
    $input = UI::input('username')->value('old');
    $richText = UI::richText('bio')->value('<p>old</p>');
    $checkbox = UI::checkbox('accept_terms')->checked(false);
    $multiCheckbox = UI::checkbox('roles');
    $list = UI::itemList('tags_list');
    $grid = UI::cardGrid('items_grid')->items([
        ['id' => 1, 'title' => 'First Card'],
    ]);

    $nested->addMany([$input, $richText, $checkbox]);
    $root->addMany([$nested, $multiCheckbox, $list, $grid]);

    // Trigger hydration for input (including null -> '')
    $sync->hydrateClientState($root, ['value' => null], $input->getId());
    expect($input->get('value'))->toBe('');

    // Trigger hydration for checkbox
    $sync->hydrateClientState($root, ['checked' => 1], $checkbox->getId());
    expect($checkbox->get('checked'))->toBeTrue();

    // Trigger hydration for item_list (array filtering non-scalar, single scalar, empty string)
    $sync->hydrateClientState($root, ['selected' => [1, 'two', ['ignored'], null]], $list->getId());
    expect($list->get('selected_items'))->toBe([1, 'two']);

    $sync->hydrateClientState($root, ['selected' => 'single'], $list->getId());
    expect($list->get('selected_items'))->toBe(['single']);

    $sync->hydrateClientState($root, ['selected' => ''], $list->getId());
    expect($list->get('selected_items'))->toBe([]);

    // Parameter hydration across component types while skipping reserved/empty keys
    $sync->hydrateClientState($root, [
        '' => 'ignored',
        'value' => 'reserved',
        'username' => 'alice',
        'bio' => '<p>new</p>',
        'accept_terms' => false,
        'roles' => ['admin', 'editor'],
        'tags_list' => [10, 20],
        'non_existent' => 'skipped',
    ]);

    expect($input->get('value'))->toBe('alice')
        ->and($richText->get('value'))->toBe('<p>new</p>')
        ->and($checkbox->get('checked'))->toBeFalse()
        ->and($multiCheckbox->get('selected_values'))->toBe(['admin', 'editor'])
        ->and($list->get('selected_items'))->toBe([10, 20]);

    // Dirty tracking clear & collect across nested containers and CardGrid + ItemCards
    $sync->clearDirtyState($root);
    expect($sync->collectDirtyChanges($root))->toBe([]);

    $input->value('bob');
    $cards = $grid->getCards();
    $firstCard = reset($cards);
    expect($firstCard)->not->toBeFalse();
    $firstCard->title('Updated Card');

    $dirty = $sync->collectDirtyChanges($root);
    expect($dirty)->toHaveKey($input->getId())
        ->and($dirty[$input->getId()]['value'])->toBe('bob')
        ->and($dirty)->toHaveKey($firstCard->getId())
        ->and($dirty[$firstCard->getId()]['title'])->toBe('Updated Card');
});

it('injects protected component references and throws on missing non-nullable components', function () {
    $sync = new ComponentTreeStateSynchronizer(new ComponentDiffer);

    $validScreen = new class extends Screen
    {
        protected Input $userName;

        protected ?Label $optionalLabel = null;

        protected string $plainString = 'keep';

        protected function buildBaseUI(Container $container, ...$params): void {}

        public function getInjectedInput(): Input
        {
            return $this->userName;
        }

        public function getOptionalLabel(): ?Label
        {
            return $this->optionalLabel;
        }
    };

    $container = UI::container('inject_root')->root(true);
    $input = UI::input('user_name');
    $container->add($input);

    $sync->injectComponentReferences($validScreen, $container);
    expect($validScreen->getInjectedInput())->toBe($input)
        ->and($validScreen->getOptionalLabel())->toBeNull();

    $missingScreen = new class extends Screen
    {
        protected Input $requiredMissing;

        protected function buildBaseUI(Container $container, ...$params): void {}
    };

    expect(fn () => $sync->injectComponentReferences($missingScreen, $container))
        ->toThrow(RuntimeException::class, "Component 'requiredMissing' not found");
});

it('validates cached snapshots and handles invalid JSON reconstruction errors cleanly', function () {
    /** @var UIStateRepositoryInterface&MockInterface $repo */
    $repo = Mockery::mock(UIStateRepositoryInterface::class);
    /** @var ComponentIdGeneratorInterface&MockInterface $idGen */
    $idGen = Mockery::mock(ComponentIdGeneratorInterface::class);
    $idGen->shouldReceive('reserveContextId');

    $manager = new ScreenSnapshotManager($repo, $idGen);

    expect($manager->isTypedSnapshot('not-an-array'))->toBeFalse()
        ->and($manager->isTypedSnapshot([1 => 'not-an-array']))->toBeFalse()
        ->and($manager->isTypedSnapshot([1 => ['type' => 'container']]))->toBeTrue();

    // Missing parent on non-root
    expect($manager->isValidSnapshot([
        1 => ['type' => 'container', 'root' => true, 'parent' => 'main'],
        2 => ['type' => 'label', 'parent' => 999],
    ]))->toBeFalse();

    // Valid table snapshot vs broken rows_container / header_row
    expect($manager->isValidSnapshot([
        1 => ['type' => 'container', 'root' => true, 'parent' => 'main'],
        2 => ['type' => 'table', 'parent' => 1, 'rows_container' => 3, 'header_row' => 4],
        3 => ['type' => 'container', 'parent' => 2],
        4 => ['type' => 'tableheaderrow', 'parent' => 2],
    ]))->toBeTrue();

    expect($manager->isValidSnapshot([
        1 => ['type' => 'table', 'root' => false, 'parent' => null, 'rows_container' => 99, 'header_row' => 4],
    ]))->toBeFalse();

    expect($manager->isValidSnapshot([
        1 => ['type' => 'table', 'parent' => null, 'rows_container' => 3, 'header_row' => 4],
        3 => ['type' => 'container', 'parent' => 999],
        4 => ['type' => 'tableheaderrow', 'parent' => 1],
    ]))->toBeFalse();

    expect($manager->isValidSnapshot([
        1 => ['type' => 'table', 'parent' => null, 'rows_container' => 3, 'header_row' => 99],
        3 => ['type' => 'container', 'parent' => 1],
    ]))->toBeFalse();

    expect($manager->isValidSnapshot([
        1 => ['type' => 'table', 'parent' => null, 'rows_container' => 3, 'header_row' => 4],
        3 => ['type' => 'container', 'parent' => 1],
        4 => ['type' => 'tableheaderrow', 'parent' => 999],
    ]))->toBeFalse();

    // Reconstruction error paths
    expect($manager->mapTypeToClass('rich_text'))->toBe(RichText::class)
        ->and($manager->mapTypeToClass('does_not_exist'))->toBeNull();

    expect(fn () => $manager->reconstructContainerFromJson([1 => ['type' => '']], 'ctx'))
        ->toThrow(RuntimeException::class, 'Unknown component type.');

    expect(fn () => $manager->reconstructContainerFromJson([1 => ['type' => 'bogus']], 'ctx'))
        ->toThrow(RuntimeException::class, "Unknown component type 'bogus'.");

    expect(fn () => $manager->reconstructContainerFromJson([1 => ['type' => 'label', 'parent' => null]], 'ctx'))
        ->toThrow(RuntimeException::class, 'No root container found in UI JSON.');
});

it('applies standalone portal fallbacks for ignore, inline_top, and inline modes', function () {
    /** @var ComponentIdGeneratorInterface&MockInterface $idGen */
    $idGen = Mockery::mock(ComponentIdGeneratorInterface::class);
    $coordinator = new LayoutRenderCoordinator($idGen, app(UsimConfig::class));

    $root = UI::container('standalone_root')->root(true);
    $first = UI::label('first_lbl')->text('First');
    $portalIgnored = UI::container('portal_ignored')->portalSlot('right_panel', fallback: 'ignore');
    $portalInlineTop = UI::container('portal_top')->portalSlot('top_bar', fallback: 'inline_top');
    $portalInline = UI::container('portal_inline')->portalSlot('bottom_panel', fallback: 'inline');
    $root->addMany([$first, $portalIgnored, $portalInlineTop, $portalInline]);

    $coordinator->applyStandalonePortalFallbacks($root);

    $ignoredJson = $portalIgnored->toJson()[$portalIgnored->getId()];
    $topJson = $portalInlineTop->toJson()[$portalInlineTop->getId()];
    $inlineJson = $portalInline->toJson()[$portalInline->getId()];

    expect($ignoredJson['portal_unmounted'] ?? false)->toBeTrue()
        ->and($ignoredJson['portal_target_id'] ?? null)->toBeNull()
        ->and($topJson['portal_target'] ?? null)->toBeNull()
        ->and($inlineJson['portal_target'] ?? null)->toBeNull();

    // inline_top portal was moved to the start of $root's children
    $children = array_values($root->getChildren());
    expect($children[0]->getId())->toBe($portalInlineTop->getId());
});

it('enforces read-only mutation guardrails in ScreenHandlerDispatcher', function () {
    $dispatcher = new ScreenHandlerDispatcher(new MethodParameterResolver);
    $screen = new class extends Screen
    {
        protected function buildBaseUI(Container $container, ...$params): void {}
    };

    $dispatcher->assertNoReadOnlyMutations($screen, 'getItems', []);

    expect(fn () => $dispatcher->assertNoReadOnlyMutations($screen, 'getItems', [10 => ['text' => 'mutated']]))
        ->toThrow(UsimReadOnlyLifecycleException::class);

    expect(fn () => $dispatcher->dispatchQuery($screen, 'missingQuery', [], [], [], null))
        ->toThrow(BadMethodCallException::class);
});
