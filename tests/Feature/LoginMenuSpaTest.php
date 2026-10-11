<?php

use App\Models\User;
use App\UI\Screens\Admin\UsersManager;
use App\UI\Screens\Auth\Login;
use App\UI\Screens\Menu;
use Illuminate\Support\Facades\Auth;

beforeEach(function () {
    app(\Idei\Usim\Support\RoleAndPermissionSyncService::class)->sync();
});

it('updates user menu on successful SPA login and handles login_failed without redirect', function () {
    Auth::logout();
    \Idei\Usim\Support\UIStateManager::clearClientCurrentScreen();
    \Idei\Usim\Support\UIStateManager::clearClientActiveModal();

    // Create admin user for testing login
    $mainUnit = \Idei\Usim\Models\UsimUnit::firstOrCreate(['slug' => 'main']);
    $admin = User::factory()->create([
        'name' => 'Super Admin Tester',
        'email' => 'admin.spa.' . uniqid() . '@example.com',
        'password' => bcrypt('secret1234'),
    ]);
    $admin->usimUnits()->sync([$mainUnit->id]);
    setPermissionsTeamId($mainUnit->id);
    $admin->assignRole('admin');
    setPermissionsTeamId(null);

    // 1. Initial menu load (guest state)
    $ui = uiScenario($this, Menu::class, ['parent' => 'menu']);
    $userMenuId = \Idei\Usim\Support\UIIdGenerator::generateFromName(Menu::class, 'user_menu');
    $initialMenu = $ui->component('user_menu')->data();
    expect($initialMenu['trigger']['label'] ?? '')->toBe('⚙️');

    // 2. Open Login modal from user_menu
    $openResponse = $ui->action('user_menu', 'showScreen', [
        'screen' => Login::class,
        'modal' => true,
    ]);
    $openResponse->assertOk();
    expect(hasModalComponents($openResponse->json()))->toBeTrue();

    // 3. Submit invalid login credentials (login_failed)
    $submitButton = $ui->component('btn_submit_login')->data();
    $baseParameters = is_array($submitButton['parameters'] ?? null)
        ? $submitButton['parameters']
        : [];

    $failedResponse = $ui->click('btn_submit_login', array_merge($baseParameters, [
        'login_email' => $admin->email,
        'login_password' => 'wrong-password-123',
    ]));

    $failedResponse->assertOk();
    $failedPayload = $failedResponse->json();
    expect($failedPayload['redirect'] ?? null)->toBeNull();
    expect($failedPayload['toast']['type'] ?? null)->toBe('error');
    expect(Auth::check())->toBeFalse();

    // 4. Submit valid login credentials (logged_user)
    $successResponse = $ui->click('btn_submit_login', array_merge($baseParameters, [
        'login_email' => $admin->email,
        'login_password' => 'secret1234',
    ]));

    $successResponse->assertOk();
    $payload = $successResponse->json();

    // Verification: 100% SPA, NO redirect()
    expect($payload['redirect'] ?? null)->toBeNull();

    // Verification: Closes modal and shows success toast
    expect($payload['action'] ?? null)->toBe('close_modal');
    expect($payload['toast']['type'] ?? null)->toBe('success');

    // Verification: User is authenticated
    $this->assertAuthenticatedAs($admin);

    // Verification: user_menu is updated in the diff with the user's name
    $updatedUserMenu = $payload[$userMenuId] ?? null;
    expect($updatedUserMenu)->not->toBeNull();
    expect($updatedUserMenu['trigger']['label'] ?? '')->toContain('Super Admin Tester');

    // Verification: user_menu options now include Logout and Profile, not Login/Register
    $menuItems = $updatedUserMenu['items'] ?? [];
    $itemLabels = array_column($menuItems, 'label');
    $allLabelsText = implode(' ', $itemLabels);
    expect($allLabelsText)->toContain('Profile');
    expect($allLabelsText)->not->toContain('Login');

    // Verification: SPA navigation instructions to target screen (UsersManager)
    expect($payload['navigate'] ?? null)->toBeArray();
    expect($payload['navigate']['url'])->toBe(UsersManager::getRoutePath());

    // Verification: store_token is returned in storage payload
    expect($payload['storage'] ?? null)->toBeArray();
    $storageKey = (string) config('usim.front_store_key', 'usim-framework');
    $rawStorage = $payload['storage'][$storageKey] ?? null;
    expect($rawStorage)->not->toBeNull();
    $decodedStorage = json_decode((string) $rawStorage, true);
    expect($decodedStorage['store_token'] ?? null)->not->toBeEmpty();
});
