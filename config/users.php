<?php

use App\UI\Screens\Admin\Dashboard;
use App\UI\Screens\Home;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Users Configuration
    |--------------------------------------------------------------------------
    |
    | These values are used to create default users when seeding the database.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Role Definitions
    |--------------------------------------------------------------------------
    |
    | Centralized role metadata with room for future attributes.
    | - default_screen: Screen class that should open right after login.
    | - label/description: Multi-language text.
    | - permissions: Optional default permissions for provisioning flows.
    | - seed_user: Default seeded account data for this role.
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
            'default_screen' => Dashboard::class,
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
            'default_screen' => Home::class,
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
