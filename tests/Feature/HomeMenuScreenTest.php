<?php

use App\Models\User;
use App\UI\Screens\Admin\Dashboard;
use App\UI\Screens\Home;
use App\UI\Screens\Menu;
use App\UI\Screens\Auth\Profile;

it('returns home screen with expected core components', function () {
    $ui = uiScenario($this, Home::class, ['reset' => true]);

    $welcomeUsim = $ui->component('welcome_usim');

    $welcomeUsim->expect('type')->toBe('label');
    expect($welcomeUsim->data()['html'] ?? null)->not->toBeNull();

    $ui->assertNoIssues();
});

it('home screen renders the welcome-usim html fragment', function () {
    $ui = uiScenario($this, Home::class, ['reset' => true]);

    $data = $ui->component('welcome_usim')->data();

    expect($data['type'] ?? null)->toBe('label');
    expect($data['html'] ?? '')->toContain('class="wf"');

    $ui->assertNoIssues();
});

it('home screen fragment contains expected landing sections', function () {
    $ui = uiScenario($this, Home::class, ['reset' => true]);

    $html = $ui->component('welcome_usim')->data()['html'] ?? '';

    expect($html)->toContain('class="hero"');
    expect($html)->toContain('class="wf"');

    $ui->assertNoIssues();
});

it('home fragment theme follows global document theme contract', function () {
    $ui = uiScenario($this, Home::class, ['reset' => true]);

    $html = $ui->component('welcome_usim')->data()['html'] ?? '';

    expect($html)->toContain('class="wf"');
    expect($html)->not->toContain('class="wf" data-theme="dark"');
    expect($html)->toContain('html[data-theme="light"] .wf');
    expect($html)->toContain('body[data-theme="light"] .wf');

    $ui->assertNoIssues();
});

it('home screen fragment is non-empty regardless of locale', function () {
    $originalLocale = app()->getLocale();
    app()->setLocale('es');

    $ui = uiScenario($this, Home::class, ['reset' => true]);
    $html = $ui->component('welcome_usim')->data()['html'] ?? '';

    expect($html)->not->toBeEmpty();

    app()->setLocale($originalLocale);

    $ui->assertNoIssues();
});

it('returns menu screen for guests with settings trigger and register option', function () {
    $originalLocale = app()->getLocale();

    foreach (['en', 'es'] as $locale) {
        app()->setLocale($locale);

        $ui = uiScenario($this, Menu::class, ['parent' => 'menu']);
        $storage = json_decode($ui->opaqueUsim(), true) ?: [];
        $menuLang = (string) ($storage['store_lang'] ?? app()->getLocale());

        $mainMenu = $ui->component('main_menu')->data();
        $userMenu = $ui->component('user_menu')->data();
        $themeToggle = $ui->component('theme_toggle')->data();

        expect($mainMenu['type'] ?? null)->toBe('menudropdown');
        expect(menuItemsContainLabel($mainMenu['items'] ?? [], t('screen.menu.items.home', [], $menuLang)))->toBeTrue();
        expect(menuItemsContainLabel($mainMenu['items'] ?? [], t('screen.menu.items.about', [], $menuLang)))->toBeTrue();

        expect($userMenu['type'] ?? null)->toBe('menudropdown');
        expect($userMenu['trigger']['label'] ?? null)->toBe('⚙️');
        expect(menuItemsContainLabel($userMenu['items'] ?? [], t('screen.menu.items.register', [], $menuLang)))->toBeTrue();
        expect(menuItemsContainLabel($userMenu['items'] ?? [], t('screen.menu.items.logout', [], $menuLang)))->toBeFalse();
        expect($themeToggle['icon_color'] ?? null)->toBe('var(--usim-menu-trigger-text)');

        $ui->assertNoIssues();
    }

    app()->setLocale($originalLocale);
});

it('returns menu screen for authenticated users with user trigger and logout option', function () {
    $originalLocale = app()->getLocale();

    foreach (['en', 'es'] as $locale) {
        app()->setLocale($locale);

        /** @var \Tests\TestCase $this */
        /** @var User $user */
        $user = User::factory()->create([
            'name' => 'Menu Tester',
        ]);

        $this->actingAs($user);

        $ui = uiScenario($this, Menu::class, ['parent' => 'menu']);
        $userMenu = $ui->component('user_menu')->data();

        expect($userMenu['type'] ?? null)->toBe('menudropdown');

        $triggerLabel = (string) ($userMenu['trigger']['label'] ?? '');
        expect($triggerLabel)->toContain('Menu Tester');

        expect(menuItemsContainLabel($userMenu['items'] ?? [], t('screen.menu.items.logout')))->toBeTrue();
        expect(menuItemsContainLabel($userMenu['items'] ?? [], t('screen.menu.items.register')))->toBeFalse();

        $ui->assertNoIssues();
    }

    app()->setLocale($originalLocale);
});

it('shows profile, logout and admin dashboard items after admin login', function () {
    /** @var \Tests\TestCase $this */
    $this->loginAs('admin');

    $ui = uiScenario($this, Menu::class, ['parent' => 'menu']);
    $mainMenu = $ui->component('main_menu')->data();
    $userMenu = $ui->component('user_menu')->data();

    expect(menuItemsContainLabel($mainMenu['items'] ?? [], Dashboard::getMenuLabel()))->toBeTrue();
    expect(menuItemsContainLabel($userMenu['items'] ?? [], Profile::getMenuLabel()))->toBeTrue();
    expect(menuItemsContainLabel($userMenu['items'] ?? [], t('screen.menu.items.logout')))->toBeTrue();

    $ui->assertNoIssues();
});

it('shows profile/logout and hides admin dashboard after regular user login', function () {
    /** @var \Tests\TestCase $this */
    $this->loginAs('user');

    $ui = uiScenario($this, Menu::class, ['parent' => 'menu']);
    $mainMenu = $ui->component('main_menu')->data();
    $userMenu = $ui->component('user_menu')->data();

    expect(menuItemsContainLabel($mainMenu['items'] ?? [], Dashboard::getMenuLabel()))->toBeFalse();
    expect(menuItemsContainLabel($userMenu['items'] ?? [], Profile::getMenuLabel()))->toBeTrue();
    expect(menuItemsContainLabel($userMenu['items'] ?? [], t('screen.menu.items.logout')))->toBeTrue();

    $ui->assertNoIssues();
});
