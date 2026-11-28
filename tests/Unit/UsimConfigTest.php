<?php

declare(strict_types=1);

use App\Models\Device;
use App\Models\User;
use App\UI\Screens\Home;
use Idei\Usim\Support\Config\ConfigReader;
use Idei\Usim\Support\Config\DeviceConfig;
use Idei\Usim\Support\Config\I18nConfig;
use Idei\Usim\Support\Config\LanguageConfig;
use Idei\Usim\Support\Config\PermissionConfig;
use Idei\Usim\Support\Config\RoleConfig;
use Idei\Usim\Support\Config\UnitConfig;
use Idei\Usim\Support\Config\UserConfig;
use Idei\Usim\Support\UsimConfig;

it('loads safe defaults when usim config is not an array and app.url is non-string', function () {
    config()->set('usim', 'corrupted');
    config()->set('app.url', 12345);

    $usimConfig = new UsimConfig;

    expect($usimConfig->appName)->toBe('USIM Framework')
        ->and($usimConfig->apiUrl)->toBe('http://localhost')
        ->and($usimConfig->frontStoreKey)->toBe('usim')
        ->and($usimConfig->screensNamespace)->toBe('App\\UI\\Screens')
        ->and($usimConfig->screensPath)->toBe(app_path('UI/Screens'))
        ->and($usimConfig->requestsNamespace)->toBe('App\\UI\\Requests')
        ->and($usimConfig->requestsPath)->toBe(app_path('UI/Requests'))
        ->and($usimConfig->uploadDisk)->toBe('local')
        ->and($usimConfig->headlessMode)->toBeFalse()
        ->and($usimConfig->defaultRegisteringRole)->toBe('registered')
        ->and($usimConfig->defaultLayout)->toBe('App\\UI\\Layouts\\WorkbenchLayout')
        ->and($usimConfig->guestLayout)->toBeNull()
        ->and($usimConfig->defaultMenuScreen)->toBe('App\\UI\\Screens\\Menu')
        ->and($usimConfig->defaultSidebarMenuScreen)->toBeNull()
        ->and($usimConfig->defaultStatusBarScreen)->toBeNull()
        ->and($usimConfig->uiCacheTtl)->toBe(7200)
        ->and($usimConfig->models)->toBe([
            'user' => User::class,
            'device' => Device::class,
        ])
        ->and($usimConfig->units)->toBe([])
        ->and($usimConfig->permissions)->toBe([])
        ->and($usimConfig->roles)->toBe([])
        ->and($usimConfig->users)->toBe([])
        ->and($usimConfig->rootUser)->toBeNull()
        ->and($usimConfig->devices)->toBe([])
        ->and($usimConfig->i18n->defaultLocale)->toBe('en')
        ->and($usimConfig->i18n->fallbackLocale)->toBe('en')
        ->and($usimConfig->i18n->autoKeyMaxLength)->toBe(30)
        ->and($usimConfig->i18n->logChannel)->toBe('i18n')
        ->and($usimConfig->i18n->logAutokeySuggestions)->toBeTrue()
        ->and($usimConfig->i18n->languages)->toBe([])
        ->and($usimConfig->i18n->keyPrefixes)->toBe([]);
});

