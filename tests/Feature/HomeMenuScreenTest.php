<?php

use App\Models\User;
use App\UI\Screens\Admin\UsersManager;
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
    $this->loginAs('root');

    $ui = uiScenario($this, Menu::class, ['parent' => 'menu']);
    $mainMenu = $ui->component('main_menu')->data();
    $userMenu = $ui->component('user_menu')->data();

    expect(menuItemsContainLabel($mainMenu['items'] ?? [], UsersManager::getMenuLabel()))->toBeTrue();
    expect(menuItemsContainLabel($userMenu['items'] ?? [], Profile::getMenuLabel()))->toBeTrue();
    expect(menuItemsContainLabel($userMenu['items'] ?? [], t('screen.menu.items.logout')))->toBeTrue();

    $ui->assertNoIssues();
});

use Idei\Usim\Models\UsimUnit;

it('shows profile/logout and hides admin dashboard after regular user login', function () {
    $defaultRegisteringRole = config('usim.default_registering_role', 'user');
    /** @var \Tests\TestCase $this */
    $this->loginAs($defaultRegisteringRole);

    $ui = uiScenario($this, Menu::class, ['parent' => 'menu']);
    $mainMenu = $ui->component('main_menu')->data();
    $userMenu = $ui->component('user_menu')->data();

    expect(menuItemsContainLabel($mainMenu['items'] ?? [], UsersManager::getMenuLabel()))->toBeFalse();
    expect(menuItemsContainLabel($userMenu['items'] ?? [], Profile::getMenuLabel()))->toBeTrue();
    expect(menuItemsContainLabel($userMenu['items'] ?? [], t('screen.menu.items.logout')))->toBeTrue();

    $ui->assertNoIssues();
});

it('does not include unit dropdown for guests', function () {
    $ui = uiScenario($this, Menu::class, ['parent' => 'menu']);
    expect(fn () => $ui->component('unit_menu'))->toThrow(RuntimeException::class);
    $ui->assertNoIssues();
});

it('does not include unit dropdown for authenticated user with no units', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create(['name' => 'No Units User']);
    $this->actingAs($user);

    $ui = uiScenario($this, Menu::class, ['parent' => 'menu']);
    expect(fn () => $ui->component('unit_menu'))->toThrow(RuntimeException::class);
    $ui->assertNoIssues();
});

it('does not include unit dropdown for authenticated user with only one unit', function () {
    /** @var \Tests\TestCase $this */
    $unit = UsimUnit::firstOrCreate(['slug' => 'main']);
    $user = User::factory()->create(['name' => 'Single Unit User']);
    $user->usimUnits()->attach($unit->id);
    $this->actingAs($user);

    $ui = uiScenario($this, Menu::class, ['parent' => 'menu']);
    expect(fn () => $ui->component('unit_menu'))->toThrow(RuntimeException::class);
    $ui->assertNoIssues();
});

it('includes unit dropdown when authenticated user has multiple units', function () {
    /** @var \Tests\TestCase $this */
    $unit1 = UsimUnit::firstOrCreate(['slug' => 'main']);
    $unit2 = UsimUnit::firstOrCreate(['slug' => 'accounting']);
    $user = User::factory()->create(['name' => 'Multi Unit User']);
    $user->usimUnits()->attach([$unit1->id, $unit2->id]);
    $this->actingAs($user);

    $ui = uiScenario($this, Menu::class, ['parent' => 'menu']);
    $unitMenu = $ui->component('unit_menu');

    expect($unitMenu)->not->toBeNull();
    $data = $unitMenu->data();
    expect($data['type'] ?? null)->toBe('menudropdown');
    expect($data['trigger']['label'] ?? '')->toContain('🏢');
    expect(menuItemsContainLabel($data['items'] ?? [], '✓ ' . ($unit1->display_name ?: 'Main')))->toBeTrue();
    expect(menuItemsContainLabel($data['items'] ?? [], 'Accounting'))->toBeTrue();

    $ui->assertNoIssues();
});

it('allows switching units when user has multiple units', function () {
    /** @var \Tests\TestCase $this */
    $unit1 = UsimUnit::firstOrCreate(['slug' => 'main']);
    $unit2 = UsimUnit::firstOrCreate(['slug' => 'finance']);
    $user = User::factory()->create(['name' => 'Switch Unit User']);
    $user->usimUnits()->attach([$unit1->id, $unit2->id]);
    $this->actingAs($user);

    $ui = uiScenario($this, Menu::class, ['parent' => 'menu']);
    expect($ui->component('unit_menu'))->not->toBeNull();

    $ui->action('unit_menu', 'changeUnit', ['unit' => 'finance', 'unit_id' => $unit2->id]);
    expect(session('current_unit_id'))->toBe($unit2->id);
    expect(session('current_unit_slug'))->toBe('finance');
});

