<?php

use Idei\Usim\Models\UsimLanguage;
use Idei\Usim\Support\LangSyncService;
use Idei\Usim\Support\UsimConfig;
use Illuminate\Support\Facades\File;

// 1. Definimos la variable en el ámbito del archivo. Se calculará una sola vez.
$tempLangPath = sys_get_temp_dir() . '/usim_lang_sync_test_' . uniqid();

// 2. Usamos beforeAll para crear el directorio una sola vez antes de correr los tests de este archivo
beforeAll(function () use ($tempLangPath) {
    File::makeDirectory($tempLangPath, 0755, true);
});

// 3. Usamos afterAll para limpiar todo al finalizar la batería de tests de este archivo
afterAll(function () use ($tempLangPath) {
    if (File::exists($tempLangPath)) {
        File::deleteDirectory($tempLangPath);
    }
});

// 4. En tus tests, simplemente importas la variable con `use`
it('sincroniza los archivos de idioma', function () use ($tempLangPath) {
    // $tempLangPath es un string nativo, sin advertencias del IDE
    $this->assertDirectoryExists($tempLangPath);
});

it('syncs configured languages and marks the fallback locale properly', function () use (&$tempLangPath) {
    UsimLanguage::query()->delete();

    config([
        'usim.i18n' => [
            'default_locale' => 'es',
            'fallback_locale' => 'en',
            'languages' => [
                [
                    'code' => 'en',
                    'name' => 'English',
                    'native_name' => 'English',
                    'active' => true,
                ],
                [
                    'code' => 'es',
                    'name' => 'Spanish',
                    'native_name' => 'Español',
                    'active' => true,
                ],
                [
                    'code' => 'fr',
                    'name' => '',
                    'native_name' => '',
                    'active' => false,
                ],
            ],
        ],
    ]);
    app()->forgetInstance(UsimConfig::class);

    $usimConfig = app(UsimConfig::class);

    $service = new LangSyncService($usimConfig, baseLangPath: $tempLangPath);
    $stats = $service->sync();

    expect($stats['languages_created'])->toBe(3);
    expect($stats['languages_updated'])->toBe(0);

    $en = UsimLanguage::where('code', 'en')->first();
    expect($en)->not->toBeNull();
    expect($en->name)->toBe('English');
    expect($en->native_name)->toBe('English');
    expect($en->is_active)->toBeTrue();
    expect($en->is_fallback)->toBeTrue();

    $es = UsimLanguage::where('code', 'es')->first();
    expect($es)->not->toBeNull();
    expect($es->name)->toBe('Spanish');
    expect($es->native_name)->toBe('Español');
    expect($es->is_active)->toBeTrue();
    expect($es->is_fallback)->toBeFalse();

    $fr = UsimLanguage::where('code', 'fr')->first();
    expect($fr)->not->toBeNull();
    expect($fr->name)->toBe('FR');
    expect($fr->native_name)->toBeNull();
    expect($fr->is_active)->toBeFalse();
    expect($fr->is_fallback)->toBeFalse();
});

it('updates existing languages and auto-creates fallback language if missing from configured list', function () use (&$tempLangPath) {
    UsimLanguage::query()->delete();

    UsimLanguage::create([
        'code' => 'es',
        'name' => 'Old Spanish',
        'native_name' => 'Old Español',
        'is_active' => false,
        'is_fallback' => true,
    ]);

    config([
        'usim.i18n' => [
            'default_locale' => 'es',
            'fallback_locale' => 'pt',
            'languages' => [
                [
                    'code' => 'es',
                    'name' => 'Spanish',
                    'native_name' => 'Español',
                    'active' => true,
                ],
            ],
        ],
    ]);
    app()->forgetInstance(UsimConfig::class);

    $usimConfig = app(UsimConfig::class);

    $service = new LangSyncService($usimConfig, baseLangPath: $tempLangPath);
    $stats = $service->sync();

    // 'es' updated (1) and 'pt' fallback created (1)
    expect($stats['languages_updated'])->toBe(1);
    expect($stats['languages_created'])->toBe(1);

    $es = UsimLanguage::where('code', 'es')->first();
    expect($es->name)->toBe('Spanish');
    expect($es->is_active)->toBeTrue();
    expect($es->is_fallback)->toBeFalse();

    $pt = UsimLanguage::where('code', 'pt')->first();
    expect($pt)->not->toBeNull();
    expect($pt->name)->toBe('PT');
    expect($pt->native_name)->toBe('PT');
    expect($pt->is_active)->toBeTrue();
    expect($pt->is_fallback)->toBeTrue();
});

it('syncs role and permission default translations into lang files using UsimConfig', function () use (&$tempLangPath) {
    config([
        'usim.i18n' => [
            'default_locale' => 'en',
            'fallback_locale' => 'en',
            'i18n_key_prefixes' => [
                'role' => 'role.',
                'permission' => 'permission.',
            ],
            'languages' => [
                [
                    'code' => 'en',
                    'name' => 'English',
                    'native_name' => 'English',
                    'active' => true,
                ],
                [
                    'code' => 'es',
                    'name' => 'Spanish',
                    'native_name' => 'Español',
                    'active' => true,
                ],
            ],
        ],
        'usim.roles' => [
            'editor' => [
                'priority' => 50,
                'home_screen' => 'dashboard',
                'guard_name' => 'web',
                'default_translations' => [
                    'en' => [
                        'display_name' => 'Content Editor',
                        'description' => 'Can edit content.',
                    ],
                    'es' => [
                        'display_name' => 'Editor de Contenido',
                        'description' => 'Puede editar contenido.',
                    ],
                ],
            ],
        ],
        'usim.permissions' => [
            'posts.publish' => [
                'default_translations' => [
                    'en' => [
                        'display_name' => 'Publish Posts',
                        'description' => 'Allows publishing articles.',
                    ],
                    'es' => [
                        'display_name' => 'Publicar Artículos',
                        'description' => 'Permite publicar artículos.',
                    ],
                ],
            ],
        ],
    ]);
    app()->forgetInstance(UsimConfig::class);

    $usimConfig = app(UsimConfig::class);

    $service = new LangSyncService($usimConfig, baseLangPath: $tempLangPath);
    $service->sync();

    $enRoleFile = $tempLangPath . '/en/role.php';
    $esRoleFile = $tempLangPath . '/es/role.php';
    $enPermFile = $tempLangPath . '/en/permission.php';
    $esPermFile = $tempLangPath . '/es/permission.php';

    expect(File::exists($enRoleFile))->toBeTrue();
    expect(File::exists($esRoleFile))->toBeTrue();
    expect(File::exists($enPermFile))->toBeTrue();
    expect(File::exists($esPermFile))->toBeTrue();

    $enRoles = require $enRoleFile;
    $esRoles = require $esRoleFile;
    $enPerms = require $enPermFile;
    $esPerms = require $esPermFile;

    expect($enRoles['editor']['name'])->toBe('Content Editor');
    expect($enRoles['editor']['description'])->toBe('Can edit content.');
    expect($esRoles['editor']['name'])->toBe('Editor de Contenido');
    expect($esRoles['editor']['description'])->toBe('Puede editar contenido.');

    expect($enPerms['posts']['publish']['name'])->toBe('Publish Posts');
    expect($enPerms['posts']['publish']['description'])->toBe('Allows publishing articles.');
    expect($esPerms['posts']['publish']['name'])->toBe('Publicar Artículos');
    expect($esPerms['posts']['publish']['description'])->toBe('Permite publicar artículos.');
});
