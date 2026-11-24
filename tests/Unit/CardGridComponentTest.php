<?php

use Idei\Usim\Components\CardGrid;
use Idei\Usim\Components\ItemCard;
use Idei\Usim\DTOs\CardActionDTO;
use Idei\Usim\DTOs\CardItemDTO;
use Idei\Usim\DTOs\CardPageResult;
use Idei\Usim\DTOs\CardQuery;
use Idei\Usim\DTOs\ListItemDTO;
use Idei\Usim\Enums\Style;
use Idei\Usim\UI;

it('builds standalone ItemCard with typed semantic slots and fluent actions', function () {
    $card = UI::itemCard('welcome_card')
        ->title('Bienvenido a USIM')
        ->subtitle('Servidor primero')
        ->eyebrow('Sistema')
        ->description('Tu cuenta fue registrada correctamente.')
        ->icon('sparkles')
        ->badge('Activo', Style::SUCCESS)
        ->metric('100%', 'Listo', Style::PRIMARY)
        ->attributes([
            ['icon' => 'shield', 'label' => 'Rol', 'value' => 'Registrado'],
        ])
        ->tags(['Cuenta', 'USIM', 'Cuenta'])
        ->footerText('Hace un momento')
        ->addAction('Ir al perfil', 'go_to_profile', ['tab' => 'general'], 'user', Style::PRIMARY, 'solid')
        ->addAction('Ayuda', 'open_help', style: Style::DEFAULT, overflow: true)
        ->style(Style::INFO)
        ->featured(true)
        ->variant('outlined')
        ->density('compact')
        ->horizontal();

    $json = $card->toJson()[$card->getId()];

    expect($json['type'])->toBe('item_card')
        ->and($json['title'])->toBe('Bienvenido a USIM')
        ->and($json['eyebrow'])->toBe('Sistema')
        ->and($json['badge'])->toBe('Activo')
        ->and($json['badge_style'])->toBe('success')
        ->and($json['metric'])->toBe('100%')
        ->and($json['metric_style'])->toBe('primary')
        ->and($json['attributes'])->toHaveCount(1)
        ->and($json['tags'])->toBe(['Cuenta', 'USIM'])
        ->and($json['actions'])->toHaveCount(2)
        ->and($json['actions'][0]['variant'])->toBe('solid')
        ->and($json['actions'][1]['overflow'])->toBeTrue()
        ->and($json['style'])->toBe('info')
        ->and($json['featured'])->toBeTrue()
        ->and($json['variant'])->toBe('outlined')
        ->and($json['density'])->toBe('compact')
        ->and($json['layout'])->toBe('horizontal');
});

it('builds CardGrid with sensible defaults, fluent config, and static child ItemCards', function () {
    $grid = UI::cardGrid('catalog')
        ->title('Productos')
        ->subtitle('Catálogo completo')
        ->headerAction('Nuevo producto', 'create_product', ['scope' => 'catalog'], 'plus', Style::PRIMARY)
        ->paginationMode('load_more')
        ->perPage(2)
        ->perPageOptions([2, 4, 8])
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
        ->onCardClick('open_product', ['origin' => 'grid'])
        ->items([
            CardItemDTO::make(id: 'p1', title: 'Auriculares', style: Style::INFO, meta: ['in_stock' => true, 'price' => 199]),
            CardItemDTO::make(id: 'p2', title: 'Monitor', style: Style::SUCCESS, meta: ['in_stock' => true, 'price' => 499]),
            CardItemDTO::make(id: 'p3', title: 'Teclado', style: Style::DEFAULT, meta: ['in_stock' => false, 'price' => 99]),
        ]);

    $tree = $grid->toJson();
    $gridJson = $tree[$grid->getId()];

    expect($gridJson['type'])->toBe('card_grid')
        ->and($gridJson['title'])->toBe('Productos')
        ->and($gridJson['header_action']['action'])->toBe('create_product')
        ->and($gridJson['pagination']['mode'])->toBe('load_more')
        ->and($gridJson['pagination']['per_page'])->toBe(2)
        ->and($gridJson['pagination']['total'])->toBe(2)
        ->and($gridJson['searchable'])->toBeTrue()
        ->and($gridJson['sort_by'])->toBe('price')
        ->and($gridJson['sort_direction'])->toBe('desc')
        ->and($gridJson['filters'])->toHaveCount(4)
        ->and($gridJson['active_filters'])->toBe(['in_stock' => true])
        ->and($gridJson['max_columns'])->toBe(4)
        ->and($gridJson['card_layout'])->toBe('horizontal')
        ->and($grid->getCards())->toHaveCount(2);

    // Child ItemCards are serialized in the flat USIM tree with parent = CardGrid ID
    $firstCard = $grid->getCards()[0];
    expect($firstCard)->toBeInstanceOf(ItemCard::class)
        ->and($firstCard->get('title'))->toBe('Monitor')
        ->and($firstCard->get('layout'))->toBe('horizontal')
        ->and($firstCard->get('variant'))->toBe('outlined')
        ->and($tree[$firstCard->getId()]['parent'])->toBe($grid->getId())
        ->and($tree[$firstCard->getId()]['type'])->toBe('item_card')
        ->and($tree[$firstCard->getId()]['click_action'])->toBe('open_product');
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
        ->and($arr['badge_style'])->toBe('success')
        ->and($arr['tags'])->toBe(['Audio', 'Inalámbrico'])
        ->and($arr['actions'])->toHaveCount(2)
        ->and($arr['actions'][1]['overflow'])->toBeTrue();
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

    $pageResult = CardPageResult::fromCollection($listDtos, $query);
    $payload = $pageResult->toArray();

    expect($query->search)->toBe('test')
        ->and($query->sortDirection)->toBe('desc')
        ->and($query->filters)->toBe(['genre' => 'sci-fi'])
        ->and($payload['items'])->toHaveCount(1)
        ->and($payload['items'][0]['id'])->toBe(3)
        ->and($payload['pagination']['total'])->toBe(3)
        ->and($payload['pagination']['can_prev'])->toBeTrue();
});

