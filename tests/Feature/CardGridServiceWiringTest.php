<?php

use App\Services\Movie\MovieCardGridService;
use App\UI\Screens\Demo\CardGridDemo;
use Idei\Usim\Components\CardGrid;
use Idei\Usim\Components\ItemCard;
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

    expect($service->calls)->toBe(0);

    $tree = $grid->toJson();
    $gridJson = $tree[$grid->getId()];
    $cards = $grid->getCards();

    expect($service->calls)->toBe(1)
        ->and($gridJson['query_action'])->toBe($grid->serviceActionFor('query'))
        ->and($cards)->toHaveCount(2)
        ->and($cards[0]->get('title'))->toBe('Alpha')
        ->and($cards[1]->get('title'))->toBe('Middle')
        ->and($tree[$cards[0]->getId()]['parent'])->toBe($grid->getId())
        ->and($gridJson['pagination']['total'])->toBe(3)
        ->and($gridJson['pagination']['total_pages'])->toBe(2)
        ->and($gridJson['pagination']['has_more'])->toBeTrue();
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
    expect($grid->getCards())->toHaveCount(2);

    $grid->handleServiceAction('query', ['page' => 2, 'per_page' => 2]);
    expect($grid->getCards())->toHaveCount(1)
        ->and($grid->getCards()[0]->get('title'))->toBe('Gladiator');

    $grid->handleServiceAction('query', ['page' => 1, 'search' => 'in']);
    expect($grid->getCards())->toHaveCount(2);
});

it('rejects services that do not implement CardListable or Listable', function () {
    expect(fn () => UI::cardGrid('invalid_grid')->service(new stdClass))
        ->toThrow(InvalidArgumentException::class);
});

