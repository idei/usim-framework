<?php

use App\UI\Screens\Demo\ItemListDemo;
use Idei\Usim\Components\ItemList;
use Idei\Usim\Enums\SelectionMode;
use Idei\Usim\Testing\ScreenTestHarness;
use Idei\Usim\UI;

test('UI::itemList() and UI::list() build an ItemList component with fluent CRUD, selection and ui-data config', function () {
    $list = UI::itemList('movies_list')
        ->title('Películas')
        ->label('Seleccionar películas')
        ->helpText('Ayuda de lista')
        ->items([
            ['id' => 1, 'label' => 'Inception', 'subtitle' => '2010', 'icon' => '🚀', 'badge' => 'Sci-Fi'],
            ['id' => 2, 'label' => 'The Matrix', 'subtitle' => '1999', 'icon' => '🚀', 'badge' => 'Sci-Fi'],
        ])
        ->selectionMode(SelectionMode::MULTIPLE)
        ->select([1])
        ->action('movies', ['featured' => true])
        ->searchable(true, 'Buscar película...')
        ->minSearchChars(2)
        ->debounce(250)
        ->creatable(true, true, 'Nueva película...', 'Agregar')
        ->editable(true, true)
        ->deletable(true, true, 'Borrar seleccionadas')
        ->onSelect('movie_selected')
        ->onCreate('movie_created')
        ->onUpdate('movie_updated')
        ->onDelete('movie_deleted')
        ->onBulkDelete('movies_bulk_deleted');

    $aliasList = UI::list('alias_list');

    expect($list)->toBeInstanceOf(ItemList::class)
        ->and($aliasList)->toBeInstanceOf(ItemList::class)
        ->and($list->getType())->toBe('item_list')
        ->and($list->getName())->toBe('movies_list')
        ->and($list->selectionMode())->toBe('multiple')
        ->and($list->select())->toBe([1])
        ->and($list->getSelectedIds())->toBe([1])
        ->and($list->get('action'))->toBe('movies')
        ->and($list->get('searchable'))->toBeTrue()
        ->and($list->get('min_search_chars'))->toBe(2)
        ->and($list->get('creatable'))->toBeTrue()
        ->and($list->get('editable'))->toBeTrue()
        ->and($list->get('deletable'))->toBeTrue();
});

test('ItemList supports item CRUD mutations and selection mode transitions in PHP', function () {
    $list = UI::itemList('test_list')
        ->items([
            ['id' => 10, 'label' => 'Alpha'],
            ['id' => 20, 'label' => 'Beta'],
        ])
        ->multipleSelect()
        ->select([10, 20]);

    expect($list->getItems())->toHaveCount(2)
        ->and($list->select())->toBe([10, 20]);

    // Add item
    $list->addItem(['id' => 30, 'label' => 'Gamma']);
    expect($list->getItems())->toHaveCount(3)
        ->and($list->findItem(30)['label'] ?? null)->toBe('Gamma');

    // Update item
    $list->updateItem(20, ['label' => 'Beta Updated']);
    expect($list->findItem(20)['label'] ?? null)->toBe('Beta Updated');

    // Remove item also cleans it from selected_items
    $list->removeItem(10);
    expect($list->getItems())->toHaveCount(2)
        ->and($list->getSelectedIds())->toBe([20]);

    // Switching to SINGLE mode keeps at most one selected item
    $list->select([20, 30]);
    $list->singleSelect();
    expect($list->selectionMode())->toBe('single')
        ->and($list->select())->toBe(20);

    // Switching to NONE clears selection
    $list->noSelection();
    expect($list->selectionMode())->toBe('none')
        ->and($list->select())->toBeNull()
        ->and($list->getSelectedIds())->toBe([]);
});

