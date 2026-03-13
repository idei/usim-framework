<?php

use App\Models\User;
use App\UI\Screens\Auth\ForgotPassword;
use App\UI\Screens\Auth\Login;
use App\UI\Screens\Auth\ResetPassword;
use App\UI\Screens\Menu;

it('returns redirect contract on successful login event', function () {
    /** @var \Tests\TestCase $this */
    $password = 'secret123';
    User::factory()->create([
        'email' => 'ui-login@example.com',
        'password' => bcrypt($password),
    ]);

    $uiResponse = getScreenJson($this, Login::class);
    $uiResponse->assertOk();
    $componentId = serviceRootComponentId($uiResponse->json());

    $response = $this->postJson('/api/ui-event', [
        'component_id' => $componentId,
        'event' => 'click',
        'action' => 'submit_login',
        'parameters' => [
            'login_email' => 'ui-login@example.com',
            'login_password' => $password,
        ],
    ]);

    $response->assertOk();
    expect($response->json('redirect'))->not->toBeNull();
});

it('returns non-redirect UI feedback contract for invalid login event', function () {
    /** @var \Tests\TestCase $this */
    User::factory()->create([
        'email' => 'ui-invalid@example.com',
        'password' => bcrypt('secret123'),
    ]);

    $uiResponse = getScreenJson($this, Login::class);
    $uiResponse->assertOk();
    $componentId = serviceRootComponentId($uiResponse->json());

    $response = $this->postJson('/api/ui-event', [
        'component_id' => $componentId,
        'event' => 'click',
        'action' => 'submit_login',
        'parameters' => [
            'login_email' => 'ui-invalid@example.com',
            'login_password' => 'wrong-password',
        ],
    ]);

    $response->assertOk();
    expect($response->json('redirect'))->toBeNull();
    expect($response->json('toast'))->toBeArray();
});

it('returns UI feedback when forgot-password is submitted without email', function () {
    /** @var \Tests\TestCase $this */
    $uiResponse = getScreenJson($this, ForgotPassword::class);
    $uiResponse->assertOk();
    $componentId = serviceRootComponentId($uiResponse->json());

    $response = $this->postJson('/api/ui-event', [
        'component_id' => $componentId,
        'event' => 'click',
        'action' => 'send_link',
        'parameters' => [],
    ]);

    $response->assertOk();
    expect($response->json('error'))->toBeNull();
});

it('returns UI error feedback when reset-password has mismatched confirmation', function () {
    /** @var \Tests\TestCase $this */
    $uiResponse = getScreenJson($this, ResetPassword::class);
    $uiResponse->assertOk();
    $componentId = serviceRootComponentId($uiResponse->json());

    $response = $this->postJson('/api/ui-event', [
        'component_id' => $componentId,
        'event' => 'click',
        'action' => 'reset_password',
        'parameters' => [
            'reset_token' => 'dummy-token',
            'reset_email' => 'user@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'different123',
        ],
    ]);

    $response->assertOk();
    expect($response->json('toast'))->toBeArray();
    expect($response->json('redirect'))->toBeNull();
});

it('returns redirect contract on confirm_logout event from menu screen', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $this->actingAs($user);

    $uiResponse = getScreenJson($this, Menu::class, ['parent' => 'menu']);
    $uiResponse->assertOk();
    $componentId = serviceRootComponentId($uiResponse->json());

    $response = $this->postJson('/api/ui-event', [
        'component_id' => $componentId,
        'event' => 'click',
        'action' => 'confirm_logout',
        'parameters' => [],
    ]);

    $response->assertOk();
    expect($response->json('redirect'))->not->toBeNull();
});
