<?php

return [
    'menu_title' => 'Slot Projections',
    'icon' => '🪟',
    'labels' => [
        'title' => 'Declarative Slot Projections Demo',
        'intro' => 'This single Screen projects filters into left_panel (270px), an on-demand inspector into right_panel (340px), a live status label into status_bar, and an action into status_bar_right — all from slot-projection-demo.layout.yaml.',
        'active_summary' => 'Ready. Try filtering from the left panel or opening the inspector on the right.',
        'filters_title' => 'Contextual Filters (left_panel)',
        'inspector_title' => 'Inspector (right_panel)',
        'inspector_body' => 'Select or open the inspector to see synchronized state from the same Screen.',
    ],
    'filters' => [
        'placeholder' => 'Filter keyword...',
    ],
    'inspector' => [
        'heading' => 'Inspecting: :subject',
        'body' => 'Live state owned by SlotProjectionDemo for ":subject". Closing this panel preserves its state.',
        'note_placeholder' => 'Add quick note...',
        'note_saved' => 'Saved note: :note',
    ],
    'status' => [
        'initial' => 'SlotProjectionDemo active (0 pings)',
        'pinged' => 'StatusBar action clicked :count time(s)',
        'note_saved' => 'Note saved: :note',
    ],
    'summary' => [
        'filtered' => 'Active filter: ":query"',
        'cleared' => 'Filter cleared — showing all items.',
    ],
    'actions' => [
        'toggle_left' => 'Toggle Left Panel',
        'open_inspector' => 'Open Right Inspector',
        'apply_filter' => 'Apply Filter',
        'close_left' => 'Collapse Left Panel',
        'save_note' => 'Save Note',
        'status_ping' => 'Ping',
    ],
];