it('normalizes scalars, collections, root user separation, and invalid entries faithfully', function () {
    config()->set('app.url', 'https://fallback.example.test');
    config()->set('usim', [
        'app_name' => 'Custom App',
        'api_url' => null,
        'front_store_key' => 'custom-key',
        'screens_namespace' => 'App\\UI\\Screens',
        'screens_path' => '/tmp/screens',
        'requests_namespace' => 'App\\Custom\\Requests',
        'requests_path' => '/tmp/requests',
        'upload_disk' => 's3',
        'headless_mode' => true,
        'default_registering_role' => 'member',
        'default_layout' => 'App\\UI\\Layouts\\CustomLayout',
        'guest_layout' => '',
        'default_menu_screen' => 'App\\UI\\Screens\\CustomMenu',
        'default_sidebar_menu_screen' => 'App\\UI\\Screens\\SidebarMenu',
        'default_status_bar_screen' => '',
        'ui_cache_ttl' => '1800',
        'models' => [
            'user' => 'App\\Models\\CustomUser',
            'device' => 999,
        ],
        'units' => [
            'structure' => [
                'main' => [
                    'parent' => null,
                    'type' => 'system',
                    'default_translations' => [
                        'en' => ['display_name' => 'Main', 'description' => 'Root unit'],
                    ],
                ],
                'invalid' => 'ignored',
            ],
        ],
        'permissions' => [
            'manage.roles' => [
                'default_translations' => [
                    'en' => ['display_name' => 'Manage Roles', 'description' => 'Role management'],
                ],
            ],
            'invalid' => 123,
        ],
        'i18n' => [
            'default_locale' => 'es',
            'fallback_locale' => 'en',
            'auto_key_max_length' => '45',
            'log_channel' => 'custom-i18n',
            'log_autokey_suggestions' => false,
            'languages' => [
                ['code' => ' es ', 'name' => 'Spanish', 'native_name' => 'Español', 'active' => true],
                ['code' => 'es', 'name' => 'Spanish Dup', 'native_name' => 'Español', 'active' => true],
                ['code' => 'fr', 'name' => 'French', 'native_name' => 'Français', 'active' => false],
                ['code' => '   ', 'name' => 'Blank', 'native_name' => 'Blank', 'active' => true],
                'not-an-array',
            ],
            'i18n_key_prefixes' => [
                'role' => 'role.',
                'invalid_value' => 42,
                0 => 'numeric-key-ignored',
            ],
        ],
        'roles' => [
            'admin' => [
                'default_translations' => [
                    'en' => ['display_name' => 'Admin', 'description' => 'Admin role'],
                ],
                'priority' => '2',
                'home_screen' => Home::class,
                'permissions' => ['manage.roles'],
                'guard_name' => 'web',
            ],
            'sensor' => [
                'priority' => 'invalid',
                'guard_name' => 'device',
            ],
            'ignored' => false,
        ],
        'users' => [
            'root' => [
                'first_name' => 'Root',
                'last_name' => 'Admin',
                'email' => 'root@example.test',
                'password' => 'secret',
                'unit_roles' => ['main' => ['admin']],
            ],
            'operator' => [
                'first_name' => 'Jane',
                'last_name' => 'Doe',
                'email' => 'jane@example.test',
                'password' => 'pass',
                'unit_roles' => ['main' => ['member']],
            ],
            'invalid' => 'skip-me',
        ],
        'devices' => [
            'tv-1' => [
                'name' => 'Lobby TV',
                'specs' => ['aspect_ratio' => '16:9'],
                'unit_roles' => ['main' => ['sensor']],
            ],
            'invalid' => null,
        ],
    ]);

    $usimConfig = new UsimConfig;

    expect($usimConfig->appName)->toBe('Custom App')
        ->and($usimConfig->apiUrl)->toBe('https://fallback.example.test')
        ->and($usimConfig->frontStoreKey)->toBe('custom-key')
        ->and($usimConfig->screensPath)->toBe('/tmp/screens')
        ->and($usimConfig->requestsNamespace)->toBe('App\\Custom\\Requests')
        ->and($usimConfig->requestsPath)->toBe('/tmp/requests')
        ->and($usimConfig->uploadDisk)->toBe('s3')
        ->and($usimConfig->headlessMode)->toBeTrue()
        ->and($usimConfig->defaultRegisteringRole)->toBe('member')
        ->and($usimConfig->defaultLayout)->toBe('App\\UI\\Layouts\\CustomLayout')
        ->and($usimConfig->guestLayout)->toBeNull()
        ->and($usimConfig->defaultMenuScreen)->toBe('App\\UI\\Screens\\CustomMenu')
        ->and($usimConfig->defaultSidebarMenuScreen)->toBe('App\\UI\\Screens\\SidebarMenu')
        ->and($usimConfig->defaultStatusBarScreen)->toBeNull()
        ->and($usimConfig->uiCacheTtl)->toBe(1800)
        ->and($usimConfig->models)->toBe([
            'user' => 'App\\Models\\CustomUser',
            'device' => Device::class,
        ]);

    expect($usimConfig->units)->toHaveKeys(['main'])
        ->and($usimConfig->units)->not->toHaveKey('invalid')
        ->and($usimConfig->units['main']->parent)->toBeNull()
        ->and($usimConfig->units['main']->type)->toBe('system');

    expect($usimConfig->permissions)->toHaveKeys(['manage.roles'])
        ->and($usimConfig->permissions)->not->toHaveKey('invalid')
        ->and($usimConfig->permissions['manage.roles']->defaultTranslations['en']['display_name'])->toBe('Manage Roles');

    expect($usimConfig->i18n->defaultLocale)->toBe('es')
        ->and($usimConfig->i18n->fallbackLocale)->toBe('en')
        ->and($usimConfig->i18n->autoKeyMaxLength)->toBe(45)
        ->and($usimConfig->i18n->logChannel)->toBe('custom-i18n')
        ->and($usimConfig->i18n->logAutokeySuggestions)->toBeFalse()
        ->and($usimConfig->i18n->languages)->toHaveCount(4)
        ->and($usimConfig->i18n->keyPrefixes)->toBe(['role' => 'role.'])
        ->and($usimConfig->activeLanguageCodes)->toBe(['es']);

    expect($usimConfig->roleNames)->toBe(['admin', 'sensor'])
        ->and($usimConfig->roles['admin']->priority)->toBe(2)
        ->and($usimConfig->roles['admin']->homeScreen)->toBe(Home::class)
        ->and($usimConfig->roles['admin']->permissions)->toBe(['manage.roles'])
        ->and($usimConfig->roles['sensor']->priority)->toBe(10)
        ->and($usimConfig->roles['sensor']->homeScreen)->toBeNull()
        ->and($usimConfig->roles['sensor']->guardName)->toBe('device')
        ->and(array_keys($usimConfig->deviceRoles))->toBe(['sensor']);

    expect($usimConfig->rootUser)->toBeInstanceOf(UserConfig::class)
        ->and($usimConfig->rootUser?->email)->toBe('root@example.test')
        ->and($usimConfig->users)->toHaveKeys(['operator'])
        ->and($usimConfig->users)->not->toHaveKey('root')
        ->and($usimConfig->users['operator']->firstName)->toBe('Jane');

    expect($usimConfig->devices)->toHaveKeys(['tv-1'])
        ->and($usimConfig->devices['tv-1']->name)->toBe('Lobby TV')
        ->and($usimConfig->devices['tv-1']->specs)->toBe(['aspect_ratio' => '16:9'])
        ->and($usimConfig->devices['tv-1']->unitRoles)->toBe(['main' => ['sensor']]);

    expect($usimConfig->resolveScreenClass('home'))->toBe(Home::class)
        ->and($usimConfig->resolveScreenClass('does/not-exist'))->toBeNull()
        ->and($usimConfig->resolveScreenSlug(Home::class))->toBe('home')
        ->and($usimConfig->resolveScreenSlug('App\\UI\\Screens\\Admin\\UsersManager'))->toBe('admin/users-manager')
        ->and($usimConfig->resolveScreenSlug('Vendor\\Other\\CustomScreen'))->toBe('custom-screen');
});