test('ItemListDemo renders initial movies catalog and supports GET /api/ui-data search', function () {
    $harness = ScreenTestHarness::for(ItemListDemo::class);
    $harness->render();

    $harness->assertHasComponent('movies_list')
        ->assertHasComponent('sel_selection_mode')
        ->assertHasComponent('btn_confirm_marathon');

    /** @var ItemList $moviesList */
    $moviesList = $harness->getComponent('movies_list');
    $initialItems = $moviesList->getItems();

    expect($initialItems)->not->toBeEmpty()
        ->and($moviesList->get('action'))->toBe('movies')
        ->and($moviesList->selectionMode())->toBe('multiple');

    // Test GET /api/ui-data query handler directly on screen
    /** @var ItemListDemo $screen */
    $screen = $harness->getScreen();
    $allMovies = $screen->getMovies();
    $filtered = $screen->getMovies('Matrix');

    expect($allMovies)->not->toBeEmpty()
        ->and($filtered)->not->toBeEmpty()
        ->and(count($filtered))->toBeLessThan(count($allMovies))
        ->and($filtered[0]['label'])->toContain('Matrix');
});

test('ItemListDemo handles inline create, update, single delete, bulk delete and selection mode changes', function () {
    $harness = ScreenTestHarness::for(ItemListDemo::class);
    $harness->render();

    /** @var ItemList $moviesList */
    $moviesList = $harness->getComponent('movies_list');
    $initialCount = count($moviesList->getItems());

    // 1. Create a new movie inline
    $harness->call('movie_created', ['value' => 'Dune: Parte Dos (2024)']);

    /** @var ItemList $moviesList */
    $moviesList = $harness->getComponent('movies_list');
    $itemsAfterCreate = $moviesList->getItems();
    expect($itemsAfterCreate)->toHaveCount($initialCount + 1)
        ->and($itemsAfterCreate[0]['label'])->toBe('Dune: Parte Dos (2024)');

    $createdId = $itemsAfterCreate[0]['id'];

    // 2. Update the created movie inline
    $harness->call('movie_updated', [
        'id' => $createdId,
        'value' => 'Dune: Parte Dos — Edición IMAX (2024)',
    ]);

    /** @var ItemList $moviesList */
    $moviesList = $harness->getComponent('movies_list');
    expect($moviesList->findItem($createdId)['label'] ?? null)->toBe('Dune: Parte Dos — Edición IMAX (2024)');

    // 3. Delete the created movie
    $harness->call('movie_deleted', ['id' => $createdId]);

    /** @var ItemList $moviesList */
    $moviesList = $harness->getComponent('movies_list');
    expect($moviesList->getItems())->toHaveCount($initialCount)
        ->and($moviesList->findItem($createdId))->toBeNull();

    // 4. Bulk delete 2 movies
    $remainingItems = $moviesList->getItems();
    $bulkIds = [$remainingItems[0]['id'], $remainingItems[1]['id']];
    $harness->call('movies_bulk_deleted', ['ids' => $bulkIds]);

    /** @var ItemList $moviesList */
    $moviesList = $harness->getComponent('movies_list');
    expect($moviesList->getItems())->toHaveCount($initialCount - 2);

    // 5. Switch selection mode to single
    $harness->call('selection_mode_changed', ['value' => 'single']);
    /** @var ItemList $moviesList */
    $moviesList = $harness->getComponent('movies_list');
    expect($moviesList->selectionMode())->toBe('single');
});

test('ItemList supports scrollbar configuration and maxHeight constraints', function () {
    $list = UI::itemList('scroll_list')
        ->maxHeight('350px')
        ->minHeight('150px')
        ->scrollbar('always');

    expect($list->get('max_height'))->toBe('350px')
        ->and($list->get('min_height'))->toBe('150px')
        ->and($list->get('scrollbar'))->toBe('always');

    $list->hideScrollbar();
    expect($list->get('scrollbar'))->toBe('hidden');

    $list->showScrollbar();
    expect($list->get('scrollbar'))->toBe('always');

    $list->scrollbar('auto');
    expect($list->get('scrollbar'))->toBe('auto');

    $list->scrollbar(false);
    expect($list->get('scrollbar'))->toBeFalse();
});

test('ItemListDemo layout configures movies_list scrollbar and max_height correctly', function () {
    $harness = ScreenTestHarness::for(ItemListDemo::class);
    $harness->render();

    /** @var ItemList $moviesList */
    $moviesList = $harness->getComponent('movies_list');

    expect($moviesList->get('scrollbar'))->toBe('always')
        ->and($moviesList->get('max_height'))->toBe('320px');
});

