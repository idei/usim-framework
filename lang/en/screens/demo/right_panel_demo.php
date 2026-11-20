<?php

return [
    'menu_title' => 'Right Panel',
    'icon' => '🧩',
    'title' => 'Workbench Right Panel & StatusBar Demo',
    'intro' => 'Click Inspect on any resource below to expand the right_panel slot from 0px without opening a blocking modal. You can resize it with the Split divider, send live messages to the StatusBar, or close it back to 0px.',
    'initial_summary' => 'Right panel is currently collapsed (0px). Select a resource to inspect.',
    'inspecting_summary' => 'Inspecting in right panel: :title',
    'status_inspecting' => 'Inspecting :title',
    'status_ping_message' => 'Workbench StatusBar updated at :time',
    'status_sent_toast' => 'StatusBar message dispatched via UsimEvent.',
    'no_right_panel_warning' => 'The active layout does not define a right_panel slot. Sign in to use WorkbenchLayout.',
    'actions' => [
        'inspect' => 'Inspect →',
        'close_panel' => 'Close Right Panel',
        'send_status' => 'Update StatusBar',
        'clear_status' => 'Clear StatusBar',
    ],
    'inspector' => [
        'note_label' => 'Quick status note',
        'note_placeholder' => 'e.g. Verified healthy',
        'publish_status' => 'Send to StatusBar',
    ],
];
