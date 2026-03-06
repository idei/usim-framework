<?php

use App\Models\User;
use App\UI\Screens\Auth\Login;
use Spatie\Permission\Models\Role;

it('loads login screen with expected components and actions', function () {
    /** @var \Tests\TestCase $this */
    $response = getScreenJson($this, Login::class);
    $response->assertOk();

    $payload = $response->json();

    $emailInput = findComponentByName($payload, 'login_email');
    $passwordInput = findComponentByName($payload, 'login_password');
    $submitButton = findComponentByName($payload, 'btn_submit_login');
    $forgotButton = findComponentByName($payload, 'btn_forgot_password');

    expect($emailInput)->not->toBeNull();
    expect($emailInput['type'] ?? null)->toBe('input');
    expect($emailInput['input_type'] ?? null)->toBe('email');

    expect($passwordInput)->not->toBeNull();
    expect($passwordInput['type'] ?? null)->toBe('input');
    expect($passwordInput['input_type'] ?? null)->toBe('password');

    expect($submitButton)->not->toBeNull();
    expect($submitButton['action'] ?? null)->toBe('submit_login');

    expect($forgotButton)->not->toBeNull();
    expect($forgotButton['action'] ?? null)->toBe('navigate_forgot_password');
});

it('authenticates configured admin user and returns redirect contract', function () {
    /** @var \Tests\TestCase $this */
    /** @var User $adminUser */
    Role::findOrCreate('admin');

    $adminConfig = config('users.admin');
    $adminUser = User::factory()->create([
        'name' => trim(($adminConfig['first_name'] ?? 'Admin') . ' ' . ($adminConfig['last_name'] ?? 'User')),
        'email' => $adminConfig['email'] ?? 'admin@example.com',
        'password' => bcrypt($adminConfig['password'] ?? 'password'),
    ]);
    $adminUser->assignRole('admin');

    $uiResponse = getScreenJson($this, Login::class);
    $uiResponse->assertOk();
    $componentId = serviceRootComponentId($uiResponse->json());

    $response = $this->postJson('/api/ui-event', [
        'component_id' => $componentId,
        'event' => 'click',
        'action' => 'submit_login',
        'parameters' => [
            'login_email' => $adminConfig['email'] ?? 'admin@example.com',
            'login_password' => $adminConfig['password'] ?? 'password',
        ],
    ]);

    $response->assertOk();
    expect($response->json('redirect'))->not->toBeNull();
    expect($response->json('toast.type'))->toBe('success');
    $this->assertAuthenticatedAs($adminUser);
});

it('authenticates configured regular user and returns redirect contract', function () {
    /** @var \Tests\TestCase $this */
    /** @var User $regularUser */
    Role::findOrCreate('user');

    $userConfig = config('users.user');
    $regularUser = User::factory()->create([
        'name' => trim(($userConfig['first_name'] ?? 'Regular') . ' ' . ($userConfig['last_name'] ?? 'User')),
        'email' => $userConfig['email'] ?? 'user@example.com',
        'password' => bcrypt($userConfig['password'] ?? 'password'),
    ]);
    $regularUser->assignRole('user');

    $uiResponse = getScreenJson($this, Login::class);
    $uiResponse->assertOk();
    $componentId = serviceRootComponentId($uiResponse->json());

    $response = $this->postJson('/api/ui-event', [
        'component_id' => $componentId,
        'event' => 'click',
        'action' => 'submit_login',
        'parameters' => [
            'login_email' => $userConfig['email'] ?? 'user@example.com',
            'login_password' => $userConfig['password'] ?? 'password',
        ],
    ]);

    $response->assertOk();
    expect($response->json('redirect'))->not->toBeNull();
    expect($response->json('toast.type'))->toBe('success');
    $this->assertAuthenticatedAs($regularUser);
});
