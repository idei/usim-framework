<?php

use App\Models\User;
use App\UI\Screens\Home;
use App\UI\Screens\Menu;

it('returns home screen with expected core components', function () {
    $ui = uiScenario($this, Home::class, ['reset' => true]);

    $welcome = $ui->component('welcome');
    $subtitle = $ui->component('subtitle');
    $features = $ui->component('features');
    $componentsCard = $ui->component('components_card');
    $gettingStartedCard = $ui->component('getting_started_card');

    $welcome->expect('type')->toBe('label');
    expect($welcome->data()['text'] ?? '')->toContain('Welcome to USIM UI Framework');

    $subtitle->expect('type')->toBe('label');
    $features->expect('type')->toBe('container');
    $componentsCard->expect('type')->toBe('card');
    $gettingStartedCard->expect('type')->toBe('card');

    $ui->assertNoIssues();
});

it('declares expected home card actions', function () {
    $ui = uiScenario($this, Home::class, ['reset' => true]);

    $componentsCard = $ui->component('components_card')->data();
    $easyCard = $ui->component('easy_card')->data();
    $customCard = $ui->component('custom_card')->data();

    expect(cardHasAction($componentsCard, 'view_demos'))->toBeFalse();
    expect(cardHasAction($easyCard, 'view_code'))->toBeFalse();
    expect(cardHasAction($customCard, 'customize'))->toBeFalse();

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
    $user = User::factory()->create([
        'name' => 'Menu Tester',
    ]);

    $this->actingAs($user);

    $ui = uiScenario($this, Menu::class, ['parent' => 'menu']);
    $userMenu = $ui->component('user_menu')->data();

    expect($userMenu['type'] ?? null)->toBe('menudropdown');
    expect((string) ($userMenu['trigger']['label'] ?? ''))->toContain('Menu Tester');
    expect(menuItemsContainLabel($userMenu['items'] ?? [], 'Logout'))->toBeTrue();
    expect(menuItemsContainLabel($userMenu['items'] ?? [], 'Register'))->toBeFalse();

    $ui->assertNoIssues();
});
