<?php

use App\Services\Movie\MovieCardGridService;
use App\UI\Screens\Demo\CardGridDemo;
use Idei\Usim\Components\CardGrid;
use Idei\Usim\Contracts\CardGrid\CardListable;
use Idei\Usim\Contracts\ItemList\Listable;
use Idei\Usim\DTOs\CardItemDTO;
use Idei\Usim\DTOs\CardPageResult;
use Idei\Usim\DTOs\CardQuery;
use Idei\Usim\DTOs\ListItemDTO;
use Idei\Usim\Enums\Style;
use Idei\Usim\Screen;
use Idei\Usim\UI;
use Illuminate\Support\Collection;

it('binds a CardListable service and lazily honors perPage/sort set after service()', function () {
    $service = new class implements CardListable
    {
        public int $calls = 0;

        public function query(CardQuery $query): CardPageResult
        {
            $this->calls++;
            $all = [
                CardItemDTO::make(id: 1, title: 'Zebra', style: Style::INFO),
                CardItemDTO::make(id: 2, title: 'Alpha', style: Style::SUCCESS),
                CardItemDTO::make(id: 3, title: 'Middle', style: Style::WARNING),
            ];

            usort($all, static fn (CardItemDTO $a, CardItemDTO $b): int => $query->sortDirection === 'desc'
                ? strcmp($b->title, $a->title)
                : strcmp($a->title, $b->title));

            return CardPageResult::fromCollection($all, $query);
        }
    };

    $grid = UI::cardGrid('lazy_grid')
        ->service($service)
        ->perPage(2)
        ->defaultSort('title', 'asc');

    // Service has not been queried redundantly before serialization or getItems()
    expect($service->calls)->toBe(0);

    $json = $grid->toJson()[$grid->getId()];

    expect($service->calls)->toBe(1)
        ->and($json['action'])->toBe($grid->serviceActionFor('query'))
        ->and($json['items'])->toHaveCount(2)
        ->and($json['items'][0]['title'])->toBe('Alpha')
        ->and($json['items'][1]['title'])->toBe('Middle')
        ->and($json['pagination']['total'])->toBe(3)
        ->and($json['pagination']['total_pages'])->toBe(2)
        ->and($json['pagination']['has_more'])->toBeTrue();
});

it('adapts a plain Listable service seamlessly for CardGrid pagination', function () {
    $listable = new class implements Listable
    {
        public function list(string $search = '', array $context = []): Collection
        {
            $items = collect([
                ListItemDTO::make(id: 1, label: 'Interstellar', subtitle: 'Sci-Fi'),
                ListItemDTO::make(id: 2, label: 'Inception', subtitle: 'Sci-Fi'),
                ListItemDTO::make(id: 3, label: 'Gladiator', subtitle: 'Action'),
            ]);

            if ($search !== '') {
                return $items->filter(
                    static fn (ListItemDTO $item): bool => str_contains(strtolower($item->label), strtolower($search))
                )->values();
            }

            return $items;
        }
    };

    $grid = UI::cardGrid('listable_adapted')->service($listable)->perPage(2);

    $page1 = $grid->queryServicePage(['page' => 1, 'per_page' => 2]);
    $page2 = $grid->queryServicePage(['page' => 2, 'per_page' => 2]);
    $filtered = $grid->queryServicePage(['search' => 'in']);

    expect($page1['items'])->toHaveCount(2)
        ->and($page1['pagination']['has_more'])->toBeTrue()
        ->and($page2['items'])->toHaveCount(1)
        ->and($page2['items'][0]['title'])->toBe('Gladiator')
        ->and($filtered['items'])->toHaveCount(2);
});

it('rejects services that do not implement CardListable or Listable', function () {
    expect(fn () => UI::cardGrid('invalid_grid')->service(new stdClass))
        ->toThrow(InvalidArgumentException::class);
});

