<?php

return [
    'menu_title' => 'Proyección de Slots',
    'icon' => '🪟',
    'labels' => [
        'title' => 'Demo de Proyección Declarativa de Slots',
        'intro' => 'Esta única Screen proyecta una cabecera sticky en top_bar, filtros en left_panel (270px), un inspector bajo demanda en right_panel (340px), una consola/prompt colapsable en bottom_panel (220px), un indicador en status_bar y accesos rápidos en status_bar_right — todo desde slot-projection-demo.layout.yaml.',
        'active_summary' => 'Listo. Probá filtrar a la izquierda, abrir el inspector a la derecha o desplegar la consola inferior.',
        'top_bar_title' => 'Demos / Proyección de Slots (top_bar sticky)',
        'filters_title' => 'Filtros Contextuales (left_panel)',
        'inspector_title' => 'Inspector (right_panel)',
        'inspector_body' => 'Abrí el inspector para ver estado sincronizado desde la misma Screen.',
        'console_title' => 'Consola / Prompt IA (bottom_panel)',
        'console_output' => 'Consola lista. Escribí un comando o prompt para probar la salida en bottom_panel.',
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
    'console' => [
        'placeholder' => 'Escribe un comando o instrucción de prompt...',
        'response' => '✓ Ejecutado en SlotProjectionDemo: :command',
        'executed' => 'Último comando en consola: :command',
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
        'toggle_bottom' => 'Alternar Consola Inferior',
        'toggle_top_bar' => 'Alternar Cabecera (top_bar)',
        'top_open_console' => '>_ Consola',
        'apply_filter' => 'Aplicar Filtro',
        'close_left' => 'Colapsar Panel Izquierdo',
        'save_note' => 'Guardar Nota',
        'run_command' => 'Ejecutar',
        'status_console' => '>_ Consola',
        'status_ping' => 'Ping',
    ],
];
