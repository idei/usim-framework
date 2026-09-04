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
    | Organizational Units (Units)
    |--------------------------------------------------------------------------
    |
    | Define organizational units (departments, directions, etc.) and their hierarchy.
    */
    'units' => [
        'structure' => [
            'main' => [
                'parent' => null,
                'type' => 'system', // Optional: institution, department, division, team, etc.
                'default_translations' => [
                    'en' => ['display_name' => 'Initial', 'description' => 'Default system unit.'],
                    'es' => ['display_name' => 'Inicial', 'description' => 'Unidad base del sistema.'],
                ],
            ],
            'lobby' => [
                'type' => 'system',
                'default_translations' => [
                    'en' => ['display_name' => 'Lobby', 'description' => 'Default system unit.'],
                    'es' => ['display_name' => 'Espera', 'description' => 'Unidad base del sistema.'],
                ],
            ],
            'idei' => [
                'type' => 'institute',
                'default_translations' => [
                    'en' => ['display_name' => 'Institute of Informatics', 'description' => 'Institute for Idei operations.'],
                    'es' => ['display_name' => 'Instituto de Informática', 'description' => 'Instituto de investigación.'],
                ],
            ],
            'ingeo' => [
                'type' => 'institute',
                'default_translations' => [
                    'en' => ['display_name' => 'Institute of Geology', 'description' => 'Institute for Ingeo operations.'],
                    'es' => ['display_name' => 'Instituto de Geología', 'description' => 'Instituto de investigación.'],
                ],
            ],
            'oafa' => [
                'type' => 'institute',
                'default_translations' => [
                    'en' => ['display_name' => 'A.O.F.A.', 'description' => 'Félix Aguilar Astronomical Observatory'],
                    'es' => ['display_name' => 'O.A.F.A.', 'description' => 'Observatorio Astronómico Félix Aguilar'],
                ],
            ],
            'comunicación' => [
                'type' => 'department',
                'default_translations' => [
                    'en' => ['display_name' => 'Communication', 'description' => 'Communication department.'],
                    'es' => ['display_name' => 'Comunicación', 'description' => 'Departamento de comunicación.'],
                ],
            ],
        ],
    ],

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
            'unit_roles' => [
                'main' => ['root'],
            ],
        ],

        'admin' => [
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin@example.com',
            'password' => '11111111',
            'unit_roles' => [
                'oafa' => ['admin'],
                'ingeo' => ['admin'],
            ],
        ],

        'test' => [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'password' => '11111111',
            'unit_roles' => [
                'idei' => ['admin', 'translator'],
            ],
        ],

        'test2' => [
            'first_name' => 'Test2',
            'last_name' => 'User2',
            'email' => 'test2@example.com',
            'password' => '11111111',
            'unit_roles' => [
                'ingeo' => ['admin'],
            ],
        ],

        'test3' => [
            'first_name' => 'Test3',
            'last_name' => 'User3',
            'email' => 'test3@example.com',
            'password' => '11111111',
            'unit_roles' => [
                'oafa' => ['admin'],
            ],
        ],

        'com-admin' => [
            'first_name' => 'Comunicación',
            'last_name' => 'User',
            'email' => 'comunicacion@example.com',
            'password' => '11111111',
            'unit_roles' => [
                'comunicación' => ['admin'],
            ],
        ],

        'idei-user-1' => [
            'first_name' => 'Idei',
            'last_name' => 'User',
            'email' => 'idei@example.com',
            'password' => '11111111',
            'unit_roles' => [
                'idei' => ['member'],
            ],
        ],

        'registered-user' => [
            'first_name' => 'Registered',
            'last_name' => 'User',
            'email' => 'registered@example.com',
            'password' => '11111111',
            'unit_roles' => [
                'lobby' => ['registered'],
            ],
        ],
    ],

    'roles' => [
        'registered' => [
            'default_translations' => [
                'en' => ['display_name' => 'Registered User', 'description' => 'User with basic access to the system.'],
                'es' => ['display_name' => 'Usuario Registrado', 'description' => 'Usuario con acceso básico al sistema.'],
            ],
            'priority' => 0,
            'home_screen' => Home::class,
            'permissions' => ['home.access'],
        ],
        'root' => [
            'default_translations' => [
                'en' => ['display_name' => 'Root', 'description' => 'User with total and unconditional access to all features.'],
                'es' => ['display_name' => 'Raíz', 'description' => 'Usuario con acceso total e incondicional a todas las funciones.'],
            ],
            'priority' => 1,
            'home_screen' => UsersManager::class,
            'permissions' => ['admin.users_manager.access', 'manage.roles'],
        ],
        'admin' => [
            'default_translations' => [
                'en' => ['display_name' => 'Administrator', 'description' => 'Manages the system and users.'],
                'es' => ['display_name' => 'Administrador', 'description' => 'Gestiona el sistema y los usuarios.'],
            ],
            'priority' => 2,
            'home_screen' => UsersManager::class,
            'permissions' => ['admin.users_manager.access'],
        ],
        'translator' => [
            'default_translations' => [
                'en' => ['display_name' => 'Translator', 'description' => 'User responsible for managing translations.'],
                'es' => ['display_name' => 'Traductor', 'description' => 'Usuario responsable de gestionar las traducciones.'],
            ],
            'priority' => 3,
            'home_screen' => TranslateManager::class,
            'permissions' => ['admin.translate_manager.access'],
        ],
        'member' => [
            'default_translations' => [
                'en' => ['display_name' => 'Member', 'description' => 'User with access to specific features.'],
                'es' => ['display_name' => 'Miembro', 'description' => 'Usuario con acceso a funciones específicas.'],
            ],
            'priority' => 4,
            'home_screen' => Home::class,
            'permissions' => ['home.access'],
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
        'approve.publications' => [
            'default_translations' => [
                'en' => ['display_name' => 'Approve Publications', 'description' => 'Allows approving or rejecting publications submitted by users.'],
                'es' => ['display_name' => 'Aprobar Publicaciones', 'description' => 'Permite aprobar o rechazar publicaciones enviadas por los usuarios.'],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Registering Role
    |--------------------------------------------------------------------------
    |
    | Rol que se le asignará al usuario al registrarse.
    |
    */
    'default_registering_role' => 'registered',

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
