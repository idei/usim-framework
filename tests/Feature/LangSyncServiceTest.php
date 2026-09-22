<?php

use Idei\Usim\Models\UsimLanguage;
use Idei\Usim\Support\LangSyncService;
use Idei\Usim\Support\UsimConfig;

it('syncs configured languages and marks the fallback locale properly', function () {
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

    $service = new LangSyncService();
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

it('updates existing languages and auto-creates fallback language if missing from configured list', function () {
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

    $service = new LangSyncService();
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

