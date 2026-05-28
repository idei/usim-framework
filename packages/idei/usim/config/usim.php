<?php

use App\UI\Screens\Admin\UsersManager;
use App\UI\Screens\Admin\TranslateManager;
use App\UI\Screens\Home;

return [

    /*
    |--------------------------------------------------------------------------
    | Application ID
    |--------------------------------------------------------------------------
    | Un identificador único para esta aplicación, utilizado para diferenciarla
    | en entornos con múltiples aplicaciones o servicios. Puede ser cualquier slug,
    | pero se recomienda usar algo descriptivo.
    */
    'app_id' => env('APP_ID', 'my-app'),

    /*
    |--------------------------------------------------------------------------
    | UI Screens Namespace
    |--------------------------------------------------------------------------
    |
    | Namespace base donde se buscan los servicios de pantallas (screens).
    */
    'screens_namespace' => 'App\\UI\\Screens',

    /*
    |--------------------------------------------------------------------------
    | UI Screens Path
    |--------------------------------------------------------------------------
    |
    | Ruta absoluta donde se encuentran los archivos de las pantallas.
    | Por defecto: app_path('UI/Screens')
    */
    'screens_path' => app_path('UI/Screens'),

    /*
    |--------------------------------------------------------------------------
    | API Base URL
    |--------------------------------------------------------------------------
    |
    | La URL base para las peticiones HTTP internas hacia la API.
    | Si no se define, utilizará la URL principal de la aplicación (APP_URL).
    | Útil cuando la API está en un servidor o contenedor diferente.
    */
    'api_url' => env('API_BASE_URL', env('APP_URL')),

    /*
    |--------------------------------------------------------------------------
    | Upload Disk
    |--------------------------------------------------------------------------
    |
    | Disco de filesystem usado para almacenar los archivos subidos.
    | Por defecto 'local' (disponible en cualquier app Laravel sin config extra).
    | Publica config/usim.php y cámbialo si necesitas un disco dedicado.
    */
    'upload_disk' => env('UPLOAD_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Default Users Configuration
    |--------------------------------------------------------------------------
    |
    | Centralized role metadata used by scaffolded auth/install flows.
    |
    */
    'users' => [
        'root' => [
            'first_name' => env('ROOT_FIRST_NAME', 'Root'),
            'last_name' => env('ROOT_LAST_NAME', 'User'),
            'email' => env('ROOT_EMAIL', 'root@example.com'),
            'password' => env('ROOT_PASSWORD', 'CHANGE_ME'),
            'roles' => ['root'],
        ],
    ],

    'roles' => [
        'root' => [
            'display_name' => 'Root',
            'description' => 'User with total and unconditional access to all features.',
            'priority' => 100,
            'default_screen' => UsersManager::class,
            'permissions' => ['*'],
        ],
        'admin' => [
            'display_name' => 'Administrator',
            'description' => 'Manages the system and users.',
            'priority' => 80,
            'default_screen' => UsersManager::class,
            'permissions' => ['screen.admin.dashboard.access'],
        ],
        'registered' => [
            'display_name' => 'Registered',
            'description' => 'Registered user with limited permissions.',
            'priority' => 60,
            'default_screen' => Home::class,
            'permissions' => ['screen.user.home.access'],
        ],
        'approved' => [
            'display_name' => 'Approved',
            'description' => 'Approved user with access to additional features.',
            'priority' => 40,
            'default_screen' => Home::class,
            'permissions' => ['screen.user.home.access', 'feature.premium.access'],
        ],
        'translator' => [
            'display_name' => 'Translator',
            'description' => 'User responsible for managing translations.',
            'priority' => 20,
            'default_screen' => TranslateManager::class,
            'permissions' => [],
        ],
    ],

    'permissions' => [
        '*' => [
            'display_name' => 'Full Access',
            'description' => 'Allows access to all features and screens.',
        ],
        'view.logs' => [
            'display_name' => 'View System Logs',
            'description' => 'Allows viewing system activity and error logs.',
        ],
        'manage.users' => [
            'display_name' => 'Manage Users',
            'description' => 'Allows creating, editing, and deleting users.',
        ],
        'manage.roles' => [
            'display_name' => 'Manage Roles',
            'description' => 'Allows creating, editing, and deleting roles and their permissions.',
        ],
        'debug.access' => [
            'display_name' => 'Access Debug Tools',
            'description' => 'Allows access to debugging and diagnostic tools.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Internationalization (i18n)
    |--------------------------------------------------------------------------
    |
    | Configuracion base para traducciones en base de datos del paquete USIM.
    |
    */
    'i18n' => [
        'default_locale' => env('USIM_DEFAULT_LOCALE', env('APP_LOCALE', 'en')),
        'fallback_locale' => env('USIM_FALLBACK_LOCALE', 'en'),
        'auto_key_max_length' => (int) env('USIM_I18N_AUTO_KEY_MAX_LENGTH', 30),
        'log_channel' => env('USIM_I18N_LOG_CHANNEL', 'i18n'),
        'log_autokey_suggestions' => env('USIM_I18N_LOG_AUTOKEY_SUGGESTIONS', true),
        'languages' => [
            ['code' => 'en', 'name' => 'English', 'native_name' => 'English', 'active' => true],
            ['code' => 'es', 'name' => 'Spanish', 'native_name' => 'Espanol', 'active' => true],
            ['code' => 'it', 'name' => 'Italian', 'native_name' => 'Italiano', 'active' => true],
            ['code' => 'fr', 'name' => 'French', 'native_name' => 'Français', 'active' => false],
            ['code' => 'de', 'name' => 'German', 'native_name' => 'Deutsch', 'active' => false],
            ['code' => 'zh', 'name' => 'Chinese', 'native_name' => '中文', 'active' => false],
            ['code' => 'ja', 'name' => 'Japanese', 'native_name' => '日本語', 'active' => false],
            ['code' => 'pt', 'name' => 'Portuguese', 'native_name' => 'Português', 'active' => false],
        ],
    ],

    /*
     |--------------------------------------------------------------------------
     | Headless Mode
     |--------------------------------------------------------------------------
     | Cuando está activado (true), USIM no sirve vistas HTML desde el catch-all
     | web. Todos los clientes deben consumir /api/ui directamente.
     | Útil para aplicaciones backend-driven que no necesitan renderer web.
     |
     | Por defecto: false (backward compatible)
     */
    'headless_mode' => env('USIM_HEADLESS_MODE', false),
];
