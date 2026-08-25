<?php

use App\UI\Screens\Admin\UsersManager;
use App\UI\Screens\Admin\TranslateManager;
use App\UI\Screens\Home;

return [

    /*
    |--------------------------------------------------------------------------
    | Front Store Key
    |--------------------------------------------------------------------------
    | Una clave de almacenamiento del cliente en el header y payload. Esto es
    | importante para evitar colisiones en entornos con múltiples aplicaciones
    | o servicios que usan USIM.
    | Puede ser cualquier slug, pero se recomienda usar algo descriptivo.
    */
    'front_store_key' => env('FRONT_STORE_KEY', 'change-the-store-key'),

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
    | Application Name
    |--------------------------------------------------------------------------
    | Nombre de la aplicación que se mostrará en la interfaz de usuario.
    | Se puede definir en el archivo .env con la variable APP_NAME. Si no se define,
    | se usará "USIM Framework" como valor predeterminado.
    */
    'app_name' => env('APP_NAME', 'USIM Framework'),

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
            'default_translations' => [
                'en' => ['display_name' => 'Root', 'description' => 'User with total and unconditional access to all features.'],
                'es' => ['display_name' => 'Raíz', 'description' => 'Usuario con acceso total e incondicional a todas las funciones.'],
            ],
            'priority' => 100,
            'home_screen' => UsersManager::class,
            'permissions' => ['admin.users_manager.access', 'manage.roles'],
        ],
        'admin' => [
            'default_translations' => [
                'en' => ['display_name' => 'Administrator', 'description' => 'Manages the system and users.'],
                'es' => ['display_name' => 'Administrador', 'description' => 'Gestiona el sistema y los usuarios.'],
            ],
            'priority' => 80,
            'home_screen' => UsersManager::class,
            'permissions' => ['admin.users_manager.access'],
        ],
        'translator' => [
            'default_translations' => [
                'en' => ['display_name' => 'Translator', 'description' => 'User responsible for managing translations.'],
                'es' => ['display_name' => 'Traductor', 'description' => 'Usuario responsable de gestionar las traducciones.'],
            ],
            'priority' => 20,
            'home_screen' => TranslateManager::class,
            'permissions' => ['admin.translate_manager.access'],
        ],
    ],

    'permissions' => [
        'debug.logs' => [
            'default_translations' => [
                'en' => ['display_name' => 'View System Logs', 'description' => 'Allows viewing system activity and error logs.'],
                'es' => ['display_name' => 'Ver Registros del Sistema', 'description' => 'Permite ver los registros de actividad y errores del sistema.'],
            ],
        ],
        'manage.roles' => [
            'default_translations' => [
                'en' => ['display_name' => 'Manage Roles', 'description' => 'Allows creating, editing, and deleting roles and their permissions.'],
                'es' => ['display_name' => 'Gestionar Roles', 'description' => 'Permite crear, editar y eliminar roles y sus permisos.'],
            ],
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
            ['code' => 'es', 'name' => 'Spanish', 'native_name' => 'Español', 'active' => true],
            ['code' => 'it', 'name' => 'Italian', 'native_name' => 'Italiano', 'active' => false],
            ['code' => 'fr', 'name' => 'French', 'native_name' => 'Français', 'active' => false],
            ['code' => 'de', 'name' => 'German', 'native_name' => 'Deutsch', 'active' => false],
            ['code' => 'zh', 'name' => 'Chinese', 'native_name' => '中文', 'active' => false],
            ['code' => 'ja', 'name' => 'Japanese', 'native_name' => '日本語', 'active' => false],
            ['code' => 'pt', 'name' => 'Portuguese', 'native_name' => 'Português', 'active' => false],
        ],
        'i18n_key_prefixes' => [
            'role' => 'role.',
            'permission' => 'permission.',
            'screen' => 'screen.',
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