it('handles CardGridDemo queries and single-card updates via native ComponentDiffer over POST /api/ui-event', function () {
    $screen = Screen::make(CardGridDemo::class);
    $screen->render(parent: null, shouldReset: true);

    /** @var CardGrid $moviesGrid */
    $moviesGrid = $screen->getContainer()->findByName('movies_grid');
    $gridId = $moviesGrid->getId();
    $queryAction = $moviesGrid->serviceActionFor('query');

    // Initial render mounts 6 child ItemCard components (rating desc: El Padrino, The Dark Knight, Inception, Forrest Gump, Interstellar, Matrix)
    $initialCards = $moviesGrid->getCards();
    expect($initialCards)->toHaveCount(6)
        ->and($initialCards[0])->toBeInstanceOf(ItemCard::class)
        ->and($initialCards[0]->get('title'))->toBe('El Padrino')
        ->and($moviesGrid->getPagination()['total'])->toBe(18)
        ->and($moviesGrid->getPagination()['total_pages'])->toBe(3);

    $inceptionCard = $moviesGrid->card(2);
    expect($inceptionCard)->toBeInstanceOf(ItemCard::class);
    assert($inceptionCard instanceof ItemCard);
    $inceptionCardId = $inceptionCard->getId();

    // 1. Toggle favorite on Inception (id=2): native ComponentDiffer emits ONLY the changed keys
    //    on Inception's ItemCard (ZERO diff on CardGrid, ZERO diff on the 5 sibling ItemCards!)
    expect(app(MovieCardGridService::class)->getFavoriteIds())->not->toContain(2);

    $favRes = $this->postJson('/api/ui-event', [
        'component_id' => $inceptionCardId,
        'event' => 'click',
        'action' => 'toggle_favorite',
        'parameters' => ['movie_id' => 2],
    ]);
    $favRes->assertOk();
    $favPayload = $favRes->json();

    expect(app(MovieCardGridService::class)->getFavoriteIds())->toContain(2)
        ->and($favPayload)->toHaveKey((string) $inceptionCardId)
        ->and($favPayload)->not->toHaveKey((string) $gridId)
        ->and($favPayload[(string) $inceptionCardId]['badge'])->toBe('★ Favorita')
        ->and($favPayload[(string) $inceptionCardId]['badge_style'])->toBe('warning')
        ->and($favPayload[(string) $inceptionCardId])->not->toHaveKey('title')
        ->and($favPayload[(string) $inceptionCardId])->not->toHaveKey('image');

    // Confirm none of the other 5 cards appeared in the diff payload
    foreach ($initialCards as $sibling) {
        if ($sibling->getId() !== $inceptionCardId) {
            expect($favPayload)->not->toHaveKey((string) $sibling->getId());
        }
    }

    // 2. Search "Nolan" + sort by year asc via POST /api/ui-event:
    //    Reuses Inception (id=2) and Interstellar (id=1) and The Dark Knight (id=11) with their exact IDs,
    //    detaches non-matching cards (parent: null), and adds Oppenheimer (id=17).
    $searchRes = $this->postJson('/api/ui-event', [
        'component_id' => $gridId,
        'event' => 'action',
        'action' => $queryAction,
        'parameters' => [
            'search' => 'Nolan',
            'page' => 1,
            'per_page' => 6,
            'sort_by' => 'year',
            'sort_direction' => 'asc',
        ],
    ]);
    $searchRes->assertOk();
    $searchPayload = $searchRes->json();

    $elPadrinoId = $initialCards[0]->getId();
    expect($searchPayload[(string) $gridId]['pagination']['total'])->toBe(4)
        ->and($searchPayload[(string) $elPadrinoId])->toBe(['parent' => null]);

    // 3. Integrated filters (genre + decade + featured + multi_select tags) -> only Interstellar (id=1)
    $filterRes = $this->postJson('/api/ui-event', [
        'component_id' => $gridId,
        'event' => 'action',
        'action' => $queryAction,
        'parameters' => [
            'search' => '',
            'page' => 1,
            'per_page' => 6,
            'filters' => [
                'genre' => 'sci-fi',
                'decade' => '2010',
                'featured' => true,
                'tags' => ['IMAX'],
            ],
        ],
    ]);
    $filterRes->assertOk();
    expect($filterRes->json((string) $gridId.'.pagination.total'))->toBe(1);

    // 4. Clear filters back to page 1, switch to load_more, then append page 2:
    //    Existing page-1 ItemCards must NOT be re-sent; ONLY the 6 new page-2 ItemCards are added!
    $resetRes = $this->postJson('/api/ui-event', [
        'component_id' => $gridId,
        'event' => 'action',
        'action' => $queryAction,
        'parameters' => [
            'search' => '',
            'page' => 1,
            'per_page' => 6,
            'sort_by' => 'title',
            'sort_direction' => 'asc',
            'filters' => [],
        ],
    ]);
    $resetRes->assertOk();

    $screen->callAction('pagination_mode_changed', ['value' => 'load_more']);

    $loadMoreRes = $this->postJson('/api/ui-event', [
        'component_id' => $gridId,
        'event' => 'action',
        'action' => $queryAction,
        'parameters' => [
            'page' => 2,
            'per_page' => 6,
            'sort_by' => 'title',
            'sort_direction' => 'asc',
            'filters' => [],
            'append' => true,
        ],
    ]);
    $loadMoreRes->assertOk();
    $loadMorePayload = $loadMoreRes->json();

    // Count newly added ItemCard nodes in the diff payload (should be exactly 6, zero deletes!)
    $addedCards = array_filter(
        $loadMorePayload,
        static fn (mixed $v): bool => is_array($v) && ($v['type'] ?? null) === 'item_card'
    );
    $deletedCards = array_filter(
        $loadMorePayload,
        static fn (mixed $v): bool => is_array($v) && array_key_exists('parent', $v) && $v['parent'] === null
    );

    expect($addedCards)->toHaveCount(6)
        ->and($deletedCards)->toHaveCount(0)
        ->and($loadMorePayload[(string) $gridId]['visible_count'])->toBe(12)
        ->and($loadMorePayload[(string) $gridId]['pagination']['page'])->toBe(2);

    // 5. Changing density & card_layout updates ALL 12 currently mounted ItemCards (both page 1 and dynamically added page 2)
    $selDensity = $screen->getContainer()->findByName('sel_density');
    $selLayout = $screen->getContainer()->findByName('sel_card_layout');

    $densityRes = $this->postJson('/api/ui-event', [
        'component_id' => $selDensity->getId(),
        'event' => 'change',
        'action' => 'density_changed',
        'parameters' => ['value' => 'compact'],
    ]);
    $densityRes->assertOk();
    $densityPayload = $densityRes->json();

    $updatedDensityCards = array_filter(
        $densityPayload,
        static fn (mixed $v): bool => is_array($v) && ($v['density'] ?? null) === 'compact'
    );
    // 12 mounted ItemCards + 1 CardGrid = 13 entries with density: compact
    expect($updatedDensityCards)->toHaveCount(13);

    $layoutRes = $this->postJson('/api/ui-event', [
        'component_id' => $selLayout->getId(),
        'event' => 'change',
        'action' => 'card_layout_changed',
        'parameters' => ['value' => 'horizontal'],
    ]);
    $layoutRes->assertOk();
    $layoutPayload = $layoutRes->json();

    $updatedLayoutCards = array_filter(
        $layoutPayload,
        static fn (mixed $v): bool => is_array($v) && ($v['layout'] ?? null) === 'horizontal'
    );
    expect($updatedLayoutCards)->toHaveCount(12)
        ->and($layoutPayload[(string) $gridId]['card_layout'])->toBe('horizontal');
});
