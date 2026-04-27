<?php

return [
    'menu_label' => 'Demo Split',
    'title' => 'Demo de Contenedor Split',
    'intro' => 'Configura orientacion, tamaño inicial y comportamiento de colapso. La barra del split es draggable.',
    'orientation' => [
        'label' => 'Orientacion',
        'options' => [
            'horizontal' => 'Horizontal',
            'vertical' => 'Vertical',
        ],
    ],
    'split_size' => [
        'label' => 'Ancho/alto del primer panel (ej: 35% o 360px)',
        'placeholder' => '45%',
    ],
    'splitter_size' => [
        'label' => 'Ancho de barra split (ej: 8px)',
        'placeholder' => '8px',
    ],
    'draggable' => 'Draggable',
    'collapsible' => 'Colapsable',
    'collapse_target' => [
        'label' => 'Panel colapsable',
        'options' => [
            'first' => 'Primer panel',
            'second' => 'Segundo panel',
        ],
    ],
    'actions' => [
        'apply_sizes' => 'Aplicar medidas',
        'toggle_collapse' => 'Toggle colapso',
        'reset_demo' => 'Reset demo',
        'collapse_first' => 'Colapsar panel A',
        'collapse_second' => 'Colapsar panel B',
    ],
    'panes' => [
        'first' => [
            'title' => 'Panel A',
            'description' => 'Este panel representa contenido principal.',
        ],
        'second' => [
            'title' => 'Panel B',
            'description' => 'Usa la barra central para redimensionar en vivo.',
        ],
    ],
];