it('auto-instantiates CardGrid and ItemCard from layout definitions via prefixes and aliases', function () {
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
                        ['icrd_summary' => ['title' => 'Resumen', 'style' => 'info']],
                        ['standalone_card' => ['component' => 'item_card', 'title' => 'Standalone']],
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
    $icrd = $container->findByName('icrd_summary');
    $standalone = $container->findByName('standalone_card');

    expect($prefixed)->toBeInstanceOf(CardGrid::class)
        ->and($aliased)->toBeInstanceOf(CardGrid::class)
        ->and($icrd)->toBeInstanceOf(ItemCard::class)
        ->and($standalone)->toBeInstanceOf(ItemCard::class);

    assert($prefixed instanceof CardGrid);
    assert($aliased instanceof CardGrid);
    assert($icrd instanceof ItemCard);
    assert($standalone instanceof ItemCard);

    expect($prefixed->get('variant'))->toBe('outlined')
        ->and($aliased->getPagination()['mode'])->toBe('load_more')
        ->and($aliased->get('max_columns'))->toBe(3)
        ->and($icrd->get('title'))->toBe('Resumen')
        ->and($standalone->get('title'))->toBe('Standalone');
});

it('mutates, removes, and prepends child ItemCards directly in the USIM component tree', function () {
    $grid = UI::cardGrid('tree_grid')->items([
        CardItemDTO::make(id: 1, title: 'Uno', badge: 'Borrador'),
        CardItemDTO::make(id: 2, title: 'Dos'),
    ]);

    $grid->patchCard(1, [
        'badge' => '★ Destacada',
        'badgeStyle' => Style::WARNING,
        'footerText' => null,
    ]);

    $card1 = $grid->card(1);
    expect($card1)->toBeInstanceOf(ItemCard::class);
    assert($card1 instanceof ItemCard);
    expect($card1->get('badge'))->toBe('★ Destacada')
        ->and($card1->get('badge_style'))->toBe('warning')
        ->and($card1->get('footer_text'))->toBeNull();

    $grid->removeCard(2);
    expect($grid->card(2))->toBeNull()
        ->and($grid->getCards())->toHaveCount(1)
        ->and($grid->getPagination()['total'])->toBe(1);

    $grid->prependCard(CardItemDTO::make(id: 3, title: 'Tres', style: Style::SUCCESS));
    $cards = $grid->getCards();
    expect($cards)->toHaveCount(2)
        ->and($cards[0]->getItemId())->toBe(3)
        ->and($cards[0]->getCardOrder())->toBe(0)
        ->and($cards[1]->getItemId())->toBe(1)
        ->and($cards[1]->getCardOrder())->toBe(1)
        ->and($grid->getPagination()['total'])->toBe(2);
});