test('ItemList supports granular CRUD label and flag setters regardless of YAML rule order', function () {
    $list = UI::itemList('ordered_list')
        ->createPlaceholder('Write item...')
        ->createButtonLabel('Insert')
        ->createInline(false)
        ->editInline(false)
        ->allowBulkDelete(false)
        ->bulkDeleteLabel('Purge');

    expect($list->get('create_placeholder'))->toBe('Write item...')
        ->and($list->get('create_button_label'))->toBe('Insert')
        ->and($list->get('create_inline'))->toBeFalse()
        ->and($list->get('edit_inline'))->toBeFalse()
        ->and($list->get('bulk_deletable'))->toBeFalse()
        ->and($list->get('bulk_delete_label'))->toBe('Purge');

    $container = UI::container('item_list_yaml_order')->layoutDefinition([
        'grid' => [
            'cols' => 1,
            'children' => [
                'ordered_list' => [
                    'create_inline' => false,
                    'edit_inline' => false,
                    'allow_bulk_delete' => false,
                    'creatable' => true,
                    'editable' => true,
                    'deletable' => true,
                    'create_placeholder' => 'Type title...',
                    'create_button_label' => 'Save New',
                    'bulk_delete_label' => 'Delete Checked',
                ],
            ],
        ],
    ]);

    $yamlList = UI::itemList('ordered_list');
    $container->add($yamlList);
    $json = $container->toJson();
    $cfg = $json[$yamlList->getId()];

    expect($cfg['creatable'])->toBeTrue()
        ->and($cfg['create_inline'])->toBeFalse()
        ->and($cfg['editable'])->toBeTrue()
        ->and($cfg['edit_inline'])->toBeFalse()
        ->and($cfg['deletable'])->toBeTrue()
        ->and($cfg['bulk_deletable'])->toBeFalse()
        ->and($cfg['create_placeholder'])->toBe('Type title...')
        ->and($cfg['create_button_label'])->toBe('Save New')
        ->and($cfg['bulk_delete_label'])->toBe('Delete Checked');
});

test('ItemListDemo auto-hydrates translations from lang files across locales via layoutContainer()', function () {
    $originalLocale = app()->getLocale();

    foreach (['es', 'en'] as $locale) {
        app()->setLocale($locale);

        $harness = ScreenTestHarness::for(ItemListDemo::class);
        $harness->render(shouldReset: true);

        $harness->assertHasComponent('item_list_demo_wrapper');

        /** @var ItemList $moviesList */
        $moviesList = $harness->getComponent('movies_list');

        expect($harness->getComponent('item_list_demo_title')->get('text'))
            ->toBe(t('screens.demo.item_list_demo.item_list_demo_title'))
            ->and($harness->getComponent('sel_selection_mode')->get('label'))
            ->toBe(t('screens.demo.item_list_demo.selection_mode.label'))
            ->and($harness->getComponent('chk_confirm_delete')->get('label'))
            ->toBe(t('screens.demo.item_list_demo.confirm_delete.label'))
            ->and($harness->getComponent('btn_reset_catalog')->get('label'))
            ->toBe(t('screens.demo.item_list_demo.actions.reset_catalog'))
            ->and($harness->getComponent('btn_confirm_marathon')->get('label'))
            ->toBe(t('screens.demo.item_list_demo.actions.confirm_marathon'))
            ->and($moviesList->get('title'))
            ->toBe(t('screens.demo.item_list_demo.movies_list.title'))
            ->and($moviesList->get('search_placeholder'))
            ->toBe(t('screens.demo.item_list_demo.movies_list.search_placeholder'))
            ->and($moviesList->get('create_button_label'))
            ->toBe(t('screens.demo.item_list_demo.movies_list.create_button_label'))
            ->and($moviesList->get('bulk_delete_label'))
            ->toBe(t('screens.demo.item_list_demo.movies_list.bulk_delete_label'));
    }

    app()->setLocale($originalLocale);
});
