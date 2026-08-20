<?php

// @usim: feature="core", type="lang"

return [
    'actions' => [
        'open_confirmation' => 'Open Confirmation Dialog',
        'open_error' => 'Open Error Dialog',
        'open_timeout_with_button' => 'Open Timeout Dialog (10 sec)',
        'open_timeout_without_button' => 'Open Timeout Without Button',
        'settings' => 'Settings',
    ],
    'auto_close_dialog' => [
        'message' => 'This dialog will close automatically in:',
        'title' => 'Auto close',
    ],
    'confirm_dialog' => [
        'cancel_label' => 'No, Cancel',
        'confirm_label' => 'Yes, Proceed',
        'message' => 'Are you sure you want to proceed with this action?',
        'title' => 'Confirm Action',
    ],
    'error_dialog' => [
        'message' => 'Could not connect to the server.
Please verify your internet connection and try again.',
        'title' => 'Connection Error',
    ],
    'instruction' => 'Click the button below to open a confirmation dialog:',
    'result' => [
        'cancelled' => 'Action cancelled by user',
        'confirmed' => 'Action confirmed! Type: :type',
    ],
    'settings_dialog' => [
        'message' => 'Do you want to reset settings?
This action cannot be undone.',
        'title' => 'Settings',
    ],
    'success_dialog' => [
        'message' => 'Settings were reset successfully.',
        'title' => 'Done!',
    ],
    'timeout_dialog' => [
        'message' => 'This message will auto-close in:',
        'title' => 'Temporary Notification',
    ],
    'title' => 'Modal Component Demo',
];
