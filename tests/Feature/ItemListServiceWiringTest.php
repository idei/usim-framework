<?php

use App\Services\Movie\SessionMovieCatalogService;
use App\UI\Screens\Demo\ItemListDemo;
use Idei\Usim\Components\ItemList;
use Idei\Usim\Contracts\ItemList\Creatable;
use Idei\Usim\Contracts\ItemList\Deletable;
use Idei\Usim\Contracts\ItemList\Listable;
use Idei\Usim\Screen;
use Idei\Usim\UI;

it('infers ItemList UI capabilities strictly from implemented service contracts', function () {
    $readOnlyService = new class implements Listable
    {
        public function list(string $search = '', array $context = []): array
        {
            return [
                ['id' => 1, 'label' => 'Alpha'],
                ['id' => 2, 'label' => 'Beta'],
            ];
        }
    };

    $readOnlyList = UI::itemList('readonly_list')->service($readOnlyService);

    expect($readOnlyList->get('creatable'))->toBeFalse()
        ->and($readOnlyList->get('editable'))->toBeFalse()
        ->and($readOnlyList->get('deletable'))->toBeFalse()
        ->and($readOnlyList->get('bulk_deletable'))->toBeFalse()
        ->and($readOnlyList->get('action'))->toBe($readOnlyList->serviceActionFor('list'))
        ->and($readOnlyList->getItems())->toHaveCount(2);

    $createAndDeleteService = new class implements Creatable, Deletable, Listable
    {
        public function list(string $search = '', array $context = []): array
        {
            return [['id' => 1, 'label' => 'Item 1']];
        }

        public function create(string $label, array $payload = []): array
        {
            return ['id' => 2, 'label' => $label];
        }

        public function delete(int|string $id): bool
        {
            return true;
        }
    };

    $partialList = UI::itemList('partial_list')->service($createAndDeleteService);

    expect($partialList->get('creatable'))->toBeTrue()
        ->and($partialList->get('editable'))->toBeFalse()
        ->and($partialList->get('deletable'))->toBeTrue()
        ->and($partialList->get('bulk_deletable'))->toBeTrue();
});

it('rejects services that do not implement any ItemList contract', function () {
    expect(fn () => UI::itemList('invalid_list')->service(new stdClass))
        ->toThrow(InvalidArgumentException::class);
});

it('falls back to Deletable::delete when BulkDeletable is not implemented', function () {
    $deletedIds = [];

    $singleDeleteOnly = new class($deletedIds) implements Deletable, Listable
    {
        /** @var list<int|string> */
        public array $deleted;

        public function __construct(array &$deleted)
        {
            $this->deleted = &$deleted;
        }

        public function list(string $search = '', array $context = []): array
        {
            return [
                ['id' => 10, 'label' => 'Ten'],
                ['id' => 20, 'label' => 'Twenty'],
            ];
        }

        public function delete(int|string $id): bool
        {
            $this->deleted[] = $id;

            return true;
        }
    };

    $list = UI::itemList('fallback_bulk_list')->service($singleDeleteOnly);
    $count = $list->performServiceBulkDelete([10, 20]);

    expect($count)->toBe(2)
        ->and($singleDeleteOnly->deleted)->toBe([10, 20])
        ->and($list->getItems())->toHaveCount(2);
});

it('serves ItemListDemo queries via GET /api/ui-data and CRUD mutations via POST /api/ui-event without Screen handlers', function () {
    $screen = Screen::make(ItemListDemo::class);
    $screen->render(parent: null, shouldReset: true);

    /** @var ItemList $moviesList */
    $moviesList = $screen->getContainer()->findByName('movies_list');
    $componentId = $moviesList->getId();
    $initialCount = count($moviesList->getItems());

    // 1. GET /api/ui-data search handled automatically by Listable service
    $searchResponse = $this->getJson('/api/ui-data?component_id='.$componentId.'&action='.urlencode($moviesList->serviceActionFor('list')).'&search=Inception');
    $searchResponse->assertOk();
    $searchResults = $searchResponse->json();
    expect($searchResults)->toHaveCount(1)
        ->and($searchResults[0]['label'])->toContain('Inception');

    // 2. POST /api/ui-event create handled automatically by Creatable service
    $createResponse = $this->postJson('/api/ui-event', [
        'component_id' => $componentId,
        'event' => 'action',
        'action' => $moviesList->serviceActionFor('create'),
        'parameters' => ['value' => 'Arrival (2016)'],
    ]);
    $createResponse->assertOk();

    $catalogAfterCreate = app(SessionMovieCatalogService::class)->list();
    expect($catalogAfterCreate)->toHaveCount($initialCount + 1)
        ->and($catalogAfterCreate[0]['label'])->toBe('Arrival (2016)');

    $createdId = $catalogAfterCreate[0]['id'];

    // 3. POST /api/ui-event update handled automatically by Updatable service
    $updateResponse = $this->postJson('/api/ui-event', [
        'component_id' => $componentId,
        'event' => 'action',
        'action' => $moviesList->serviceActionFor('update'),
        'parameters' => ['id' => $createdId, 'value' => 'Arrival (2016) — Director Cut'],
    ]);
    $updateResponse->assertOk();

    $updatedMatches = app(SessionMovieCatalogService::class)->list('Director Cut');
    expect($updatedMatches)->toHaveCount(1);

    // 4. POST /api/ui-event delete handled automatically by Deletable service
    $deleteResponse = $this->postJson('/api/ui-event', [
        'component_id' => $componentId,
        'event' => 'action',
        'action' => $moviesList->serviceActionFor('delete'),
        'parameters' => ['id' => $createdId],
    ]);
    $deleteResponse->assertOk();

    expect(app(SessionMovieCatalogService::class)->list())->toHaveCount($initialCount);
});

it('supports overriding delete with ConfirmDialog in ItemListDemo and delegating back to the service', function () {
    $screen = Screen::make(ItemListDemo::class);
    $screen->render(parent: null, shouldReset: true);

    /** @var ItemList $moviesList */
    $moviesList = $screen->getContainer()->findByName('movies_list');
    $initialItems = $moviesList->getItems();
    $initialCount = count($initialItems);
    $targetId = $initialItems[0]['id'];

    // Enable ConfirmDialog interception
    $screen->callAction('toggle_confirm_delete', ['checked' => true]);

    /** @var ItemList $moviesList */
    $moviesList = $screen->getContainer()->findByName('movies_list');
    expect($moviesList->get('on_delete')['action'] ?? null)->toBe('movie_deleted');

    // Triggering delete opens ConfirmDialog without deleting yet
    $screen->callAction('movie_deleted', ['id' => $targetId]);
    expect(app(SessionMovieCatalogService::class)->list())->toHaveCount($initialCount);

    // Confirming deletion delegates to service via performServiceDelete()
    $screen->callAction('confirm_delete_movies');
    expect(app(SessionMovieCatalogService::class)->list())->toHaveCount($initialCount - 1);
});
