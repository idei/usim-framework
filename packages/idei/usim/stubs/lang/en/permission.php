<?php

// @usim: feature="core", type="lang"

return [
    'debug' => [
        'logs' => [
            'name' => 'View System Logs',
            'description' => 'Allows viewing system activity and error logs.',
        ],
        'access' => [
            'name' => 'Access Debug Tools',
            'description' => 'Allows access to debugging and diagnostic tools.',
        ],
    ],
    'manage' => [
        'users' => [
            'name' => 'Manage Users',
            'description' => 'Allows creating, editing, and deleting users.',
        ],
        'roles' => [
            'name' => 'Manage Roles',
            'description' => 'Allows creating, editing, and deleting roles and their permissions.',
        ],
    ],
    'auth' => [
        'profile' => [
            'access' => [
                'name' => 'Access Auth Profile',
                'description' => 'Permission to access Auth Profile.',
            ],
        ],
    ],
    'admin' => [
        'translate_manager' => [
            'access' => [
                'name' => 'Access Admin Translate Manager',
                'description' => 'Permission to access Admin Translate Manager.',
            ],
        ],
        'users_manager' => [
            'access' => [
                'name' => 'Access Admin Users Manager',
                'description' => 'Permission to access Admin Users Manager.',
            ],
        ],
        'admin_device_pairing' => [
            'access' => [
                'name' => 'Access Admin Admin Device Pairing',
                'description' => 'Permission to access Admin Admin Device Pairing.',
            ],
        ],
    ],
    'home' => [
        'access' => [
            'name' => 'Access Home',
            'description' => 'Permission to access Home.',
        ],
    ],
    'write' => [
        'name' => 'Write Access',
        'description' => 'Allows creating and modifying content.',
    ],
    'approve' => [
        'publications' => [
            'name' => 'Approve Publications',
            'description' => 'Allows approving or rejecting publications submitted by users.',
        ],
    ],
    'registered' => [
        'access' => [
            'name' => 'Access Registered',
            'description' => 'Permission to access Registered.',
        ],
    ],
    'device' => [
        'kiosk' => [
            'access' => [
                'name' => 'Access Device Kiosk',
                'description' => 'Permission to access Device Kiosk.',
            ],
        ],
    ],
];
