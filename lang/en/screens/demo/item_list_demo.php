<?php

return [
    'menu_title' => 'Item List Demo',
    'icon' => '📋',
    'item_list_demo_title' => '🎬 Movies Catalog — ItemList Demo',
    'item_list_demo_intro' => 'ItemList wired to a contract-based service (Listable, Creatable, Updatable, Deletable, BulkDeletable) backed by session, with GET /api/ui-data search, persistent selection, and inline CRUD.',
    'selection_mode' => [
        'label' => 'Selection Mode',
        'options' => [
            'multiple' => 'Multiple (Checkboxes + Bulk selection)',
            'single' => 'Single (Click selection)',
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
