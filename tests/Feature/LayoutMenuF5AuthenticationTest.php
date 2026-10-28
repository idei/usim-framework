<?php

use App\Models\User;
use App\Services\Auth\AuthSessionService;
use App\UI\Layouts\MainLayout;
use App\UI\Screens\Auth\Login;
use App\UI\Screens\Home;
use App\UI\Screens\Menu;
use Idei\Usim\Contracts\UIStateRepositoryInterface;
use Illuminate\Support\Facades\Auth;

beforeEach(function () {
    app(\Idei\Usim\Support\RoleAndPermissionSyncService::class)->sync();
});

it('preserves authenticated user menu on F5 reload after login and reverts on logout', function () {
    /** @var \Tests\TestCase $this */
    Auth::logout();
    \Idei\Usim\Support\UIStateManager::clearClientCurrentScreen();
    \Idei\Usim\Support\UIStateManager::clearClientActiveModal();

    // 1. Initial guest visit to home screen
    $guestResponse = $this->getJson('/api/ui/home');
    $guestResponse->assertOk();
    $guestData = $guestResponse->json();

    $guestUserMenu = findComponentByName($guestData, 'user_menu');
    expect($guestUserMenu)->not->toBeNull();
    expect($guestUserMenu['trigger']['label'])->toBe('⚙️');

    // 2. Perform user login via UI event
    $user = User::factory()->create([
        'name' => 'John Doe',
        'email' => 'john.doe.' . uniqid() . '@example.com',
        'password' => bcrypt('secret1234'),
    ]);

    $loginPayload = getScreenJson($this, Login::class)->json();
    $loginRoot = serviceRootComponentId($loginPayload, Login::class);
    $submitBtn = findComponentByName($loginPayload, 'btn_submit_login');
    $submitComponentId = $submitBtn ? (int) $submitBtn['_json_key'] : $loginRoot;

    $loginResponse = $this->postJson('/api/ui-event', [
        'component_id' => $submitComponentId,
        'event' => 'click',
        'action' => 'submit_login',
        'parameters' => [
            'login_email' => $user->email,
            'login_password' => 'secret1234',
        ],
    ]);
    $loginResponse->assertOk();
    $this->assertAuthenticatedAs($user);

    // 3. User reloads page (F5 -> GET /api/ui/home)
    $f5Response = $this->getJson('/api/ui/home');
    $f5Response->assertOk();
    $f5Data = $f5Response->json();

    $f5UserMenu = findComponentByName($f5Data, 'user_menu');
    expect($f5UserMenu)->not->toBeNull();
    expect($f5UserMenu['trigger']['label'])->toContain('John Doe');

    // Verify authenticated menu items
    $f5MenuItems = $f5UserMenu['items'] ?? [];
    $itemLabels = implode(' ', array_column($f5MenuItems, 'label'));
    expect($itemLabels)->toContain('Profile');
    expect($itemLabels)->not->toContain('Login');

    // 4. User logs out via UI event
    $menuPayload = getScreenJson($this, Menu::class, ['parent' => 'menu'])->json();
    $menuRootId = serviceRootComponentId($menuPayload, Menu::class);

    $logoutResponse = $this->postJson('/api/ui-event', [
        'component_id' => $menuRootId,
        'event' => 'click',
        'action' => 'confirm_logout',
        'parameters' => [],
    ]);
    $logoutResponse->assertOk();
    expect(Auth::check())->toBeFalse();

    // 5. User reloads page after logout (F5 -> GET /api/ui/home)
    $afterLogoutResponse = $this->getJson('/api/ui/home');
    $afterLogoutResponse->assertOk();
    $afterLogoutData = $afterLogoutResponse->json();

    $loggedOutUserMenu = findComponentByName($afterLogoutData, 'user_menu');
    expect($loggedOutUserMenu)->not->toBeNull();
    expect($loggedOutUserMenu['trigger']['label'])->toBe('⚙️');

    $loggedOutMenuItems = $loggedOutUserMenu['items'] ?? [];
    $loggedOutLabels = implode(' ', array_column($loggedOutMenuItems, 'label'));
    expect($loggedOutLabels)->toContain('Login');
    expect($loggedOutLabels)->not->toContain('Logout');
});

it('clears client snapshots and active modals on establishSession and logout', function () {
    $repo = app(UIStateRepositoryInterface::class);
    $user = User::factory()->create(['name' => 'Snapshot Test User']);

    // Seed state in repository
    $repo->store(Home::class, ['100' => ['name' => 'test_comp', 'type' => 'label']]);
    $repo->store(MainLayout::class, ['200' => ['name' => 'test_layout', 'type' => 'container']]);
    $repo->pushClientActiveModal(Login::class);

    expect($repo->get(Home::class))->not->toBeNull();
    expect($repo->get(MainLayout::class))->not->toBeNull();
    expect($repo->getClientActiveModalStack())->not->toBeEmpty();

    // Call establishSession -> must clear snapshots and active modal stack
    $authService = app(AuthSessionService::class);
    $authService->establishSession($user);

    expect($repo->get(Home::class))->toBeNull();
    expect($repo->get(MainLayout::class))->toBeNull();
    expect($repo->getClientActiveModalStack())->toBeEmpty();

    // Seed state again
    $repo->store(Home::class, ['100' => ['name' => 'test_comp_auth', 'type' => 'label']]);
    $repo->store(MainLayout::class, ['200' => ['name' => 'test_layout_auth', 'type' => 'container']]);
    $repo->pushClientActiveModal(Login::class);

    expect($repo->get(Home::class))->not->toBeNull();
    expect($repo->get(MainLayout::class))->not->toBeNull();

    // Call logout -> must clear snapshots and active modal stack
    $authService->logout($user);

    expect($repo->get(Home::class))->toBeNull();
    expect($repo->get(MainLayout::class))->toBeNull();
    expect($repo->getClientActiveModalStack())->toBeEmpty();
});
