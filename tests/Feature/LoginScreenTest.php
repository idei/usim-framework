<?php

use App\Models\User;
use App\UI\Screens\Auth\Login;

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
    $result = $this->loginAs('admin');
    /** @var User $adminUser */
    $adminUser = $result['user'];
    $response = $result['response'];

    $response->assertOk();
    expect($response->json('redirect'))->not->toBeNull();
    expect($response->json('toast.type'))->toBe('success');
    $this->assertAuthenticatedAs($adminUser);
});

it('authenticates configured regular user and returns redirect contract', function () {
    /** @var \Tests\TestCase $this */
    $result = $this->loginAs('user');
    /** @var User $regularUser */
    $regularUser = $result['user'];
    $response = $result['response'];

    $response->assertOk();
    expect($response->json('redirect'))->not->toBeNull();
    expect($response->json('toast.type'))->toBe('success');
    $this->assertAuthenticatedAs($regularUser);
});
