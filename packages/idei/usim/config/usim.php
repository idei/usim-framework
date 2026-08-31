<?php

// packages/idei/usim/config/usim.php

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
	'front_store_key' => env('FRONT_STORE_KEY', 'change-this-store-key'),

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
	| API Client Route
	|--------------------------------------------------------------------------
	|
	| La ruta para acceder al cliente de la API.
	*/
	'api_client' => env('API_CLIENT_ROUTE', '/api-client'),

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
                'type' => 'institution', // Optional: institution, department, division, team, etc.
                'default_translations' => [
                    'en' => ['display_name' => 'Main Organization', 'description' => 'Default system unit.'],
                    'es' => ['display_name' => 'Organización Principal', 'description' => 'Unidad base del sistema.'],
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
	],

	'roles' => [
		'guest' => [
			'default_translations' => [
				'en' => ['display_name' => 'Guest', 'description' => 'User with basic access to the system.'],
				'es' => ['display_name' => 'Invitado', 'description' => 'Usuario con acceso básico al sistema.'],
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
		'registered' => [
			'default_translations' => [
				'en' => ['display_name' => 'Registered User', 'description' => 'User with basic access to the system.'],
				'es' => ['display_name' => 'Usuario Registrado', 'description' => 'Usuario con acceso básico al sistema.'],
			],
			'priority' => 4,
			'home_screen' => Home::class,
			'permissions' => ['home.access'],
		],
	],

	'permissions' => [
		'manage.roles' => [
			'default_translations' => [
				'en' => ['display_name' => 'Manage Roles', 'description' => 'Allows creating, editing, and deleting roles and their permissions.'],
				'es' => ['display_name' => 'Gestionar Roles', 'description' => 'Permite crear, editar y eliminar roles y sus permisos.'],
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

	/*
	|--------------------------------------------------------------------------
	| UI Cache TTL
	|--------------------------------------------------------------------------
	| Tiempo de vida (en segundos) para el cache de estado de UI.
	| Se recomienda un valor entre 30 minutos (1800) y 24 horas (86400).
	| Ajusta este valor según la frecuencia de cambios en la UI y la cantidad de usuarios.
	*/
	'ui_cache_ttl' => env('UI_CACHE_TTL', 1800),
];
