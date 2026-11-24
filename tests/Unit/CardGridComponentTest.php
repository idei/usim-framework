<?php

use Idei\Usim\Components\CardGrid;
use Idei\Usim\DTOs\CardActionDTO;
use Idei\Usim\DTOs\CardItemDTO;
use Idei\Usim\DTOs\CardPageResult;
use Idei\Usim\DTOs\CardQuery;
use Idei\Usim\DTOs\ListItemDTO;
use Idei\Usim\Enums\Style;
use Idei\Usim\UI;

it('builds CardGrid with sensible defaults and fluent configuration', function () {
    $grid = UI::cardGrid('catalog')
        ->title('Productos')
        ->subtitle('Catálogo completo')
        ->headerAction('Nuevo producto', 'create_product', ['scope' => 'catalog'], 'plus', Style::PRIMARY)
        ->paginationMode('load_more')
        ->perPage(24)
        ->perPageOptions([12, 24, 48])
        ->searchable(true, 'Buscar producto...')
        ->debounce(400)
        ->minSearchChars(2)
        ->sortOptions([
            'price' => 'Precio',
            'name' => 'Nombre',
        ])
        ->defaultSort('price', 'desc')
        ->addSelectFilter('category', 'Categoría', ['tech' => 'Tecnología', 'home' => 'Hogar'])
        ->addMultiSelectFilter('tags', 'Etiquetas', ['sale' => 'Oferta', 'new' => 'Nuevo'])
        ->addToggleFilter('in_stock', 'Solo en stock', true)
        ->addChipsFilter('rating', 'Puntaje', ['4' => '4+ ★', '5' => '5 ★'])
        ->minCardWidth('260px')
        ->maxColumns(4)
        ->cardLayout('horizontal')
        ->variant('outlined')
        ->density('compact')
        ->showLayoutToggle(true)
        ->maxVisibleActions(2)
        ->maxVisibleTags(3)
        ->onCardClick('open_product', ['origin' => 'grid']);

    $json = $grid->toJson()[$grid->getId()];

    expect($json['type'])->toBe('card_grid')
        ->and($json['title'])->toBe('Productos')
        ->and($json['subtitle'])->toBe('Catálogo completo')
        ->and($json['header_action']['action'])->toBe('create_product')
        ->and($json['pagination']['mode'])->toBe('load_more')
        ->and($json['pagination']['per_page'])->toBe(24)
        ->and($json['pagination']['per_page_options'])->toBe([12, 24, 48])
        ->and($json['searchable'])->toBeTrue()
        ->and($json['search_placeholder'])->toBe('Buscar producto...')
        ->and($json['debounce'])->toBe(400)
        ->and($json['min_search_chars'])->toBe(2)
        ->and($json['sort_options'])->toHaveCount(2)
        ->and($json['sort_by'])->toBe('price')
        ->and($json['sort_direction'])->toBe('desc')
        ->and($json['filters'])->toHaveCount(4)
        ->and($json['active_filters'])->toBe(['in_stock' => true])
        ->and($json['min_card_width'])->toBe('260px')
        ->and($json['max_columns'])->toBe(4)
        ->and($json['card_layout'])->toBe('horizontal')
        ->and($json['variant'])->toBe('outlined')
        ->and($json['density'])->toBe('compact')
        ->and($json['show_layout_toggle'])->toBeTrue()
        ->and($json['on_card_click'])->toBe(['action' => 'open_product', 'parameters' => ['origin' => 'grid']]);
});

it('serializes CardItemDTO and CardActionDTO with typed semantic slots', function () {
    $card = CardItemDTO::make(
        id: 'prod-1',
        title: 'Auriculares Pro',
        subtitle: 'Bluetooth 5.3',
        eyebrow: 'Audio',
        description: 'Cancelación activa de ruido con 40h de batería.',
        image: '/images/headphones.png',
        badge: 'En stock',
        badgeStyle: Style::SUCCESS,
        metric: '$199',
        metricLabel: 'USD',
        metricStyle: Style::PRIMARY,
        attributes: [
            ['icon' => 'bolt', 'label' => 'Batería', 'value' => '40h'],
            ['label' => 'Peso', 'value' => '240g'],
        ],
        tags: ['Audio', 'Inalámbrico', 'Audio'],
        footerText: 'Envío gratis',
        actions: [
            CardActionDTO::make(
                label: 'Ver',
                action: 'inspect_item',
                parameters: ['sku' => 'HP-01'],
                icon: 'eye',
                style: Style::PRIMARY,
                variant: 'solid',
            ),
            CardActionDTO::make(
                label: 'Eliminar',
                action: 'delete_item',
                style: Style::DANGER,
                overflow: true,
            ),
        ],
        clickAction: 'inspect_item',
        style: Style::INFO,
        featured: true,
        accentColor: '#3498db',
        meta: ['stock' => 14],
    );

    $arr = $card->toArray();

    expect($arr['id'])->toBe('prod-1')
        ->and($arr['title'])->toBe('Auriculares Pro')
        ->and($arr['eyebrow'])->toBe('Audio')
        ->and($arr['badge_style'])->toBe('success')
        ->and($arr['metric'])->toBe('$199')
        ->and($arr['metric_style'])->toBe('primary')
        ->and($arr['attributes'])->toHaveCount(2)
        ->and($arr['tags'])->toBe(['Audio', 'Inalámbrico'])
        ->and($arr['actions'])->toHaveCount(2)
        ->and($arr['actions'][0]['variant'])->toBe('solid')
        ->and($arr['actions'][1]['overflow'])->toBeTrue()
        ->and($arr['style'])->toBe('info')
        ->and($arr['featured'])->toBeTrue()
        ->and($arr['accent_color'])->toBe('#3498db')
        ->and($arr['meta'])->toBe(['stock' => 14]);
});

