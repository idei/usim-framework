<?php

return [
    'menu_title' => 'Grilla de Tarjetas',
    'icon' => '🃏',
    'card_grid_demo_title' => '🃏 Catálogo de Películas — CardGrid Demo',
    'card_grid_demo_intro' => 'CardGrid responsive conectado a CardListable vía GET /api/ui-data, con buscador con debounce, filtros integrados, ordenamiento, paginado (páginas y cargar más) y acciones por tarjeta.',
    'pagination_mode' => [
        'label' => 'Modo de Paginado',
        'options' => [
            'pages' => 'Páginas numeradas (clásico)',
            'load_more' => 'Cargar más (incremental)',
        ],
    ],
    'card_layout' => [
        'label' => 'Orientación de Tarjeta',
        'options' => [
            'vertical' => 'Vertical (imagen arriba)',
            'horizontal' => 'Horizontal (imagen a la izquierda)',
        ],
    ],
    'density' => [
        'label' => 'Densidad',
        'options' => [
            'compact' => 'Compacta',
            'comfortable' => 'Cómoda (estándar)',
            'spacious' => 'Amplia',
        ],
    ],
    'movies_grid' => [
        'title' => 'Cartelera Destacada',
        'subtitle' => 'Haz clic en cualquier tarjeta para ver el resumen o utiliza las acciones del pie (y el menú ⋮ para acciones adicionales).',
        'search_placeholder' => 'Buscar por título, director, género o etiqueta...',
    ],
];
