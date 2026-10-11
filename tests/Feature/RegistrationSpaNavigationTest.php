<?php

use App\Models\User;
use App\UI\Screens\Auth\Register;
use App\UI\Screens\Menu;
use App\UI\Screens\Registered;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

beforeEach(function () {
    app(\Idei\Usim\Support\RoleAndPermissionSyncService::class)->sync();
});

it('executes full SPA registration sequence without redirect and returns registered screen components', function () {
    Auth::logout();
    \Idei\Usim\Support\UIStateManager::clearClientCurrentScreen();
    \Idei\Usim\Support\UIStateManager::clearClientActiveModal();

    // 1. Initial menu load (as in Home landing)
    $ui = uiScenario($this, Menu::class, ['parent' => 'menu']);

    // 2. Open Register modal from user_menu
    $openResponse = $ui->action('user_menu', 'showScreen', [
        'screen' => Register::class,
        'modal' => true,
    ]);
    $openResponse->assertOk();
    expect(hasModalComponents($openResponse->json()))->toBeTrue();

    // 3. Open LegalTerms modal
    $termsResponse = $ui->click('btn_terms', []);
    $termsResponse->assertOk();

    // 4. Submit registration
    $email = 'spa.register.' . uniqid() . '@example.com';
    $submitButton = $ui->component('btn_submit_register')->data();
    $baseParameters = is_array($submitButton['parameters'] ?? null)
        ? $submitButton['parameters']
        : [];

    $submitResponse = $ui->click('btn_submit_register', array_merge($baseParameters, [
        'name' => 'Juan Diego Olivares',
        'email' => $email,
        'password' => 'secret1234',
        'password_confirmation' => 'secret1234',
        'accept_terms' => true,
    ]));

    $submitResponse->assertOk();
    $payload = $submitResponse->json();

    // Verification: Must be 100% SPA, NO redirect()
    expect($payload['redirect'] ?? null)->toBeNull();

    // Verification: Closes modal and shows toast
    expect($payload['action'] ?? null)->toBe('close_modal');
    expect($payload['toast']['type'] ?? null)->toBe('success');

    // Verification: SPA Navigation instructions
    expect($payload['navigate'] ?? null)->toBeArray();
    expect($payload['navigate']['url'])->toBe(Registered::getRoutePath());
    expect($payload['clear_container'] ?? null)->not->toBeNull();

    $slotContainerId = $payload['navigate']['slot_container_id'] ?? $payload['clear_container'];
    expect($slotContainerId)->toBe(104921240);

    // Verification: Contains Registered screen root container and child components
    $registeredContainer = null;
    $registeredChildren = [];

    foreach ($payload as $key => $comp) {
        if (!is_array($comp) || !isset($comp['type'])) {
            continue;
        }

        if ($comp['type'] === 'container' && ($comp['parent'] ?? null) == $slotContainerId) {
            $registeredContainer = $comp;
            $registeredContainer['_key'] = $key;
        } elseif (($comp['parent'] ?? null) != $slotContainerId && isset($comp['name'])) {
            if (str_starts_with($comp['name'], 'registered_')) {
                $registeredChildren[] = $comp;
            }
        }
    }

    expect($registeredContainer)->not->toBeNull();
    // 5 children: registered_icon, registered_title, registered_greeting, registered_card, registered_message
    expect($registeredChildren)->toHaveCount(5);

    /** @var TestCase $this */
    // Verification: User authenticated and user menu updated
    $user = User::where('email', $email)->firstOrFail();
    $this->assertAuthenticatedAs($user);

    $userMenuId = \Idei\Usim\Support\UIIdGenerator::generateFromName(Menu::class, 'user_menu');
    $userMenu = $payload[$userMenuId] ?? null;
    expect($userMenu)->not->toBeNull();
    expect($userMenu['trigger']['label'] ?? '')->toContain('Juan Diego Olivares');

    // 5. Open Profile modal from updated user_menu
    $profileResponse = $ui->action('user_menu', 'showScreen', [
        'screen' => \App\UI\Screens\Auth\Profile::class,
        'modal' => true,
    ]);
    $profileResponse->assertOk();
    $profilePayload = $profileResponse->json();
    expect(hasModalComponents($profilePayload))->toBeTrue();

    // Verify input_email and input_name are present and populated with user's info
    $inputEmail = null;
    $inputName = null;
    foreach ($profilePayload as $comp) {
        if (!is_array($comp) || !isset($comp['name'])) {
            continue;
        }
        if ($comp['name'] === 'input_email') {
            $inputEmail = $comp;
        } elseif ($comp['name'] === 'input_name') {
            $inputName = $comp;
        }
    }

    expect($inputEmail)->not->toBeNull();
    expect($inputEmail['value'])->toBe($email);
    expect($inputName)->not->toBeNull();
    expect($inputName['value'])->toBe('Juan Diego Olivares');

    // 6. Confirm logout from user_menu via SPA without redirect
    $logoutResponse = $ui->action('user_menu', 'confirm_logout', []);
    $logoutResponse->assertOk();
    $logoutPayload = $logoutResponse->json();
    expect($logoutPayload['redirect'] ?? null)->toBeNull();
    expect($logoutPayload['toast']['type'] ?? null)->toBe('success');
    expect($logoutPayload['navigate'] ?? null)->toBeArray();
    expect($logoutPayload['navigate']['url'])->toBe(\App\UI\Screens\Home::getRoutePath());
    expect(Auth::check())->toBeFalse();

    $loggedOutUserMenu = $logoutPayload[$userMenuId] ?? null;
    expect($loggedOutUserMenu)->not->toBeNull();
    expect($loggedOutUserMenu['trigger']['label'] ?? '')->toBe('⚙️');
});
