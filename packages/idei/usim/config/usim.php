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
            'default_translations' => [
                'en' => ['display_name' => 'Root', 'description' => 'User with total and unconditional access to all features.'],
                'es' => ['display_name' => 'Root', 'description' => 'Usuario con acceso total e incondicional a todas las funciones.'],
                'it' => ['display_name' => 'Root', 'description' => 'Utente con accesso totale e incondizionato a tutte le funzionalità.'],
            ],
            'priority' => 100,
            'home_screen' => UsersManager::class,
            'permissions' => ['*'],
        ],
        'admin' => [
            'default_translations' => [
                'en' => ['display_name' => 'Administrator', 'description' => 'Manages the system and users.'],
                'es' => ['display_name' => 'Administrator', 'description' => 'Gestiona el sistema y los usuarios.'],
                'it' => ['display_name' => 'Administrator', 'description' => 'Gestisce il sistema e gli utenti.'],
            ],
            'priority' => 80,
            'home_screen' => UsersManager::class,
            'permissions' => ['screen.admin.users_manager.access'],
        ],
        'registered' => [
            'default_translations' => [
                'en' => ['display_name' => 'Registered', 'description' => 'Registered user with limited permissions.'],
                'es' => ['display_name' => 'Registered', 'description' => 'Usuario registrado con permisos limitados.'],
                'it' => ['display_name' => 'Registered', 'description' => 'Utente registrato con permessi limitati.'],
            ],
            'priority' => 60,
            'home_screen' => Home::class,
            'permissions' => ['screen.user.home.access'],
        ],
        'approved' => [
            'default_translations' => [
                'en' => ['display_name' => 'Approved', 'description' => 'Approved user with access to additional features.'],
                'es' => ['display_name' => 'Approved', 'description' => 'Usuario aprobado con acceso a funciones adicionales.'],
                'it' => ['display_name' => 'Approved', 'description' => 'Utente approvato con accesso a funzionalità aggiuntive.'],
            ],
            'priority' => 40,
            'home_screen' => Home::class,
            'permissions' => ['screen.user.home.access'],
        ],
        'translator' => [
            'default_translations' => [
                'en' => ['display_name' => 'Translator', 'description' => 'User responsible for managing translations.'],
                'es' => ['display_name' => 'Translator', 'description' => 'Usuario responsable de gestionar las traducciones.'],
                'it' => ['display_name' => 'Translator', 'description' => 'Utente responsabile della gestione delle traduzioni.'],
            ],
            'priority' => 20,
            'home_screen' => TranslateManager::class,
            'permissions' => [],
        ],
        'developer' => [
            'default_translations' => [
                'en' => ['display_name' => 'Developer', 'description' => 'User with access to development and debugging tools.'],
                'es' => ['display_name' => 'Developer', 'description' => 'Usuario con acceso a herramientas de desarrollo y depuración.'],
                'it' => ['display_name' => 'Developer', 'description' => 'Utente con accesso a strumenti di sviluppo e debug.'],
            ],
            'priority' => 10,
            'home_screen' => Home::class,
            'permissions' => ['debug.logs', 'debug.access'],
        ],
    ],

    'permissions' => [
        'debug.logs' => [
            'default_translations' => [
                'en' => ['display_name' => 'View System Logs', 'description' => 'Allows viewing system activity and error logs.'],
                'es' => ['display_name' => 'View System Logs', 'description' => 'Permite ver los registros de actividad y errores del sistema.'],
                'it' => ['display_name' => 'View System Logs', 'description' => 'Consente di visualizzare i registri di sistema e gli errori.'],
            ],
        ],
        'debug.access' => [
            'default_translations' => [
                'en' => ['display_name' => 'Access Debug Tools', 'description' => 'Allows access to debugging and diagnostic tools.'],
                'es' => ['display_name' => 'Access Debug Tools', 'description' => 'Permite acceder a herramientas de depuración y diagnóstico.'],
                'it' => ['display_name' => 'Access Debug Tools', 'description' => 'Consente l\'accesso a strumenti di debug e diagnostici.'],
            ],
        ],
        'manage.users' => [
            'default_translations' => [
                'en' => ['display_name' => 'Manage Users', 'description' => 'Allows creating, editing, and deleting users.'],
                'es' => ['display_name' => 'Manage Users', 'description' => 'Permite crear, editar y eliminar usuarios.'],
                'it' => ['display_name' => 'Manage Users', 'description' => 'Consente di creare, modificare ed eliminare utenti.'],
            ],
        ],
        'manage.roles' => [
            'default_translations' => [
                'en' => ['display_name' => 'Manage Roles', 'description' => 'Allows creating, editing, and deleting roles and their permissions.'],
                'es' => ['display_name' => 'Manage Roles', 'description' => 'Permite crear, editar y eliminar roles y sus permisos.'],
                'it' => ['display_name' => 'Manage Roles', 'description' => 'Consente di creare, modificare ed eliminare ruoli e i loro permessi.'],
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
            ['code' => 'es', 'name' => 'Spanish', 'native_name' => 'Espanol', 'active' => true],
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
