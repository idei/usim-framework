<?php

use App\Models\User;
use App\UI\Screens\Home;
use App\UI\Screens\Menu;

it('returns home screen with expected core components', function () {
    $response = getScreenJson($this, Home::class);
    $response->assertOk();

    $payload = $response->json();

    $welcome = findComponentByName($payload, 'welcome');
    $subtitle = findComponentByName($payload, 'subtitle');
    $features = findComponentByName($payload, 'features');
    $componentsCard = findComponentByName($payload, 'components_card');
    $gettingStartedCard = findComponentByName($payload, 'getting_started_card');

    expect($welcome)->not->toBeNull();
    expect($welcome['type'] ?? null)->toBe('label');
    expect($welcome['text'] ?? '')->toContain('Welcome to USIM UI Framework');

    expect($subtitle)->not->toBeNull();
    expect($subtitle['type'] ?? null)->toBe('label');

    expect($features)->not->toBeNull();
    expect($features['type'] ?? null)->toBe('container');

    expect($componentsCard)->not->toBeNull();
    expect($componentsCard['type'] ?? null)->toBe('card');

    expect($gettingStartedCard)->not->toBeNull();
    expect($gettingStartedCard['type'] ?? null)->toBe('card');
});

it('declares expected home card actions', function () {
    $response = getScreenJson($this, Home::class);
    $response->assertOk();

    $payload = $response->json();

    $componentsCard = findComponentByName($payload, 'components_card');
    $easyCard = findComponentByName($payload, 'easy_card');
    $customCard = findComponentByName($payload, 'custom_card');
    $gettingStartedCard = findComponentByName($payload, 'getting_started_card');

    expect($componentsCard)->not->toBeNull();
    expect(cardHasAction($componentsCard, 'view_demos'))->toBeTrue();

    expect($easyCard)->not->toBeNull();
    expect(cardHasAction($easyCard, 'view_code'))->toBeTrue();

    expect($customCard)->not->toBeNull();
    expect(cardHasAction($customCard, 'customize'))->toBeTrue();

    expect($gettingStartedCard)->not->toBeNull();
    expect(cardHasAction($gettingStartedCard, 'view_all_demos'))->toBeTrue();
    expect(cardHasAction($gettingStartedCard, 'view_docs'))->toBeTrue();
});

it('returns menu screen for guests with settings trigger and register option', function () {
    $response = getScreenJson($this, Menu::class, ['parent' => 'menu']);
    $response->assertOk();

    $payload = $response->json();
    $mainMenu = findComponentByName($payload, 'main_menu');
    $userMenu = findComponentByName($payload, 'user_menu');

    expect($mainMenu)->not->toBeNull();
    expect($mainMenu['type'] ?? null)->toBe('menudropdown');
    expect(menuItemsContainLabel($mainMenu['items'] ?? [], 'Home'))->toBeTrue();
    expect(menuItemsContainLabel($mainMenu['items'] ?? [], 'About'))->toBeTrue();

    expect($userMenu)->not->toBeNull();
    expect($userMenu['type'] ?? null)->toBe('menudropdown');
    expect($userMenu['trigger']['label'] ?? null)->toBe('⚙️');
    expect(menuItemsContainLabel($userMenu['items'] ?? [], 'Register'))->toBeTrue();
    expect(menuItemsContainLabel($userMenu['items'] ?? [], 'Logout'))->toBeFalse();
});

it('returns menu screen for authenticated users with user trigger and logout option', function () {
    /** @var \Tests\TestCase $this */
    /** @var User $user */
    $user = User::factory()->create([
        'name' => 'Menu Tester',
    ]);

    $this->actingAs($user);

    $response = getScreenJson($this, Menu::class, ['parent' => 'menu']);
    $response->assertOk();

    $payload = $response->json();
    $userMenu = findComponentByName($payload, 'user_menu');

    expect($userMenu)->not->toBeNull();
    expect($userMenu['type'] ?? null)->toBe('menudropdown');

    $triggerLabel = (string) ($userMenu['trigger']['label'] ?? '');
    expect($triggerLabel)->toContain('Menu Tester');

    expect(menuItemsContainLabel($userMenu['items'] ?? [], 'Logout'))->toBeTrue();
    expect(menuItemsContainLabel($userMenu['items'] ?? [], 'Register'))->toBeFalse();
});