it('serves CardGridDemo queries (search, filters, sort, pagination) via GET /api/ui-data and events via POST /api/ui-event', function () {
    $screen = Screen::make(CardGridDemo::class);
    $screen->render(parent: null, shouldReset: true);

    /** @var CardGrid $moviesGrid */
    $moviesGrid = $screen->getContainer()->findByName('movies_grid');
    $componentId = $moviesGrid->getId();
    $queryAction = $moviesGrid->serviceActionFor('query');

    // Initial render obeys YAML per_page: 6 and defaultSort rating desc
    expect($moviesGrid->getItems())->toHaveCount(6)
        ->and($moviesGrid->getItems()[0]['title'])->toBe('El Padrino')
        ->and($moviesGrid->getPagination()['total'])->toBe(18)
        ->and($moviesGrid->getPagination()['total_pages'])->toBe(3);

    // 1. Search via GET /api/ui-data
    $searchRes = $this->getJson('/api/ui-data?component_id='.$componentId.'&action='.urlencode($queryAction).'&parameters='.urlencode((string) json_encode([
        'search' => 'Nolan',
        'page' => 1,
        'per_page' => 6,
        'sort_by' => 'year',
        'sort_direction' => 'asc',
    ])));
    $searchRes->assertOk();
    $searchData = $searchRes->json();
    expect($searchData['pagination']['total'])->toBe(4)
        ->and($searchData['items'][0]['title'])->toBe('The Dark Knight')
        ->and($searchData['items'][3]['title'])->toBe('Oppenheimer');

    // 2. Integrated filters (genre + decade + featured toggle + multi_select tags)
    $filterRes = $this->getJson('/api/ui-data?component_id='.$componentId.'&action='.urlencode($queryAction).'&parameters='.urlencode((string) json_encode([
        'page' => 1,
        'per_page' => 6,
        'filters' => [
            'genre' => 'sci-fi',
            'decade' => '2010',
            'featured' => true,
            'tags' => ['IMAX'],
        ],
    ])));
    $filterRes->assertOk();
    $filterData = $filterRes->json();
    expect($filterData['pagination']['total'])->toBe(1)
        ->and($filterData['items'][0]['title'])->toBe('Interstellar');

    // 3. Page 2 pagination
    $page2Res = $this->getJson('/api/ui-data?component_id='.$componentId.'&action='.urlencode($queryAction).'&parameters='.urlencode((string) json_encode([
        'page' => 2,
        'per_page' => 6,
        'sort_by' => 'title',
        'sort_direction' => 'asc',
    ])));
    $page2Res->assertOk();
    $page2Data = $page2Res->json();
    expect($page2Data['items'])->toHaveCount(6)
        ->and($page2Data['pagination']['page'])->toBe(2)
        ->and($page2Data['pagination']['can_prev'])->toBeTrue()
        ->and($page2Data['pagination']['can_next'])->toBeTrue();

    // 4. Card action event (toggle_favorite) via POST /api/ui-event emits ONLY a surgical card_patches diff (no items, no reload_token)
    $initialFavs = app(MovieCardGridService::class)->getFavoriteIds();
    expect($initialFavs)->not->toContain(2);

    $favRes = $this->postJson('/api/ui-event', [
        'component_id' => $componentId,
        'event' => 'click',
        'action' => 'toggle_favorite',
        'parameters' => ['movie_id' => 2],
    ]);
    $favRes->assertOk();
    expect(app(MovieCardGridService::class)->getFavoriteIds())->toContain(2);

    $favDiff = $favRes->json((string) $componentId);
    expect($favDiff)->toBeArray()
        ->and($favDiff)->toHaveKey('card_patches')
        ->and($favDiff)->not->toHaveKey('items')
        ->and($favDiff)->not->toHaveKey('reload_token')
        ->and($favDiff['card_patches']['seq'])->toBe(1)
        ->and($favDiff['card_patches']['ops'])->toHaveCount(1)
        ->and($favDiff['card_patches']['ops'][0]['op'])->toBe('patch')
        ->and($favDiff['card_patches']['ops'][0]['id'])->toBe(2)
        ->and($favDiff['card_patches']['ops'][0]['data']['badge'])->toBe('★ Favorita')
        ->and($favDiff['card_patches']['ops'][0]['data']['badge_style'])->toBe('warning');

    // A second toggle increments seq cleanly and sends only the new op (never replaying turn 1)
    $unfavRes = $this->postJson('/api/ui-event', [
        'component_id' => $componentId,
        'event' => 'click',
        'action' => 'toggle_favorite',
        'parameters' => ['movie_id' => 2],
    ]);
    $unfavRes->assertOk();
    $unfavDiff = $unfavRes->json((string) $componentId);
    expect($unfavDiff['card_patches']['seq'])->toBe(2)
        ->and($unfavDiff['card_patches']['ops'])->toHaveCount(1)
        ->and($unfavDiff['card_patches']['ops'][0]['data']['badge'])->toBe('Destacada');

    // 5. Switching pagination mode to load_more via Screen action
    $screen->callAction('pagination_mode_changed', ['value' => 'load_more']);
    /** @var CardGrid $updatedGrid */
    $updatedGrid = $screen->getContainer()->findByName('movies_grid');
    expect($updatedGrid->getPagination()['mode'])->toBe('load_more');
});