it('converts ListItemDTO cleanly to CardItemDTO and slices collections via CardPageResult', function () {
    $listDtos = collect([
        ListItemDTO::make(id: 1, label: 'Alpha', subtitle: 'First', icon: '🚀', badge: 'New', badgeStyle: Style::PRIMARY),
        ListItemDTO::make(id: 2, label: 'Beta', subtitle: 'Second', style: Style::SUCCESS),
        ListItemDTO::make(id: 3, label: 'Gamma', subtitle: 'Third', disabled: true),
    ]);

    $query = CardQuery::fromArray([
        'page' => 2,
        'per_page' => 2,
        'search' => '  test  ',
        'sort_by' => 'title',
        'sort_direction' => 'DESC',
        'filters' => json_encode(['genre' => 'sci-fi', 'empty' => '']),
    ]);

    expect($query->search)->toBe('test')
        ->and($query->page)->toBe(2)
        ->and($query->perPage)->toBe(2)
        ->and($query->sortDirection)->toBe('desc')
        ->and($query->filters)->toBe(['genre' => 'sci-fi'])
        ->and($query->offset())->toBe(2);

    $pageResult = CardPageResult::fromCollection($listDtos, $query);
    $payload = $pageResult->toArray();

    expect($payload['items'])->toHaveCount(1)
        ->and($payload['items'][0]['id'])->toBe(3)
        ->and($payload['items'][0]['title'])->toBe('Gamma')
        ->and($payload['items'][0]['disabled'])->toBeTrue()
        ->and($payload['pagination']['total'])->toBe(3)
        ->and($payload['pagination']['total_pages'])->toBe(2)
        ->and($payload['pagination']['page'])->toBe(2)
        ->and($payload['pagination']['has_more'])->toBeFalse()
        ->and($payload['pagination']['can_prev'])->toBeTrue();
});

it('auto-instantiates CardGrid from layout definitions via grd_ prefix and card_grid alias', function () {
    $screen = new class extends \Idei\Usim\Screen
    {
        protected function startingUI(): \Idei\Usim\Components\Container
        {
            return UI::container('card_grid_layout_test')->layoutDefinition([
                'grid' => [
                    'cols' => 12,
                    'children' => [
                        ['grd_products' => ['per_page' => 12, 'variant' => 'outlined']],
                        ['custom_catalog' => ['component' => 'card_grid', 'pagination_mode' => 'load_more', 'max_columns' => 3]],
                    ],
                ],
            ]);
        }
    };

    $screen->render(parent: null, shouldReset: true);
    $container = $screen->getContainer();
    expect($container)->not->toBeNull();
    assert($container !== null);

    $prefixed = $container->findByName('grd_products');
    $aliased = $container->findByName('custom_catalog');

    expect($prefixed)->toBeInstanceOf(CardGrid::class)
        ->and($aliased)->toBeInstanceOf(CardGrid::class);

    assert($prefixed instanceof CardGrid);
    assert($aliased instanceof CardGrid);

    expect($prefixed->get('variant'))->toBe('outlined')
        ->and($aliased->getPagination()['mode'])->toBe('load_more')
        ->and($aliased->get('max_columns'))->toBe(3);
});

it('queues surgical card_patches (patchCard, removeCard, prependCard) with monotonic seq without mutating items', function () {
    $grid = UI::cardGrid('patchable')->items([
        CardItemDTO::make(id: 1, title: 'Uno', badge: 'Borrador'),
        CardItemDTO::make(id: 2, title: 'Dos'),
    ]);

    $grid
        ->patchCard(1, [
            'badge' => '★ Destacada',
            'badgeStyle' => Style::WARNING,
            'footerText' => null,
            'actions' => [
                CardActionDTO::make(label: 'Quitar', action: 'unstar', style: Style::WARNING),
            ],
        ])
        ->removeCard(2)
        ->prependCard(CardItemDTO::make(id: 3, title: 'Tres', style: Style::SUCCESS));

    $payload = $grid->toJson()[$grid->getId()];
    $patches = $payload['card_patches'];

    expect($patches['seq'])->toBe(1)
        ->and($patches['ops'])->toHaveCount(3)
        ->and($patches['ops'][0]['op'])->toBe('patch')
        ->and($patches['ops'][0]['id'])->toBe(1)
        ->and($patches['ops'][0]['data']['badge'])->toBe('★ Destacada')
        ->and($patches['ops'][0]['data']['badge_style'])->toBe('warning')
        ->and(array_key_exists('footer_text', $patches['ops'][0]['data']))->toBeTrue()
        ->and($patches['ops'][0]['data']['footer_text'])->toBeNull()
        ->and($patches['ops'][0]['data']['actions'][0]['label'])->toBe('Quitar')
        ->and($patches['ops'][1])->toBe(['op' => 'remove', 'id' => 2])
        ->and($patches['ops'][2]['op'])->toBe('prepend')
        ->and($patches['ops'][2]['data']['id'])->toBe(3);
});
