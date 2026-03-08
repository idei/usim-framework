<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Role Definitions
    |--------------------------------------------------------------------------
    |
    | Centralized role metadata used by scaffolded auth/seed flows.
    | - default_screen: Screen class-string opened after login.
    | - label/description: Multi-language metadata.
    | - permissions: Default permissions for role provisioning.
    | - seed_user: Default seeded account for the role.
    |
    */
    'roles' => [
        'admin' => [
            'label' => [
                'es' => 'Administrador',
                'en' => 'Administrator',
            ],
            'description' => [
                'es' => 'Gestiona el sistema y usuarios.',
                'en' => 'Manages the system and users.',
            ],
            'default_screen' => 'App\\UI\\Screens\\Admin\\Dashboard',
            'permissions' => ['*'],
            'seed_user' => [
                'first_name' => env('ADMIN_FIRST_NAME', 'Admin'),
                'last_name' => env('ADMIN_LAST_NAME', 'User'),
                'email' => env('ADMIN_EMAIL', 'admin@example.com'),
                'password' => env('ADMIN_PASSWORD', 'password'),
            ],
        ],
        'user' => [
            'label' => [
                'es' => 'Usuario',
                'en' => 'User',
            ],
            'description' => [
                'es' => 'Usuario regular del sistema.',
                'en' => 'Regular system user.',
            ],
            'default_screen' => 'App\\UI\\Screens\\Home',
            'permissions' => [],
            'seed_user' => [
                'first_name' => env('USER_FIRST_NAME', 'Regular'),
                'last_name' => env('USER_LAST_NAME', 'User'),
                'email' => env('USER_EMAIL', 'user@example.com'),
                'password' => env('USER_PASSWORD', 'password'),
            ],
        ],
    ],

];