it('builds config DTOs directly from raw arrays via fromArray factories and ConfigReader', function () {
    $reader = ConfigReader::fromMixed([
        'name' => 'ok',
        'empty' => '',
        'flag' => true,
        'count' => '15',
    ]);

    expect($reader->string('name', 'fallback'))->toBe('ok')
        ->and($reader->string('missing', 'fallback'))->toBe('fallback')
        ->and($reader->nullableString('empty'))->toBe('')
        ->and($reader->nonEmptyString('empty'))->toBeNull()
        ->and($reader->nonEmptyString('name'))->toBe('ok')
        ->and($reader->bool('flag'))->toBeTrue()
        ->and($reader->int('count', 1))->toBe(15)
        ->and($reader->int('name', 99))->toBe(99);

    expect(UnitConfig::fromArray(['parent' => 'root', 'type' => 'dept']))->toBeInstanceOf(UnitConfig::class)
        ->and(PermissionConfig::fromArray([]))->toBeInstanceOf(PermissionConfig::class)
        ->and(LanguageConfig::fromArray(['code' => 'en', 'active' => true])->active)->toBeTrue()
        ->and(I18nConfig::fromArray([])->defaultLocale)->toBe('en')
        ->and(RoleConfig::fromArray([])->guardName)->toBe('web')
        ->and(UserConfig::fromArray(['email' => 'u@test.local'])->email)->toBe('u@test.local')
        ->and(DeviceConfig::fromArray(['name' => 'dev'])->name)->toBe('dev');
});
