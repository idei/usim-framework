<?php

return [
    'menu_title' => 'Item List Demo',
    'icon' => '📋',
    'item_list_demo_title' => '🎬 Movies Catalog — ItemList Demo',
    'item_list_demo_intro' => 'Explore async search via GET /api/ui-data with loading indicator, persistent single/multiple selection across searches, and inline CRUD actions.',
    'selection_mode' => [
        'label' => 'Selection Mode',
        'options' => [
            'multiple' => 'Multiple (Checkboxes + Bulk selection)',
            'single' => 'Single (Radio visual)',
            'none' => 'No selection (Read-only / CRUD)',
        ],
    ],
    'confirm_delete' => [
        'label' => 'Confirm before deleting',
    ],
    'actions' => [
        'reset_catalog' => 'Reset catalog',
        'confirm_marathon' => '🎬 Confirm Marathon',
    ],
    'movies_list' => [
        'title' => 'Available Movies',
        'label' => 'Select movies for your marathon or manage the catalog',
        'help_text' => 'Use the search bar to filter via /api/ui-data. Selected movies remain preserved even when changing search filters.',
        'search_placeholder' => 'Search movie by title, genre, year or cast...',
        'create_placeholder' => 'e.g. Dune: Part Two (2024) - Sci-Fi...',
        'create_button_label' => 'Add Movie',
        'bulk_delete_label' => 'Delete selected',
    ],
];
