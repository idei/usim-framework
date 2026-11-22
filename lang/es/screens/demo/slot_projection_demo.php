<?php

return [
    'menu_title' => 'Proyección de Slots',
    'icon' => '🪟',
    'labels' => [
        'title' => 'Demo de Proyección Declarativa de Slots',
        'intro' => 'Esta única Screen proyecta filtros en left_panel (270px), un inspector bajo demanda en right_panel (340px), un indicador en status_bar y una acción en status_bar_right — todo desde slot-projection-demo.layout.yaml.',
        'active_summary' => 'Listo. Probá filtrar desde el panel izquierdo o abrir el inspector a la derecha.',
        'filters_title' => 'Filtros Contextuales (left_panel)',
        'inspector_title' => 'Inspector (right_panel)',
        'inspector_body' => 'Abrí el inspector para ver estado sincronizado desde la misma Screen.',
    ],
    'filters' => [
        'placeholder' => 'Palabra clave...',
    ],
    'inspector' => [
        'heading' => 'Inspeccionando: :subject',
        'body' => 'Estado vivo perteneciente a SlotProjectionDemo para ":subject". Cerrar este panel preserva su estado.',
        'note_placeholder' => 'Agregar nota rápida...',
        'note_saved' => 'Nota guardada: :note',
    ],
    'status' => [
        'initial' => 'SlotProjectionDemo activa (0 pings)',
        'pinged' => 'Acción de StatusBar ejecutada :count vez/veces',
        'note_saved' => 'Nota guardada: :note',
    ],
    'summary' => [
        'filtered' => 'Filtro activo: ":query"',
        'cleared' => 'Filtro limpiado — mostrando todos los elementos.',
    ],
    'actions' => [
        'toggle_left' => 'Alternar Panel Izquierdo',
        'open_inspector' => 'Abrir Inspector Derecho',
        'apply_filter' => 'Aplicar Filtro',
        'close_left' => 'Colapsar Panel Izquierdo',
        'save_note' => 'Guardar Nota',
        'status_ping' => 'Ping',
    ],
];
