<?php

use App\Models\User;
use App\UI\Screens\Home;
use App\UI\Screens\Menu;

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

it('home screen fragment is non-empty regardless of locale', function () {
    app()->setLocale('es');

    $ui = uiScenario($this, Home::class, ['reset' => true]);
    $html = $ui->component('welcome_usim')->data()['html'] ?? '';

    expect($html)->not->toBeEmpty();

    app()->setLocale(config('app.locale'));

    $ui->assertNoIssues();
});

it('returns menu screen for guests with settings trigger and register option', function () {
    $ui = uiScenario($this, Menu::class, ['parent' => 'menu']);

    $mainMenu = $ui->component('main_menu')->data();
    $userMenu = $ui->component('user_menu')->data();

    expect($mainMenu['type'] ?? null)->toBe('menudropdown');
    expect(menuItemsContainLabel($mainMenu['items'] ?? [], 'Home'))->toBeTrue();
    expect(menuItemsContainLabel($mainMenu['items'] ?? [], 'About'))->toBeTrue();

    expect($userMenu['type'] ?? null)->toBe('menudropdown');
    expect($userMenu['trigger']['label'] ?? null)->toBe('⚙️');
    expect(menuItemsContainLabel($userMenu['items'] ?? [], 'Register'))->toBeTrue();
    expect(menuItemsContainLabel($userMenu['items'] ?? [], 'Logout'))->toBeFalse();

    $ui->assertNoIssues();
});

it('returns menu screen for authenticated users with user trigger and logout option', function () {
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

    expect(menuItemsContainLabel($userMenu['items'] ?? [], 'Logout'))->toBeTrue();
    expect(menuItemsContainLabel($userMenu['items'] ?? [], 'Register'))->toBeFalse();

    $ui->assertNoIssues();
});

it('shows profile, logout and admin dashboard items after admin login', function () {
    /** @var \Tests\TestCase $this */
    $this->loginAs('admin');

    $ui = uiScenario($this, Menu::class, ['parent' => 'menu']);
    $mainMenu = $ui->component('main_menu')->data();
    $userMenu = $ui->component('user_menu')->data();

    expect(menuItemsContainLabel($mainMenu['items'] ?? [], 'Dashboard'))->toBeTrue();
    expect(menuItemsContainLabel($userMenu['items'] ?? [], 'Profile'))->toBeTrue();
    expect(menuItemsContainLabel($userMenu['items'] ?? [], 'Logout'))->toBeTrue();

    $ui->assertNoIssues();
});

it('shows profile/logout and hides admin dashboard after regular user login', function () {
    /** @var \Tests\TestCase $this */
    $this->loginAs('user');

    $ui = uiScenario($this, Menu::class, ['parent' => 'menu']);
    $mainMenu = $ui->component('main_menu')->data();
    $userMenu = $ui->component('user_menu')->data();

    expect(menuItemsContainLabel($mainMenu['items'] ?? [], 'Dashboard'))->toBeFalse();
    expect(menuItemsContainLabel($userMenu['items'] ?? [], 'Profile'))->toBeTrue();
    expect(menuItemsContainLabel($userMenu['items'] ?? [], 'Logout'))->toBeTrue();

    $ui->assertNoIssues();
});
