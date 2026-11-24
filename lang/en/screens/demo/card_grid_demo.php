<?php

return [
    'menu_title' => 'Card Grid Demo',
    'icon' => '🃏',
    'card_grid_demo_title' => '🃏 Movies Catalog — CardGrid Demo',
    'card_grid_demo_intro' => 'Responsive CardGrid managing first-class ItemCard components wired to CardListable via POST /api/ui-event, with native ComponentDiffer diffs, debounced search, integrated filters, sorting, and pagination (pages & load more).',
    'pagination_mode' => [
        'label' => 'Pagination Mode',
        'options' => [
            'pages' => 'Numbered pages (classic)',
            'load_more' => 'Load more (incremental)',
        ],
    ],
    'card_layout' => [
        'label' => 'Card Layout',
        'options' => [
            'vertical' => 'Vertical (image top)',
            'horizontal' => 'Horizontal (image left)',
        ],
    ],
    'density' => [
        'label' => 'Density',
        'options' => [
            'compact' => 'Compact',
            'comfortable' => 'Comfortable (default)',
            'spacious' => 'Spacious',
        ],
    ],
    'movies_grid' => [
        'title' => 'Featured Catalog',
        'subtitle' => 'Click any card to inspect details or use card footer actions (and the ⋮ overflow menu).',
        'search_placeholder' => 'Search by title, director, genre or tag...',
    ],
];
