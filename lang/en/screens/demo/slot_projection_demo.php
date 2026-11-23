<?php

return [
    'menu_title' => 'Slot Projections',
    'icon' => '🪟',
    'labels' => [
        'title' => 'Declarative Slot Projections Demo',
        'intro' => 'This single Screen projects a sticky header into top_bar, filters into left_panel (270px), an on-demand inspector into right_panel (340px), a collapsible console/prompt panel into bottom_panel (220px), a live status label into status_bar, and quick actions into status_bar_right — all from slot-projection-demo.layout.yaml.',
        'active_summary' => 'Ready. Try filtering on the left, opening the right inspector, or toggling the bottom console.',
        'top_bar_title' => 'Demos / Slot Projections (sticky top_bar)',
        'filters_title' => 'Contextual Filters (left_panel)',
        'inspector_title' => 'Inspector (right_panel)',
        'inspector_body' => 'Select or open the inspector to see synchronized state from the same Screen.',
        'console_title' => 'Console / AI Prompt (bottom_panel)',
        'console_output' => 'Console ready. Type a command or prompt to test bottom_panel output.',
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
    'console' => [
        'placeholder' => 'Type a command or AI prompt instruction...',
        'response' => '✓ Executed on SlotProjectionDemo: :command',
        'executed' => 'Last console command: :command',
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
        'toggle_bottom' => 'Toggle Bottom Console',
        'toggle_top_bar' => 'Toggle Top Bar',
        'top_open_console' => '>_ Console',
        'apply_filter' => 'Apply Filter',
        'close_left' => 'Collapse Left Panel',
        'save_note' => 'Save Note',
        'run_command' => 'Run',
        'status_console' => '>_ Console',
        'status_ping' => 'Ping',
    ],
];
