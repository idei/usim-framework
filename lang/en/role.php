<?php

return [
    'root' => [
        'name' => 'Root',
        'description' => 'User with total and unconditional access to all features.',
    ],
    'admin' => [
        'name' => 'Administrator',
        'description' => 'Manages the system and users.',
    ],
    'registered' => [
        'name' => 'Registered',
        'description' => 'Registered user with limited permissions.',
    ],
    'approved' => [
        'name' => 'Approved',
        'description' => 'Approved user with access to additional features.',
    ],
    'translator' => [
        'name' => 'Translator',
        'description' => 'User responsible for managing translations.',
    ],
    'developer' => [
        'name' => 'Developer',
        'description' => 'User with access to development and debugging tools.',
    ],
];
