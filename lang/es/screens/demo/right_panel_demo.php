<?php

return [
    'menu_title' => 'Panel Derecho',
    'icon' => '🧩',
    'title' => 'Demo de Panel Derecho y StatusBar (Workbench)',
    'intro' => 'Hacé clic en Inspeccionar en cualquiera de los recursos para expandir automáticamente el slot right_panel desde 0px sin abrir un modal bloqueante. Podés redimensionarlo con el Split, enviar mensajes a la StatusBar o cerrarlo de vuelta a 0px.',
    'initial_summary' => 'El panel derecho está colapsado (0px). Seleccioná un recurso para inspeccionarlo.',
    'inspecting_summary' => 'Inspeccionando en panel derecho: :title',
    'status_inspecting' => 'Inspeccionando :title',
    'status_ping_message' => 'StatusBar actualizada a las :time',
    'status_sent_toast' => 'Mensaje enviado a la StatusBar vía UsimEvent.',
    'no_right_panel_warning' => 'El layout activo no define un slot right_panel. Iniciá sesión para usar WorkbenchLayout.',
    'actions' => [
        'inspect' => 'Inspeccionar →',
        'close_panel' => 'Cerrar Panel Derecho',
        'send_status' => 'Actualizar StatusBar',
        'clear_status' => 'Limpiar StatusBar',
    ],
    'inspector' => [
        'note_label' => 'Nota rápida de estado',
        'note_placeholder' => 'Ej. Verificado OK',
        'publish_status' => 'Enviar a StatusBar',
    ],
];
