<?php

return [
    'menu_title' => 'Lista de Ítems',
    'icon' => '📋',
    'item_list_demo_title' => '🎬 Catálogo de Películas — ItemList Demo',
    'item_list_demo_intro' => 'Explora búsqueda asíncrona vía GET /api/ui-data con estado de carga, selección simple/múltiple persistente entre búsquedas y operaciones CRUD inline.',
    'selection_mode' => [
        'label' => 'Modo de Selección',
        'options' => [
            'multiple' => 'Múltiple (Checkboxes + Selección masiva)',
            'single' => 'Simple (Radio visual)',
            'none' => 'Sin selección (Solo lectura / CRUD)',
        ],
    ],
    'confirm_delete' => [
        'label' => 'Confirmar antes de eliminar',
    ],
    'actions' => [
        'reset_catalog' => 'Restaurar catálogo',
        'confirm_marathon' => '🎬 Confirmar Maratón',
    ],
    'movies_list' => [
        'title' => 'Películas Disponibles',
        'label' => 'Selecciona películas para tu maratón o administra el catálogo',
        'help_text' => 'Usa el buscador para filtrar vía /api/ui-data. Las películas seleccionadas se conservan visibles aunque cambies la búsqueda.',
        'search_placeholder' => 'Buscar película por título, género, año o reparto...',
        'create_placeholder' => 'Ej: Dune: Parte Dos (2024) - Ciencia Ficción...',
        'create_button_label' => 'Agregar Película',
        'bulk_delete_label' => 'Eliminar seleccionadas',
    ],
];
