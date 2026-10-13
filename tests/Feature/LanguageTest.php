<?php

use App\UI\Screens\Home;
use App\UI\Screens\Menu;
use Idei\Usim\Models\UsimLanguage;

it('changes application language and redirects with reset=true', function () {
    UsimLanguage::create(['code' => 'en', 'name' => 'English', 'native_name' => 'English', 'is_active' => true, 'is_fallback' => true]);
    UsimLanguage::create(['code' => 'es', 'name' => 'Spanish', 'native_name' => 'Español', 'is_active' => true, 'is_fallback' => false]);

    // 1. Initial page load of Home
    $ui = uiScenario($this, Home::class);
    $initialLangMenu = $ui->component('lang_menu')->data();
    expect($initialLangMenu['trigger']['label'] ?? '')->toBe('EN');

    // 2. Click changeLang to switch to Spanish
    $response = $ui->action('lang_menu', 'changeLang', ['lang' => 'es']);
    $response->assertOk();
    expect($response->json('change_language'))->toBe('es');
    expect($response->json('redirect'))->toContain('reset=true');

    $updatedStorage = json_decode($ui->opaqueUsim(), true);
    expect($updatedStorage['store_lang'] ?? '')->toBe('es');

    // 3. User browser navigates to redirect target with updated storage
    $uiAfterRedirect = uiScenario($this, Home::class, ['storage' => $updatedStorage]);
    $reloadedLangMenu = $uiAfterRedirect->component('lang_menu')->data();
    expect($reloadedLangMenu['trigger']['label'] ?? '')->toBe('ES');
});

