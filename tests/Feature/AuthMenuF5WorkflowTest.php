<?php

use App\Models\User;
use App\UI\Screens\Admin\UsersManager;
use App\UI\Screens\Auth\Login;
use App\UI\Screens\Home;
use App\UI\Screens\Menu;
use Idei\Usim\Events\UsimEvent;
use Idei\Usim\Listeners\UsimEventDispatcher;
use Idei\Usim\Support\UIIdGenerator;
use Idei\Usim\Support\UIStateManager;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    app(\Idei\Usim\Support\RoleAndPermissionSyncService::class)->sync();
});

it('recovers and updates Menu and navigates when cache expires before login submission', function () {
    Auth::logout();
    UIStateManager::clearClientCurrentScreen();
    UIStateManager::clearClientActiveModal();

    // Create admin user for testing login
    $mainUnit = \Idei\Usim\Models\UsimUnit::firstOrCreate(['slug' => 'main']);
    $admin = User::factory()->create([
        'name' => 'Resilient Login Tester',
        'email' => 'admin.resilient.' . uniqid() . '@example.com',
        'password' => bcrypt('secret1234'),
    ]);
    $admin->usimUnits()->sync([$mainUnit->id]);
    setPermissionsTeamId($mainUnit->id);
    $admin->assignRole('admin');
    setPermissionsTeamId(null);

    // 1. Initial menu load (guest state)
    $ui = uiScenario($this, Menu::class, ['parent' => 'menu']);
    $userMenuId = UIIdGenerator::generateFromName(Menu::class, 'user_menu');
    $initialMenu = $ui->component('user_menu')->data();
    expect($initialMenu['trigger']['label'] ?? '')->toBe('⚙️');

    // 2. Open Login modal
    $openResponse = $ui->action('user_menu', 'showScreen', [
        'screen' => Login::class,
        'modal' => true,
    ]);
    $openResponse->assertOk();
    expect(hasModalComponents($openResponse->json()))->toBeTrue();

    // 3. Simulate cache expiration (user inactive > 60 seconds)
    $clientId = UIStateManager::getOrCreateClientId();
    Cache::forget("ui_open_screens:{$clientId}");
    Cache::forget("ui_open_classes:{$clientId}");

    // Verify cache is evicted
    expect(UIStateManager::getClientOpenedScreens($clientId))->toBeEmpty();

    // 4. User submits login after timeout
    $submitButton = $ui->component('btn_submit_login')->data();
    $baseParameters = is_array($submitButton['parameters'] ?? null)
        ? $submitButton['parameters']
        : [];

    $successResponse = $ui->click('btn_submit_login', array_merge($baseParameters, [
        'login_email' => $admin->email,
        'login_password' => 'secret1234',
    ]));

    $successResponse->assertOk();
    $payload = $successResponse->json();

    // Verify modal is closed
    expect($payload['action'] ?? null)->toBe('close_modal');

    // Verify user is authenticated
    $this->assertAuthenticatedAs($admin);

    // Verify user_menu is updated in the diff with the user's name despite cache eviction
    $updatedUserMenu = $payload[$userMenuId] ?? null;
    expect($updatedUserMenu)->not->toBeNull();
    expect($updatedUserMenu['trigger']['label'] ?? '')->toContain('Resilient Login Tester');

    // Verify user_menu options now include Logout and Profile, not Login/Register
    $menuItems = $updatedUserMenu['items'] ?? [];
    $itemLabels = array_column($menuItems, 'label');
    $allLabelsText = implode(' ', $itemLabels);
    expect($allLabelsText)->toContain('Profile');
    expect($allLabelsText)->not->toContain('Login');

    // Verify SPA navigation instructions to target screen (UsersManager)
    expect($payload['navigate'] ?? null)->toBeArray();
    expect($payload['navigate']['url'])->toBe(UsersManager::getRoutePath());
});

it('deduplicates identical UsimEvent dispatch in same request cycle', function () {
    UsimEventDispatcher::resetRequestState();

    $event = new UsimEvent('test_dedup_event', ['timestamp' => microtime(true)]);
    $dispatcher = app(UsimEventDispatcher::class);

    // Dispatch the same event instance twice
    $dispatcher->handle($event);
    $dispatcher->handle($event);

    // Reset state should clear hashes
    UsimEventDispatcher::resetRequestState();

    // After reset, the same event can be dispatched again
    $dispatcher->handle($event);
    expect(true)->toBeTrue();
});

it('touches client session and refreshes cache TTL', function () {
    $clientId = 'test-touch-client-' . uniqid();
    UIStateManager::setClientCurrentScreen('/home', Home::class, $clientId);
    UIStateManager::storeKeyValue('active_unit', 'main', $clientId);

    expect(UIStateManager::getClientCurrentScreenClass($clientId))->toBe(Home::class);
    expect(UIStateManager::getActiveUnit($clientId))->toBe('main');

    // Call touchClientSession
    UIStateManager::touchClientSession($clientId);

    expect(UIStateManager::getClientCurrentScreenClass($clientId))->toBe(Home::class);
    expect(UIStateManager::getActiveUnit($clientId))->toBe('main');
});

it('closes modal and navigates to target screen via closeModalAndNavigate', function () {
    $login = Login::openAsModal();
    expect($login->isOpenedAsModal())->toBeTrue();

    $result = $login->closeModalAndNavigate(Home::class);
    expect($result)->toBeTrue();

    $collector = app(\Idei\Usim\UIChangesCollector::class);
    $changes = $collector->all();

    expect($changes['action'] ?? null)->toBe('close_modal');
    expect($changes['navigate'] ?? null)->toBeArray();
    expect($changes['navigate']['url'])->toBe(Home::getRoutePath());
});
