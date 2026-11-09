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
