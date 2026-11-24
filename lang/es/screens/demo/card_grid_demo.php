<?php

return [
    'menu_title' => 'Grilla de Tarjetas',
    'icon' => '🃏',
    'card_grid_demo_title' => '🃏 Catálogo de Películas — CardGrid Demo',
    'card_grid_demo_intro' => 'CardGrid responsive que administra componentes ItemCard conectados a CardListable vía POST /api/ui-event, con diffing nativo de ComponentDiffer, buscador, filtros integrados, ordenamiento y paginado (páginas y cargar más).',
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
